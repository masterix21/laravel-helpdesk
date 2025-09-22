<?php

namespace LucaLongo\LaravelHelpdesk\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use LucaLongo\LaravelHelpdesk\Enums\TicketType;
use LucaLongo\LaravelHelpdesk\Models\ResponseTemplate;
use LucaLongo\LaravelHelpdesk\Models\Ticket;

class ResponseTemplateService
{
    public function getTemplatesForTicket(Ticket $ticket): Collection
    {
        return ResponseTemplate::active()
            ->forType($ticket->type)
            ->orderBy('name')
            ->get();
    }

    public function getTemplateBySlug(string $slug): ?ResponseTemplate
    {
        return ResponseTemplate::active()
            ->where('slug', $slug)
            ->first();
    }

    public function renderTemplate(ResponseTemplate $template, Ticket $ticket, array $additionalVariables = []): string
    {
        $variables = array_merge(
            $this->getTicketVariables($ticket),
            $this->getAgentVariables(),
            $additionalVariables
        );

        return $template->render($variables);
    }

    public function applyTemplate(string $slug, Ticket $ticket, array $additionalVariables = []): ?string
    {
        $template = $this->getTemplateBySlug($slug);

        if (! $template) {
            return null;
        }

        return $this->renderTemplate($template, $ticket, $additionalVariables);
    }

    public function createDefaultTemplates(): void
    {
        $templates = [
            [
                'name' => 'Welcome',
                'slug' => 'welcome',
                'content' => "Hello {customer_name},\n\nThank you for contacting our support team. Your ticket #{ticket_number} has been created and we will respond to you shortly.\n\nBest regards,\n{agent_name}",
                'variables' => ['customer_name', 'ticket_number', 'agent_name'],
            ],
            [
                'name' => 'Ticket Resolved',
                'slug' => 'resolved',
                'content' => "Hi {customer_name},\n\nYour ticket #{ticket_number} has been resolved. If you have any further questions, please don't hesitate to contact us.\n\nBest regards,\n{agent_name}",
                'variables' => ['customer_name', 'ticket_number', 'agent_name'],
            ],
            [
                'name' => 'Awaiting Customer Response',
                'slug' => 'awaiting-response',
                'content' => "Hi {customer_name},\n\nWe need additional information to proceed with your ticket #{ticket_number}.\n\n{message}\n\nPlease respond at your earliest convenience.\n\nBest regards,\n{agent_name}",
                'variables' => ['customer_name', 'ticket_number', 'message', 'agent_name'],
            ],
            [
                'name' => 'Technical Issue - Product Support',
                'slug' => 'technical-issue',
                'content' => "Hi {customer_name},\n\nRegarding your technical issue with ticket #{ticket_number}:\n\n{message}\n\nIf you need further assistance, please let us know.\n\nBest regards,\n{agent_name}",
                'ticket_type' => TicketType::ProductSupport,
                'variables' => ['customer_name', 'ticket_number', 'message', 'agent_name'],
            ],
            [
                'name' => 'Commercial Inquiry Response',
                'slug' => 'commercial-response',
                'content' => "Dear {customer_name},\n\nThank you for your interest in our products/services (Ticket #{ticket_number}).\n\n{message}\n\nWe look forward to working with you.\n\nBest regards,\n{agent_name}",
                'ticket_type' => TicketType::Commercial,
                'variables' => ['customer_name', 'ticket_number', 'message', 'agent_name'],
            ],
        ];

        foreach ($templates as $templateData) {
            ResponseTemplate::firstOrCreate(
                ['slug' => $templateData['slug']],
                $templateData
            );
        }
    }

    protected function getTicketVariables(Ticket $ticket): array
    {
        return [
            'ticket_number' => $ticket->ticket_number,
            'ticket_subject' => $ticket->subject,
            'ticket_type' => $ticket->type?->label() ?? '',
            'ticket_status' => $ticket->status->label(),
            'ticket_priority' => $ticket->priority->label(),
            'customer_name' => $ticket->customer_name,
            'customer_email' => $ticket->customer_email,
        ];
    }

    protected function getAgentVariables(): array
    {
        $user = Auth::user();

        return [
            'agent_name' => $user?->name ?? 'Support Team',
            'agent_email' => $user?->email ?? config('mail.from.address'),
            'company_name' => config('app.name'),
        ];
    }
}
