<?php

namespace LucaLongo\LaravelHelpdesk\Services;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use LucaLongo\LaravelHelpdesk\Events\TimeEntryStarted;
use LucaLongo\LaravelHelpdesk\Events\TimeEntryStopped;
use LucaLongo\LaravelHelpdesk\Models\Ticket;
use LucaLongo\LaravelHelpdesk\Models\TicketTimeEntry;

class TimeTrackingService
{
    public function startTimer(
        int $ticketId,
        ?int $userId = null,
        ?string $description = null,
        bool $stopRunning = true
    ): TicketTimeEntry {
        if ($stopRunning && $userId) {
            $this->stopUserRunningTimers($userId);
        }

        $entry = TicketTimeEntry::create([
            'ticket_id' => $ticketId,
            'user_id' => $userId,
            'started_at' => now(),
            'description' => $description,
            'is_billable' => config('helpdesk.time_tracking.billable_by_default', true),
        ]);

        event(new TimeEntryStarted($entry));

        return $entry;
    }

    public function stopTimer(int $entryId): ?TicketTimeEntry
    {
        $entry = TicketTimeEntry::find($entryId);

        if (! $entry || ! $entry->isRunning()) {
            return null;
        }

        $entry->stop();
        event(new TimeEntryStopped($entry));

        return $entry;
    }

    public function stopUserRunningTimers(int $userId): Collection
    {
        $runningEntries = TicketTimeEntry::running()
            ->byUser($userId)
            ->get();

        $runningEntries->each(function (TicketTimeEntry $entry) {
            $entry->stop();
            event(new TimeEntryStopped($entry));
        });

        return $runningEntries;
    }

    public function logTime(
        int $ticketId,
        int $durationMinutes,
        ?int $userId = null,
        ?string $description = null,
        ?Carbon $startedAt = null,
        bool $isBillable = true,
        ?float $hourlyRate = null
    ): TicketTimeEntry {
        $startedAt = $startedAt ?? now()->subMinutes($durationMinutes);
        $endedAt = $startedAt->copy()->addMinutes($durationMinutes);

        return TicketTimeEntry::create([
            'ticket_id' => $ticketId,
            'user_id' => $userId,
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'duration_minutes' => $durationMinutes,
            'description' => $description,
            'is_billable' => $isBillable,
            'hourly_rate' => $hourlyRate,
        ]);
    }

    public function getTicketTimeEntries(int $ticketId): Collection
    {
        return TicketTimeEntry::where('ticket_id', $ticketId)
            ->with('user')
            ->orderBy('started_at', 'desc')
            ->get();
    }

    public function getTicketTotalTime(int $ticketId, bool $billableOnly = false): int
    {
        $query = TicketTimeEntry::where('ticket_id', $ticketId);

        if ($billableOnly) {
            $query->billable();
        }

        return $query->sum('duration_minutes') ?: 0;
    }

    public function getTicketTotalCost(int $ticketId): float
    {
        return TicketTimeEntry::where('ticket_id', $ticketId)
            ->billable()
            ->whereNotNull('hourly_rate')
            ->get()
            ->sum('total_cost');
    }

    public function getUserTimeReport(
        int $userId,
        Carbon $startDate,
        Carbon $endDate,
        ?bool $billableOnly = null
    ): array {
        $query = TicketTimeEntry::byUser($userId)
            ->dateRange($startDate, $endDate)
            ->with('ticket');

        if ($billableOnly === true) {
            $query->billable();
        } elseif ($billableOnly === false) {
            $query->nonBillable();
        }

        $entries = $query->get();

        $totalMinutes = $entries->sum('duration_minutes');
        $billableMinutes = $entries->where('is_billable', true)->sum('duration_minutes');
        $totalCost = $entries->sum('total_cost');

        $byTicket = $entries->groupBy('ticket_id')->map(function ($ticketEntries) {
            $ticket = $ticketEntries->first()->ticket;

            return [
                'ticket' => $ticket,
                'total_minutes' => $ticketEntries->sum('duration_minutes'),
                'billable_minutes' => $ticketEntries->where('is_billable', true)->sum('duration_minutes'),
                'total_cost' => $ticketEntries->sum('total_cost'),
                'entries_count' => $ticketEntries->count(),
            ];
        });

        return [
            'user_id' => $userId,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'total_minutes' => $totalMinutes,
            'total_hours' => round($totalMinutes / 60, 2),
            'billable_minutes' => $billableMinutes,
            'billable_hours' => round($billableMinutes / 60, 2),
            'total_cost' => $totalCost,
            'entries_count' => $entries->count(),
            'by_ticket' => $byTicket,
        ];
    }

    public function getProjectTimeReport(
        string $projectIdentifier,
        Carbon $startDate,
        Carbon $endDate
    ): array {
        $tickets = Ticket::where('meta->project', $projectIdentifier)
            ->pluck('id');

        $entries = TicketTimeEntry::whereIn('ticket_id', $tickets)
            ->dateRange($startDate, $endDate)
            ->with(['ticket', 'user'])
            ->get();

        $totalMinutes = $entries->sum('duration_minutes');
        $billableMinutes = $entries->where('is_billable', true)->sum('duration_minutes');
        $totalCost = $entries->sum('total_cost');

        $byUser = $entries->groupBy('user_id')->map(function ($userEntries) {
            $user = $userEntries->first()->user;

            return [
                'user' => $user,
                'total_minutes' => $userEntries->sum('duration_minutes'),
                'billable_minutes' => $userEntries->where('is_billable', true)->sum('duration_minutes'),
                'total_cost' => $userEntries->sum('total_cost'),
                'entries_count' => $userEntries->count(),
            ];
        });

        $byTicket = $entries->groupBy('ticket_id')->map(function ($ticketEntries) {
            $ticket = $ticketEntries->first()->ticket;

            return [
                'ticket' => $ticket,
                'total_minutes' => $ticketEntries->sum('duration_minutes'),
                'billable_minutes' => $ticketEntries->where('is_billable', true)->sum('duration_minutes'),
                'total_cost' => $ticketEntries->sum('total_cost'),
                'entries_count' => $ticketEntries->count(),
            ];
        });

        return [
            'project' => $projectIdentifier,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'total_minutes' => $totalMinutes,
            'total_hours' => round($totalMinutes / 60, 2),
            'billable_minutes' => $billableMinutes,
            'billable_hours' => round($billableMinutes / 60, 2),
            'total_cost' => $totalCost,
            'entries_count' => $entries->count(),
            'by_user' => $byUser,
            'by_ticket' => $byTicket,
        ];
    }

    public function deleteEntry(int $entryId): bool
    {
        return TicketTimeEntry::destroy($entryId) > 0;
    }

    public function updateEntry(
        int $entryId,
        array $data
    ): ?TicketTimeEntry {
        $entry = TicketTimeEntry::find($entryId);

        if (! $entry) {
            return null;
        }

        $entry->update($data);

        return $entry;
    }
}
