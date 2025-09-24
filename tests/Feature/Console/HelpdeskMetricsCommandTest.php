<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use LucaLongo\LaravelHelpdesk\Enums\TicketPriority;
use LucaLongo\LaravelHelpdesk\Models\Ticket;
use LucaLongo\LaravelHelpdesk\Models\TicketTimeEntry;

use function Pest\Laravel\artisan;

beforeEach(function (): void {
    File::deleteDirectory(storage_path('app/helpdesk'));
});

it('generates a JSON metrics snapshot with SLA and time tracking aggregation', function (): void {
    $approachingTicket = Ticket::factory()->create([
        'opened_at' => now()->subDay(),
        'first_response_due_at' => now()->addMinutes(45),
        'first_response_at' => null,
        'response_time_minutes' => 30,
        'priority' => TicketPriority::High->value,
        'sla_breached' => false,
    ]);

    $breachedTicket = Ticket::factory()->create([
        'opened_at' => now()->subDays(2),
        'sla_breached' => true,
        'sla_breach_type' => 'resolution',
        'response_time_minutes' => 90,
        'resolution_time_minutes' => 240,
    ]);

    TicketTimeEntry::factory()->create([
        'ticket_id' => $approachingTicket->id,
        'user_id' => 10,
        'started_at' => now()->subHours(3),
        'ended_at' => now()->subHours(2),
        'duration_minutes' => 60,
        'is_billable' => true,
        'hourly_rate' => 120.00,
    ]);

    TicketTimeEntry::factory()->create([
        'ticket_id' => $breachedTicket->id,
        'user_id' => 10,
        'started_at' => now()->subHours(5),
        'ended_at' => now()->subHours(3.5),
        'duration_minutes' => 90,
        'is_billable' => false,
        'hourly_rate' => null,
    ]);

    $path = storage_path('app/helpdesk/test_metrics.json');

    artisan('helpdesk:metrics', [
        '--format' => 'json',
        '--path' => $path,
        '--within' => 60,
        '--from' => now()->subDays(7)->toDateString(),
        '--to' => now()->toDateString(),
    ])->assertExitCode(0);

    expect(File::exists($path))->toBeTrue();

    $payload = json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR);

    expect($payload['totals']['tickets'])->toBe(2)
        ->and($payload['totals']['approaching_sla'])->toBe(1)
        ->and($payload['totals']['breached_sla'])->toBe(1)
        ->and($payload['totals']['total_minutes_logged'])->toBe(150)
        ->and($payload['totals']['billable_minutes_logged'])->toBe(60)
        ->and($payload['totals']['total_cost'])->toEqual(120.0);

    $ticketSnapshots = collect($payload['tickets'])->keyBy('id');

    expect($ticketSnapshots[$approachingTicket->id]['sla_status'])->toBe('approaching')
        ->and($ticketSnapshots[$breachedTicket->id]['sla_status'])->toBe('breached');
});

it('writes a CSV snapshot with summary rows when no tickets match the filters', function (): void {
    $path = storage_path('app/helpdesk/test_metrics.csv');

    artisan('helpdesk:metrics', [
        '--format' => 'csv',
        '--path' => $path,
    ])->assertExitCode(0);

    expect(File::exists($path))->toBeTrue();

    $rows = array_values(array_filter(array_map('trim', explode(PHP_EOL, File::get($path)))));

    expect(count($rows))->toBeGreaterThanOrEqual(2);

    $header = str_getcsv($rows[0]);
    $summaryRow = str_getcsv($rows[array_key_last($rows)]);

    $typeIndex = array_search('type', $header, true);
    $breachedIndex = array_search('breached_total', $header, true);
    $withinIndex = array_search('within_minutes', $header, true);

    expect($typeIndex)->not->toBeFalse()
        ->and($summaryRow[$typeIndex])->toBe('summary')
        ->and((int) $summaryRow[$breachedIndex])->toBe(0)
        ->and((int) $summaryRow[$withinIndex])->toBe(60);
});
