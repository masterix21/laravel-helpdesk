<?php

declare(strict_types=1);

namespace LucaLongo\LaravelHelpdesk\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\File;
use LucaLongo\LaravelHelpdesk\Models\Ticket;
use LucaLongo\LaravelHelpdesk\Services\TimeTrackingService;

class GenerateMetricsSnapshotCommand extends Command
{
    protected $signature = 'helpdesk:metrics
        {--format=json : Output format; supported: json, csv}
        {--path= : Destination file path; defaults to storage/app/helpdesk/helpdesk_metrics_{timestamp}.(json|csv)}
        {--from= : Filter tickets opened on or after this date (YYYY-MM-DD)}
        {--to= : Filter tickets opened on or before this date (YYYY-MM-DD)}
        {--within=60 : Minutes window to consider tickets approaching their SLA deadline}';

    protected $description = 'Generate SLA and time tracking metrics snapshot for Helpdesk tickets.';

    public function __construct(private readonly TimeTrackingService $timeTrackingService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $format = strtolower((string) $this->option('format'));
        if (! in_array($format, ['json', 'csv'], true)) {
            $this->error('Unsupported format. Choose either json or csv.');

            return static::INVALID;
        }

        $within = (int) $this->option('within');
        if ($within < 1) {
            $this->error('The --within option must be at least 1 minute.');

            return static::INVALID;
        }

        [$rangeStart, $rangeEnd] = $this->resolveRange();
        if ($rangeStart === false || $rangeEnd === false) {
            return static::INVALID;
        }

        $baseQuery = Ticket::query();

        $baseQuery = $this->applyDateFilters($baseQuery, $rangeStart, $rangeEnd);

        /** @var Collection<int, Ticket> $tickets */
        $tickets = (clone $baseQuery)->orderBy('opened_at')->get();
        /** @var Collection<int, Ticket> $approaching */
        $approaching = (clone $baseQuery)->approachingSla($within)->get();
        /** @var Collection<int, Ticket> $breached */
        $breached = (clone $baseQuery)->breachedSla()->get();

        $snapshot = $this->buildSnapshot($tickets, $approaching, $breached, $within, $rangeStart, $rangeEnd);

        $payload = $format === 'json'
            ? $this->formatAsJson($snapshot)
            : $this->formatAsCsv($snapshot);

        $path = $this->resolvePath($format);
        if ($path === false) {
            return static::INVALID;
        }

        File::ensureDirectoryExists(dirname($path));
        File::put($path, $payload);

        $this->components->info(sprintf('Helpdesk metrics snapshot generated: %s', $path));
        $this->renderOverview($snapshot);

        return static::SUCCESS;
    }

    private function resolveRange(): array
    {
        $fromOption = $this->option('from');
        $toOption = $this->option('to');

        try {
            $start = $fromOption ? CarbonImmutable::parse($fromOption)->startOfDay() : null;
        } catch (\Exception $exception) {
            $this->error(sprintf('Invalid --from date: %s', $exception->getMessage()));

            return [false, false];
        }

        try {
            $end = $toOption ? CarbonImmutable::parse($toOption)->endOfDay() : null;
        } catch (\Exception $exception) {
            $this->error(sprintf('Invalid --to date: %s', $exception->getMessage()));

            return [false, false];
        }

        if ($start && $end && $start->greaterThan($end)) {
            $this->error('--from date must be before or equal to --to date.');

            return [false, false];
        }

        return [$start, $end];
    }

    private function applyDateFilters(Builder $query, ?CarbonImmutable $start, ?CarbonImmutable $end): Builder
    {
        if ($start) {
            $query->where('opened_at', '>=', $start);
        }

        if ($end) {
            $query->where('opened_at', '<=', $end);
        }

        return $query;
    }

