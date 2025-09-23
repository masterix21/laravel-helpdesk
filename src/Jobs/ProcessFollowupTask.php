<?php

namespace LucaLongo\LaravelHelpdesk\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use LucaLongo\LaravelHelpdesk\Models\Ticket;

class ProcessFollowupTask implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected int $ticketId,
        protected string $taskType,
        protected array $parameters = [],
    ) {}

    public function handle(): void
    {
        $ticket = Ticket::find($this->ticketId);

        if (! $ticket) {
            Log::warning('Follow-up task: Ticket not found', ['ticket_id' => $this->ticketId]);

            return;
        }

        switch ($this->taskType) {
            case 'follow_up':
                $this->handleFollowUp($ticket);
                break;

            case 'escalation_check':
                $this->handleEscalationCheck($ticket);
                break;

            case 'reminder':
                $this->handleReminder($ticket);
                break;

            case 'auto_close_check':
                $this->handleAutoCloseCheck($ticket);
                break;

            default:
                Log::warning('Unknown follow-up task type', [
                    'ticket_id' => $this->ticketId,
                    'task_type' => $this->taskType,
                ]);
        }
    }

    protected function handleFollowUp(Ticket $ticket): void
    {
        if ($ticket->status->isTerminal()) {
            return;
        }

        $lastActivity = $ticket->comments()->latest()->first()?->created_at ?? $ticket->updated_at;
        $daysSinceActivity = $lastActivity->diffInDays(now());

        if ($daysSinceActivity >= 3) {
            $ticket->comments()->create([
                'body' => $this->parameters['message'] ?? 'This ticket has been inactive for '.$daysSinceActivity.' days. Please provide an update or it may be closed.',
                'is_internal' => false,
                'author_type' => null,
                'author_id' => null,
            ]);

            if ($this->parameters['notify_assignee'] ?? true) {
                // Send notification to assignee
            }
        }
    }

    protected function handleEscalationCheck(Ticket $ticket): void
    {
        if ($ticket->status->isTerminal()) {
            return;
        }

        $shouldEscalate = false;

        if ($ticket->isFirstResponseOverdue()) {
            $shouldEscalate = true;
        } elseif ($ticket->isResolutionOverdue()) {
            $shouldEscalate = true;
        }

        if ($shouldEscalate) {
            $automationService = app(\LucaLongo\LaravelHelpdesk\Services\AutomationService::class);
            $automationService->processTicket($ticket, 'escalation_check');
        }
    }

    protected function handleReminder(Ticket $ticket): void
    {
        if ($ticket->status->isTerminal()) {
            return;
        }

        $message = $this->parameters['message'] ?? 'Reminder: This ticket requires attention.';

        $ticket->comments()->create([
            'body' => $message,
            'is_internal' => $this->parameters['is_internal'] ?? true,
            'author_type' => 'system',
            'author_id' => null,
        ]);
    }

    protected function handleAutoCloseCheck(Ticket $ticket): void
    {
        if ($ticket->status !== \LucaLongo\LaravelHelpdesk\Enums\TicketStatus::Resolved) {
            return;
        }

        $lastActivity = $ticket->comments()->latest()->first()?->created_at ?? $ticket->updated_at;
        $daysSinceActivity = $lastActivity->diffInDays(now());

        if ($daysSinceActivity >= ($this->parameters['days'] ?? 7)) {
            $ticket->transitionTo(\LucaLongo\LaravelHelpdesk\Enums\TicketStatus::Closed);

            $ticket->comments()->create([
                'body' => 'Ticket automatically closed after '.$daysSinceActivity.' days of inactivity.',
                'is_internal' => true,
                'author_type' => null,
                'author_id' => null,
            ]);
        }
    }
}
