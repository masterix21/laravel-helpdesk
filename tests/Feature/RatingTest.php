<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use LucaLongo\LaravelHelpdesk\Enums\TicketStatus;
use LucaLongo\LaravelHelpdesk\Models\Ticket;
use LucaLongo\LaravelHelpdesk\Events\TicketRated;
use LucaLongo\LaravelHelpdesk\Events\TicketRatingUpdated;
use LucaLongo\LaravelHelpdesk\Models\TicketRating;
use LucaLongo\LaravelHelpdesk\Services\RatingService;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new RatingService();
    $this->user = new \LucaLongo\LaravelHelpdesk\Tests\Fakes\User(['id' => 1]);
    $this->ticket = Ticket::factory()->create([
        'opened_by_type' => get_class($this->user),
        'opened_by_id' => $this->user->id,
        'status' => TicketStatus::Resolved,
        'closed_at' => now()->subHours(2),
    ]);
});

describe('Rating submission', function () {
    it('can submit a rating for a resolved ticket', function () {
        Event::fake();

        $rating = $this->service->submitRating(
            $this->ticket,
            $this->user,
            5,
            'Excellent service!',
            ['device' => 'mobile']
        );

        expect($rating)->toBeInstanceOf(TicketRating::class)
            ->and($rating->ticket_id)->toBe($this->ticket->id)
            ->and($rating->user_id)->toBe($this->user->id)
            ->and($rating->rating)->toBe(5)
            ->and($rating->feedback)->toBe('Excellent service!')
            ->and($rating->metadata)->toHaveKey('device', 'mobile');

        Event::assertDispatched(TicketRated::class, function ($event) use ($rating) {
            return $event->rating->id === $rating->id;
        });
    });

    it('updates existing rating if user rates again', function () {
        Event::fake();

        $firstRating = $this->service->submitRating($this->ticket, $this->user, 3, 'OK service');

        $updatedRating = $this->service->submitRating(
            $this->ticket,
            $this->user,
            5,
            'Actually, great service!'
        );

        expect($updatedRating->id)->toBe($firstRating->id)
            ->and($updatedRating->rating)->toBe(5)
            ->and($updatedRating->feedback)->toBe('Actually, great service!');

        Event::assertDispatched(TicketRatingUpdated::class);
    });

    it('throws exception for invalid rating values', function () {
        $this->service->submitRating($this->ticket, $this->user, 6);
    })->throws(\InvalidArgumentException::class, 'Rating must be between 1 and 5');

    it('cannot rate ticket if not eligible', function () {
        $otherUser = new \LucaLongo\LaravelHelpdesk\Tests\Fakes\User(['id' => 2]);
        $otherTicket = Ticket::factory()->create([
            'opened_by_type' => get_class($otherUser),
            'opened_by_id' => $otherUser->id,
            'status' => TicketStatus::Resolved,
        ]);

        $rating = $this->service->submitRating($otherTicket, $this->user, 5);

        expect($rating)->toBeNull();
    });

    it('cannot rate ticket in non-allowed status', function () {
        $openTicket = Ticket::factory()->create([
            'opened_by_type' => get_class($this->user),
            'opened_by_id' => $this->user->id,
            'status' => TicketStatus::Open,
        ]);

        $canRate = $this->service->canRateTicket($openTicket, $this->user);

        expect($canRate)->toBeFalse();
    });

    it('calculates response time hours correctly', function () {
        $ticket = Ticket::factory()->create([
            'opened_by_type' => get_class($this->user),
            'opened_by_id' => $this->user->id,
            'status' => TicketStatus::Resolved,
            'opened_at' => now()->subHours(24),
            'closed_at' => now(),
        ]);

        $rating = $this->service->submitRating($ticket, $this->user, 4);

        expect($rating->response_time_hours)->toBe(24);
    });
});

describe('Rating helpers', function () {
    it('correctly identifies positive ratings', function () {
        $rating = TicketRating::factory()->create(['rating' => 4]);
        expect($rating->isPositive())->toBeTrue()
            ->and($rating->isNeutral())->toBeFalse()
            ->and($rating->isNegative())->toBeFalse();
    });

    it('correctly identifies neutral ratings', function () {
        $rating = TicketRating::factory()->create(['rating' => 3]);
        expect($rating->isNeutral())->toBeTrue()
            ->and($rating->isPositive())->toBeFalse()
            ->and($rating->isNegative())->toBeFalse();
    });

    it('correctly identifies negative ratings', function () {
        $rating = TicketRating::factory()->create(['rating' => 2]);
        expect($rating->isNegative())->toBeTrue()
            ->and($rating->isPositive())->toBeFalse()
            ->and($rating->isNeutral())->toBeFalse();
    });

    it('generates correct star display', function () {
        $rating = TicketRating::factory()->create(['rating' => 3]);
        expect($rating->getStars())->toBe('★★★☆☆');
    });

    it('returns correct satisfaction level', function () {
        $ratings = [
            5 => 'Very Satisfied',
            4 => 'Satisfied',
            3 => 'Neutral',
            2 => 'Dissatisfied',
            1 => 'Very Dissatisfied',
        ];

        foreach ($ratings as $score => $expectedLevel) {
            $rating = TicketRating::factory()->create(['rating' => $score]);
            expect($rating->getSatisfactionLevel())->toBe($expectedLevel);
        }
    });
});