    private function buildSnapshot(
        Collection $tickets,
        Collection $approaching,
        Collection $breached,
        int $within,
        ?CarbonImmutable $start,
        ?CarbonImmutable $end
    ): array {
        $approachingIds = $approaching->pluck('id')->all();
        $breachedIds = $breached->pluck('id')->all();

        $responseTimes = [];
        $totalMinutes = 0;
        $totalBillableMinutes = 0;
        $totalCost = 0.0;
        $ticketRows = [];

        foreach ($tickets as $ticket) {
            $totalMinutesForTicket = $this->timeTrackingService->getTicketTotalTime($ticket->id);
            $billableMinutesForTicket = $this->timeTrackingService->getTicketTotalTime($ticket->id, true);
            $costForTicket = $this->timeTrackingService->getTicketTotalCost($ticket->id);

            if ($ticket->response_time_minutes !== null) {
                $responseTimes[] = $ticket->response_time_minutes;
            }

            $totalMinutes += $totalMinutesForTicket;
            $totalBillableMinutes += $billableMinutesForTicket;
            $totalCost += $costForTicket;

            $ticketRows[] = [
                'id' => $ticket->id,
                'ulid' => $ticket->ulid,
                'ticket_number' => $ticket->ticket_number,
                'subject' => $ticket->subject,
                'status' => $ticket->status->value,
                'priority' => $ticket->priority?->value,
                'opened_at' => optional($ticket->opened_at)?->toIso8601String(),
                'first_response_due_at' => optional($ticket->first_response_due_at)?->toIso8601String(),
                'first_response_at' => optional($ticket->first_response_at)?->toIso8601String(),
                'resolution_due_at' => optional($ticket->resolution_due_at)?->toIso8601String(),
                'closed_at' => optional($ticket->closed_at)?->toIso8601String(),
                'response_time_minutes' => $ticket->response_time_minutes,
                'resolution_time_minutes' => $ticket->resolution_time_minutes,
                'total_minutes_logged' => $totalMinutesForTicket,
                'billable_minutes_logged' => $billableMinutesForTicket,
                'total_cost' => round($costForTicket, 2),
                'sla_status' => $this->resolveSlaStatus($ticket->id, $approachingIds, $breachedIds),
            ];
        }

        $ticketCount = $tickets->count();
        $breachedCount = count($breachedIds);
        $approachingCount = count($approachingIds);
        $breachRate = $ticketCount > 0 ? round(($breachedCount / $ticketCount) * 100, 2) : 0.0;
        $averageResponseTime = $responseTimes === [] ? null : round(array_sum($responseTimes) / count($responseTimes), 2);
        sort($responseTimes);
        $medianResponseTime = $this->calculateMedian($responseTimes);

        return [
            'generated_at' => now()->toIso8601String(),
            'range_start' => $start?->toIso8601String(),
            'range_end' => $end?->toIso8601String(),
            'within_minutes' => $within,
            'totals' => [
                'tickets' => $ticketCount,
                'approaching_sla' => $approachingCount,
                'breached_sla' => $breachedCount,
                'breach_rate_percent' => $breachRate,
                'average_response_time_minutes' => $averageResponseTime,
                'median_response_time_minutes' => $medianResponseTime,
                'total_minutes_logged' => $totalMinutes,
                'billable_minutes_logged' => $totalBillableMinutes,
                'total_cost' => round($totalCost, 2),
            ],
            'tickets' => $ticketRows,
        ];
    }

    private function resolveSlaStatus(int $ticketId, array $approachingIds, array $breachedIds): string
    {
        if (in_array($ticketId, $breachedIds, true)) {
            return 'breached';
        }

        if (in_array($ticketId, $approachingIds, true)) {
            return 'approaching';
        }

        return 'healthy';
    }

    private function calculateMedian(array $values): ?float
    {
        $count = count($values);

        if ($count === 0) {
            return null;
        }

        $middle = (int) floor($count / 2);

        if ($count % 2) {
            return (float) $values[$middle];
        }

        return round(($values[$middle - 1] + $values[$middle]) / 2, 2);
    }

