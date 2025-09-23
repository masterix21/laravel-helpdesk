<?php

namespace LucaLongo\LaravelHelpdesk\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use LucaLongo\LaravelHelpdesk\Enums\TicketStatus;
use LucaLongo\LaravelHelpdesk\Events\TicketRated;
use LucaLongo\LaravelHelpdesk\Events\TicketRatingUpdated;
use LucaLongo\LaravelHelpdesk\Models\Ticket;
use LucaLongo\LaravelHelpdesk\Models\TicketRating;

class RatingService
{
    public function submitRating(
        Ticket $ticket,
        Model $user,
        int $rating,
        ?string $feedback = null,
        array $metadata = []
    ): ?TicketRating {
        $validator = Validator::make([
            'rating' => $rating,
            'feedback' => $feedback,
        ], [
            'rating' => 'required|integer|min:1|max:5',
            'feedback' => 'nullable|string|max:5000',
        ]);

        if ($validator->fails()) {
            throw new \InvalidArgumentException($validator->errors()->first());
        }

        if (! $this->canRateTicket($ticket, $user)) {
            return null;
        }

        $responseTimeHours = null;
        if ($ticket->closed_at) {
            $responseTimeHours = $ticket->opened_at->diffInHours($ticket->closed_at);
        }

        $existingRating = $this->getUserRating($ticket, $user);

        if ($existingRating) {
            $existingRating->update([
                'rating' => $rating,
                'feedback' => $feedback,
                'rated_at' => now(),
                'response_time_hours' => $responseTimeHours,
                'metadata' => array_merge($existingRating->metadata ?? [], $metadata),
            ]);

            event(new TicketRatingUpdated($existingRating));

            return $existingRating;
        }

        $ticketRating = TicketRating::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'rating' => $rating,
            'feedback' => $feedback,
            'resolved_at' => $ticket->closed_at,
            'rated_at' => now(),
            'response_time_hours' => $responseTimeHours,
            'metadata' => $metadata,
        ]);

        event(new TicketRated($ticketRating));

        return $ticketRating;
    }

    public function canRateTicket(Ticket $ticket, Model $user): bool
    {
        if (! in_array($ticket->status, config('helpdesk.rating.allowed_statuses', [TicketStatus::Resolved, TicketStatus::Closed]))) {
            return false;
        }

        $ratingPeriod = config('helpdesk.rating.rating_period_days', 30);
        if ($ratingPeriod > 0 && $ticket->closed_at) {
            $expiryDate = $ticket->closed_at->addDays($ratingPeriod);
            if (now()->greaterThan($expiryDate)) {
                return false;
            }
        }

        if ($ticket->opened_by_type === $user->getMorphClass() && $ticket->opened_by_id === $user->getKey()) {
            return true;
        }

        // Use whereHas to avoid N+1 query issue
        if ($ticket->subscriptions()->where('user_id', $user->id)->exists()) {
            return true;
        }

        return false;
    }

    public function getUserRating(Ticket $ticket, Model $user): ?TicketRating
    {
        return TicketRating::where('ticket_id', $ticket->id)
            ->where('user_id', $user->id)
            ->first();
    }

    public function getAverageRating(?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): float
    {
        $query = TicketRating::query();

        if ($from && $to) {
            $query->whereBetween('rated_at', [$from, $to]);
        }

        return round($query->avg('rating') ?? 0, 2);
    }

    public function getCSAT(?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): float
    {
        $query = TicketRating::query();

        if ($from && $to) {
            $query->whereBetween('rated_at', [$from, $to]);
        }

        $total = $query->count();

        if ($total === 0) {
            return 0;
        }

        $satisfied = (clone $query)->where('rating', '>=', 4)->count();

        return round(($satisfied / $total) * 100, 2);
    }

    public function getNPS(?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): float
    {
        $query = TicketRating::query();

        if ($from && $to) {
            $query->whereBetween('rated_at', [$from, $to]);
        }

        $total = $query->count();

        if ($total === 0) {
            return 0;
        }

        $promoters = (clone $query)->where('rating', '>=', 5)->count();
        $detractors = (clone $query)->where('rating', '<=', 2)->count();

        $nps = (($promoters - $detractors) / $total) * 100;

        return round($nps, 2);
    }

    public function getRatingDistribution(?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): Collection
    {
        $query = TicketRating::query();

        if ($from && $to) {
            $query->whereBetween('rated_at', [$from, $to]);
        }

        $distribution = $query
            ->select('rating', DB::raw('count(*) as count'))
            ->groupBy('rating')
            ->orderBy('rating')
            ->get()
            ->pluck('count', 'rating');

        $result = collect();
        for ($i = 1; $i <= 5; $i++) {
            $result->put($i, $distribution->get($i, 0));
        }

        return $result;
    }

    public function getResponseRate(?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): float
    {
        $ticketQuery = Ticket::whereIn('status', config('helpdesk.rating.allowed_statuses', [TicketStatus::Resolved, TicketStatus::Closed]));

        if ($from && $to) {
            $ticketQuery->whereBetween('closed_at', [$from, $to]);
        }

        $totalEligibleTickets = $ticketQuery->count();

        if ($totalEligibleTickets === 0) {
            return 0;
        }

        $ratedTickets = (clone $ticketQuery)->has('rating')->count();

        return round(($ratedTickets / $totalEligibleTickets) * 100, 2);
    }

    public function getAverageResponseTime(?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): ?float
    {
        $query = TicketRating::whereNotNull('response_time_hours');

        if ($from && $to) {
            $query->whereBetween('rated_at', [$from, $to]);
        }

        $average = $query->avg('response_time_hours');

        return $average ? round($average, 2) : null;
    }

    public function getFeedbackSentiment(?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): array
    {
        $query = TicketRating::query();

        if ($from && $to) {
            $query->whereBetween('rated_at', [$from, $to]);
        }

        $total = $query->count();

        if ($total === 0) {
            return [
                'positive' => 0,
                'neutral' => 0,
                'negative' => 0,
            ];
        }

        $positive = (clone $query)->where('rating', '>=', 4)->count();
        $neutral = (clone $query)->where('rating', '=', 3)->count();
        $negative = (clone $query)->where('rating', '<=', 2)->count();

        return [
            'positive' => round(($positive / $total) * 100, 2),
            'neutral' => round(($neutral / $total) * 100, 2),
            'negative' => round(($negative / $total) * 100, 2),
        ];
    }

    public function getRecentFeedback(int $limit = 10, ?int $minRating = null, ?int $maxRating = null): Collection
    {
        $query = TicketRating::with(['ticket', 'user'])
            ->whereNotNull('feedback')
            ->orderBy('rated_at', 'desc');

        if ($minRating !== null) {
            $query->where('rating', '>=', $minRating);
        }

        if ($maxRating !== null) {
            $query->where('rating', '<=', $maxRating);
        }

        return $query->limit($limit)->get();
    }

    public function getMetrics(?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): array
    {
        return [
            'average_rating' => $this->getAverageRating($from, $to),
            'csat' => $this->getCSAT($from, $to),
            'nps' => $this->getNPS($from, $to),
            'response_rate' => $this->getResponseRate($from, $to),
            'average_response_time_hours' => $this->getAverageResponseTime($from, $to),
            'sentiment' => $this->getFeedbackSentiment($from, $to),
            'distribution' => $this->getRatingDistribution($from, $to),
        ];
    }

    public function getTrends(int $days = 30, string $interval = 'day'): Collection
    {
        $from = now()->subDays($days)->startOfDay();
        $to = now()->endOfDay();

        $query = TicketRating::whereBetween('rated_at', [$from, $to]);

        $dateFormat = match ($interval) {
            'day' => '%Y-%m-%d',
            'week' => '%Y-%u',
            'month' => '%Y-%m',
            default => '%Y-%m-%d',
        };

        return $query
            ->select(
                DB::raw("DATE_FORMAT(rated_at, '$dateFormat') as period"),
                DB::raw('AVG(rating) as average_rating'),
                DB::raw('COUNT(*) as total_ratings'),
                DB::raw('SUM(CASE WHEN rating >= 4 THEN 1 ELSE 0 END) as positive_count')
            )
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->map(function ($item) {
                $item->csat = $item->total_ratings > 0
                    ? round(($item->positive_count / $item->total_ratings) * 100, 2)
                    : 0;
                $item->average_rating = round($item->average_rating, 2);

                return $item;
            });
    }
}