describe('Rating metrics', function () {
    beforeEach(function () {
        TicketRating::factory()->count(3)->create(['rating' => 5, 'rated_at' => now()]);
        TicketRating::factory()->count(2)->create(['rating' => 4, 'rated_at' => now()]);
        TicketRating::factory()->count(1)->create(['rating' => 3, 'rated_at' => now()]);
        TicketRating::factory()->count(1)->create(['rating' => 2, 'rated_at' => now()]);
        TicketRating::factory()->count(1)->create(['rating' => 1, 'rated_at' => now()]);
    });

    it('calculates average rating correctly', function () {
        $average = $this->service->getAverageRating();
        expect($average)->toBeGreaterThanOrEqual(3.5)
            ->and($average)->toBeLessThanOrEqual(4.0);
    });

    it('calculates CSAT correctly', function () {
        $csat = $this->service->getCSAT();
        expect($csat)->toBe(62.5);
    });

    it('calculates NPS correctly', function () {
        $nps = $this->service->getNPS();
        expect($nps)->toBe(12.5);
    });

    it('returns correct rating distribution', function () {
        $distribution = $this->service->getRatingDistribution();

        expect($distribution->get(5))->toBe(3)
            ->and($distribution->get(4))->toBe(2)
            ->and($distribution->get(3))->toBe(1)
            ->and($distribution->get(2))->toBe(1)
            ->and($distribution->get(1))->toBe(1);
    });

    it('calculates response rate correctly', function () {
        // Create resolved tickets with ratings
        $ratedTickets = Ticket::factory()->count(3)->create([
            'status' => TicketStatus::Resolved,
            'closed_at' => now(),
        ]);

        foreach ($ratedTickets as $ticket) {
            TicketRating::factory()->create(['ticket_id' => $ticket->id]);
        }

        // Create resolved tickets without ratings
        Ticket::factory()->count(2)->create([
            'status' => TicketStatus::Resolved,
            'closed_at' => now(),
        ]);

        // Expect at least some ratings exist (response rate > 0)
        $responseRate = $this->service->getResponseRate();
        expect($responseRate)->toBeGreaterThan(0)
            ->and($responseRate)->toBeLessThanOrEqual(100);
    });

    it('returns correct sentiment breakdown', function () {
        $sentiment = $this->service->getFeedbackSentiment();

        expect($sentiment['positive'])->toBe(62.5)
            ->and($sentiment['neutral'])->toBe(12.5)
            ->and($sentiment['negative'])->toBe(25.0);
    });
});

describe('Rating queries', function () {
    it('filters positive ratings', function () {
        TicketRating::factory()->positive()->count(3)->create();
        TicketRating::factory()->negative()->count(2)->create();

        $positive = TicketRating::positive()->count();
        expect($positive)->toBe(3);
    });

    it('filters negative ratings', function () {
        TicketRating::factory()->positive()->count(2)->create();
        TicketRating::factory()->negative()->count(4)->create();

        $negative = TicketRating::negative()->count();
        expect($negative)->toBe(4);
    });

    it('filters ratings in period', function () {
        TicketRating::factory()->count(2)->create(['rated_at' => now()]);
        TicketRating::factory()->count(3)->create(['rated_at' => now()->subDays(10)]);

        $recent = TicketRating::inPeriod(now()->subDays(5), now())->count();
        expect($recent)->toBe(2);
    });
});

describe('Recent feedback', function () {
    it('retrieves recent feedback with filters', function () {
        TicketRating::factory()->count(5)->create([
            'rating' => 5,
            'feedback' => 'Great!',
            'rated_at' => now(),
        ]);
        TicketRating::factory()->count(3)->create([
            'rating' => 1,
            'feedback' => 'Poor service',
            'rated_at' => now()->subDays(1),
        ]);

        $positiveFeedback = $this->service->getRecentFeedback(3, 4);
        expect($positiveFeedback)->toHaveCount(3)
            ->and($positiveFeedback->every(fn ($r) => $r->rating >= 4))->toBeTrue();

        $negativeFeedback = $this->service->getRecentFeedback(2, null, 2);
        expect($negativeFeedback)->toHaveCount(2)
            ->and($negativeFeedback->every(fn ($r) => $r->rating <= 2))->toBeTrue();
    });
});

describe('Comprehensive metrics', function () {
    it('returns all metrics in a single call', function () {
        TicketRating::factory()->count(10)->create();

        $metrics = $this->service->getMetrics();

        expect($metrics)->toHaveKeys([
            'average_rating',
            'csat',
            'nps',
            'response_rate',
            'average_response_time_hours',
            'sentiment',
            'distribution',
        ])
            ->and($metrics['sentiment'])->toHaveKeys(['positive', 'neutral', 'negative'])
            ->and($metrics['distribution'])->toHaveKeys([1, 2, 3, 4, 5]);
    });
});