    private function formatAsJson(array $snapshot): string
    {
        return json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function formatAsCsv(array $snapshot): string
    {
        $rows = [];

        foreach ($snapshot['tickets'] as $ticket) {
            $rows[] = [
                'type' => 'ticket',
                'ticket_id' => $ticket['id'],
                'ulid' => $ticket['ulid'],
                'ticket_number' => $ticket['ticket_number'] ?? '',
                'subject' => $ticket['subject'] ?? '',
                'status' => $ticket['status'],
                'priority' => $ticket['priority'] ?? '',
                'opened_at' => $ticket['opened_at'] ?? '',
                'first_response_due_at' => $ticket['first_response_due_at'] ?? '',
                'first_response_at' => $ticket['first_response_at'] ?? '',
                'resolution_due_at' => $ticket['resolution_due_at'] ?? '',
                'closed_at' => $ticket['closed_at'] ?? '',
                'response_time_minutes' => $ticket['response_time_minutes'] ?? '',
                'resolution_time_minutes' => $ticket['resolution_time_minutes'] ?? '',
                'total_minutes_logged' => $ticket['total_minutes_logged'],
                'billable_minutes_logged' => $ticket['billable_minutes_logged'],
                'total_cost' => $ticket['total_cost'],
                'sla_status' => $ticket['sla_status'],
                'breached_total' => '',
                'approaching_total' => '',
                'breach_rate_percent' => '',
                'average_response_time_minutes' => '',
                'median_response_time_minutes' => '',
                'range_start' => $snapshot['range_start'] ?? '',
                'range_end' => $snapshot['range_end'] ?? '',
                'within_minutes' => $snapshot['within_minutes'],
            ];
        }

        $totals = $snapshot['totals'];
        $rows[] = [
            'type' => 'summary',
            'ticket_id' => '',
            'ulid' => '',
            'ticket_number' => '',
            'subject' => '',
            'status' => '',
            'priority' => '',
            'opened_at' => '',
            'first_response_due_at' => '',
            'first_response_at' => '',
            'resolution_due_at' => '',
            'closed_at' => '',
            'response_time_minutes' => '',
            'resolution_time_minutes' => '',
            'total_minutes_logged' => $totals['total_minutes_logged'],
            'billable_minutes_logged' => $totals['billable_minutes_logged'],
            'total_cost' => $totals['total_cost'],
            'sla_status' => '',
            'breached_total' => $totals['breached_sla'],
            'approaching_total' => $totals['approaching_sla'],
            'breach_rate_percent' => $totals['breach_rate_percent'],
            'average_response_time_minutes' => $totals['average_response_time_minutes'] ?? '',
            'median_response_time_minutes' => $totals['median_response_time_minutes'] ?? '',
            'range_start' => $snapshot['range_start'] ?? '',
            'range_end' => $snapshot['range_end'] ?? '',
            'within_minutes' => $snapshot['within_minutes'],
        ];

        return $this->convertRowsToCsv($rows);
    }

    private function convertRowsToCsv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');

        if ($rows === []) {
            $rows[] = [
                'type' => 'summary',
                'ticket_id' => '',
                'ulid' => '',
                'ticket_number' => '',
                'subject' => '',
                'status' => '',
                'priority' => '',
                'opened_at' => '',
                'first_response_due_at' => '',
                'first_response_at' => '',
                'resolution_due_at' => '',
                'closed_at' => '',
                'response_time_minutes' => '',
                'resolution_time_minutes' => '',
                'total_minutes_logged' => 0,
                'billable_minutes_logged' => 0,
                'total_cost' => 0,
                'sla_status' => '',
                'breached_total' => 0,
                'approaching_total' => 0,
                'breach_rate_percent' => 0,
                'average_response_time_minutes' => '',
                'median_response_time_minutes' => '',
                'range_start' => '',
                'range_end' => '',
                'within_minutes' => $this->option('within'),
            ];
        }

        fputcsv($handle, array_keys($rows[0]));

        foreach ($rows as $row) {
            $normalized = array_map(function ($value) {
                if (is_bool($value)) {
                    return $value ? 'true' : 'false';
                }

                if ($value === null) {
                    return '';
                }

                return $value;
            }, $row);

            fputcsv($handle, $normalized);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv ?: '';
    }

    private function resolvePath(string $format): string|false
    {
        $pathOption = $this->option('path');
        $timestamp = now()->format('Ymd_His');

        if (! $pathOption) {
            return storage_path(sprintf('app/helpdesk/helpdesk_metrics_%s.%s', $timestamp, $format));
        }

        $pathOption = (string) $pathOption;

        if (str_ends_with($pathOption, '/')) {
            return $pathOption.sprintf('helpdesk_metrics_%s.%s', $timestamp, $format);
        }

        $extension = pathinfo($pathOption, PATHINFO_EXTENSION);
        if ($extension === '') {
            return $pathOption.DIRECTORY_SEPARATOR.sprintf('helpdesk_metrics_%s.%s', $timestamp, $format);
        }

        if ($extension !== $format) {
            $this->error(sprintf('The provided --path must end with .%s', $format));

            return false;
        }

        return $pathOption;
    }

    private function renderOverview(array $snapshot): void
    {
        $totals = $snapshot['totals'];

        $this->table(
            ['Tickets', 'Approaching SLA', 'Breached SLA', 'Breach %', 'Avg Resp (min)', 'Median Resp (min)', 'Total Minutes', 'Billable Minutes', 'Total Cost'],
            [[
                $totals['tickets'],
                $totals['approaching_sla'],
                $totals['breached_sla'],
                $totals['breach_rate_percent'],
                $totals['average_response_time_minutes'] ?? 'n/a',
                $totals['median_response_time_minutes'] ?? 'n/a',
                $totals['total_minutes_logged'],
                $totals['billable_minutes_logged'],
                $totals['total_cost'],
            ]]
        );
    }
}
