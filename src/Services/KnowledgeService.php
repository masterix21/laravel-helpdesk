<?php

namespace LucaLongo\LaravelHelpdesk\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LucaLongo\LaravelHelpdesk\Enums\KnowledgeArticleStatus;
use LucaLongo\LaravelHelpdesk\Enums\KnowledgeSuggestionMatchType;
use LucaLongo\LaravelHelpdesk\Enums\TicketStatus;
use LucaLongo\LaravelHelpdesk\Events\KnowledgeArticleViewed;
use LucaLongo\LaravelHelpdesk\Events\KnowledgeSuggestionGenerated;
use LucaLongo\LaravelHelpdesk\Models\KnowledgeArticle;
use LucaLongo\LaravelHelpdesk\Models\KnowledgeSection;
use LucaLongo\LaravelHelpdesk\Models\KnowledgeSuggestion;
use LucaLongo\LaravelHelpdesk\Models\Ticket;

class KnowledgeService
{
    public function suggestArticlesForTicket(Ticket $ticket, int $limit = 5): Collection
    {
        $suggestions = collect();

        // Clear existing suggestions
        $ticket->knowledgeSuggestions()->delete();

        // 1. Search by keywords in title and description
        $keywordMatches = $this->findArticlesByKeywords($ticket, $limit);
        $suggestions = $suggestions->merge($keywordMatches);

        // 2. Search by category matches
        $suggestions = $suggestions->merge(
            $ticket->categories->isNotEmpty()
                ? $this->findArticlesByCategories($ticket, $limit)
                : collect()
        );

        // 3. Search by similar resolved tickets
        $suggestions = $suggestions->merge($this->findArticlesBySimilarTickets($ticket, $limit));

        // 4. Search by tags if present
        $suggestions = $suggestions->merge(
            $ticket->tags->isNotEmpty()
                ? $this->findArticlesByTags($ticket, $limit)
                : collect()
        );

        // Store suggestions in database
        $suggestions->each(function ($suggestion) use ($ticket) {
            KnowledgeSuggestion::create([
                'ticket_id' => $ticket->id,
                'article_id' => $suggestion['article']->id,
                'relevance_score' => $suggestion['score'],
                'match_type' => $suggestion['match_type'],
                'matched_terms' => $suggestion['matched_terms'] ?? null,
            ]);
        });

        event(new KnowledgeSuggestionGenerated($ticket, $suggestions->count()));

        // Return top suggestions sorted by weighted score
        return $ticket->knowledgeSuggestions()
            ->with('article')
            ->get()
            ->sortByDesc(fn($s) => $s->getWeightedScore())
            ->take($limit);
    }

    protected function findArticlesByKeywords(Ticket $ticket, int $limit): \Illuminate\Support\Collection
    {
        $terms = $this->extractKeywords($ticket->subject . ' ' . $ticket->description);

        if (empty($terms)) {
            return collect();
        }

        $articles = KnowledgeArticle::query()
            ->published()
            ->where(function ($query) use ($terms) {
                foreach ($terms as $term) {
                    $query->orWhere('title', 'LIKE', "%{$term}%")
                        ->orWhere('content', 'LIKE', "%{$term}%")
                        ->orWhereJsonContains('keywords', $term);
                }
            })
            ->limit($limit * 2)
            ->get();

        return $articles->map(function ($article) use ($terms, $ticket) {
            $matchedTerms = array_filter($terms, function ($term) use ($article) {
                return str_contains(strtolower($article->title . $article->content), strtolower($term));
            });

            return [
                'article' => $article,
                'score' => $this->calculateRelevanceScore($article, $ticket, count($matchedTerms)),
                'match_type' => KnowledgeSuggestionMatchType::Keyword->value,
                'matched_terms' => array_values($matchedTerms),
            ];
        });
    }

    protected function findArticlesByCategories(Ticket $ticket, int $limit): \Illuminate\Support\Collection
    {
        $categoryIds = $ticket->categories->pluck('id');

        $articles = KnowledgeArticle::query()
            ->published()
            ->whereHas('sections', function ($query) use ($categoryIds) {
                $query->whereHas('categories', function ($q) use ($categoryIds) {
                    $q->whereIn('id', $categoryIds);
                });
            })
            ->limit($limit)
            ->get();

        return $articles->map(function ($article) use ($ticket) {
            return [
                'article' => $article,
                'score' => $this->calculateRelevanceScore($article, $ticket, 1),
                'match_type' => KnowledgeSuggestionMatchType::Category->value,
                'matched_terms' => null,
            ];
        });
    }

    protected function findArticlesBySimilarTickets(Ticket $ticket, int $limit): \Illuminate\Support\Collection
    {
        // Find resolved tickets with similar content
        $keywords = $this->extractKeywords($ticket->subject);

        $similarTickets = Ticket::query()
            ->where('id', '!=', $ticket->id)
            ->where('status', TicketStatus::Resolved)
            ->whereHas('rating', function ($query) {
                $query->where('rating', '>=', 4);
            })
            ->when(!empty($keywords), function ($query) use ($keywords) {
                $query->where(function ($q) use ($keywords) {
                    foreach ($keywords as $keyword) {
                        $q->orWhere('subject', 'LIKE', "%{$keyword}%")
                          ->orWhere('description', 'LIKE', "%{$keyword}%");
                    }
                });
            })
            ->limit(10)
            ->pluck('id');

        if ($similarTickets->isEmpty()) {
            return collect();
        }

        // Find articles that helped resolve similar tickets
        $articles = KnowledgeArticle::query()
            ->published()
            ->whereHas('tickets', function ($query) use ($similarTickets) {
                $query->whereIn('ticket_id', $similarTickets)
                    ->where('resolved_issue', true);
            })
            ->withCount(['tickets as success_count' => function ($query) use ($similarTickets) {
                $query->whereIn('ticket_id', $similarTickets)
                    ->where('resolved_issue', true);
            }])
            ->orderByDesc('success_count')
            ->limit($limit)
            ->get();

        return $articles->map(function ($article) use ($ticket) {
            return [
                'article' => $article,
                'score' => $this->calculateRelevanceScore($article, $ticket, $article->success_count ?? 1),
                'match_type' => KnowledgeSuggestionMatchType::SimilarTickets->value,
                'matched_terms' => null,
            ];
        });
    }

    protected function findArticlesByTags(Ticket $ticket, int $limit): \Illuminate\Support\Collection
    {
        $tagNames = $ticket->tags->pluck('name');

        $articles = KnowledgeArticle::query()
            ->published()
            ->where(function ($query) use ($tagNames) {
                foreach ($tagNames as $tag) {
                    $query->orWhereJsonContains('keywords', $tag);
                }
            })
            ->limit($limit)
            ->get();

        return $articles->map(function ($article) use ($ticket, $tagNames) {
            $matchedTags = array_filter($tagNames->toArray(), function ($tag) use ($article) {
                return in_array($tag, $article->keywords ?? []);
            });

            return [
                'article' => $article,
                'score' => $this->calculateRelevanceScore($article, $ticket, count($matchedTags)),
                'match_type' => KnowledgeSuggestionMatchType::Tags->value,
                'matched_terms' => array_values($matchedTags),
            ];
        });
    }

    public function generateFAQFromResolvedTickets(int $minRating = 4, int $minOccurrences = 3): Collection
    {
        $tickets = Ticket::query()
            ->where('status', TicketStatus::Resolved)
            ->whereHas('rating', function ($query) use ($minRating) {
                $query->where('rating', '>=', $minRating);
            })
            ->with(['comments', 'categories', 'tags'])
            ->get();

        // Group tickets by similarity
        $groupedTickets = $this->groupSimilarTickets($tickets);

        $faqs = collect();

        foreach ($groupedTickets as $group) {
            if ($group->count() < $minOccurrences) {
                continue;
            }

            $representativeTicket = $group->first();
            $solution = $this->extractSolution($representativeTicket);

            if (!$solution) {
                continue;
            }

            $faq = KnowledgeArticle::create([
                'title' => $this->generateFAQTitle($representativeTicket),
                'excerpt' => Str::limit($representativeTicket->description, 200),
                'content' => $this->generateFAQContent($representativeTicket, $solution),
                'status' => KnowledgeArticleStatus::Draft,
                'is_faq' => true,
                'is_public' => true,
                'keywords' => $this->extractKeywords($representativeTicket->subject . ' ' . $representativeTicket->description),
                'meta' => [
                    'source_tickets' => $group->pluck('id')->toArray(),
                    'average_rating' => $group->avg('rating.rating'),
                    'occurrences' => $group->count(),
                ],
            ]);

            // Link FAQ to source tickets
            foreach ($group as $ticket) {
                $faq->attachToTicket($ticket, ['resolved_issue' => true]);
            }

            // Add to appropriate sections based on categories
            if ($representativeTicket->categories->isNotEmpty()) {
                $sectionIds = KnowledgeSection::whereIn('name', $representativeTicket->categories->pluck('name'))->pluck('id');
                $faq->sections()->attach($sectionIds);
            }

            $faqs->push($faq);
        }

        return $faqs;
    }

    protected function groupSimilarTickets(Collection $tickets): Collection
    {
        $groups = collect();

        foreach ($tickets as $ticket) {
            $added = false;

            foreach ($groups as $group) {
                if ($this->areTicketsSimilar($ticket, $group->first())) {
                    $group->push($ticket);
                    $added = true;
                    break;
                }
            }

            if (!$added) {
                $groups->push(collect([$ticket]));
            }
        }

        return $groups;
    }

    protected function areTicketsSimilar(Ticket $ticket1, Ticket $ticket2): bool
    {
        $keywords1 = $this->extractKeywords($ticket1->subject . ' ' . $ticket1->description);
        $keywords2 = $this->extractKeywords($ticket2->subject . ' ' . $ticket2->description);

        $commonKeywords = array_intersect($keywords1, $keywords2);
        $similarity = count($commonKeywords) / max(count($keywords1), count($keywords2), 1);

        return $similarity >= 0.6;
    }

    protected function extractSolution(Ticket $ticket): ?string
    {
        $resolutionComment = $ticket->comments()
            ->where('is_internal', false)
            ->orderByDesc('created_at')
            ->first();

        return $resolutionComment?->content;
    }

    protected function generateFAQTitle(Ticket $ticket): string
    {
        $title = $ticket->subject;

        if (!Str::endsWith($title, '?')) {
            $title = "How to " . Str::lower($title);
        }

        return Str::limit($title, 100);
    }

    protected function generateFAQContent(Ticket $ticket, string $solution): string
    {
        $content = "## Problem\n\n";
        $content .= $ticket->description . "\n\n";
        $content .= "## Solution\n\n";
        $content .= $solution . "\n\n";

        if ($ticket->categories->isNotEmpty()) {
            $content .= "## Related Categories\n\n";
            $content .= $ticket->categories->pluck('name')->implode(', ') . "\n\n";
        }

        if ($ticket->tags->isNotEmpty()) {
            $content .= "## Tags\n\n";
            $content .= $ticket->tags->pluck('name')->implode(', ') . "\n";
        }

        return $content;
    }

    protected function extractKeywords(string $text): array
    {
        $text = strtolower($text);
        $text = preg_replace('/[^\w\s]/', ' ', $text);
        $words = explode(' ', $text);

        $stopWords = ['the', 'is', 'at', 'which', 'on', 'and', 'a', 'an', 'as', 'are', 'was', 'were', 'been', 'be', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should', 'may', 'might', 'must', 'can', 'could', 'to', 'of', 'in', 'for', 'with', 'from', 'up', 'about', 'into', 'through', 'during', 'before', 'after', 'above', 'below', 'between', 'under', 'again', 'further', 'then', 'once'];

        $keywords = array_filter($words, function ($word) use ($stopWords) {
            return strlen($word) > 2 && !in_array($word, $stopWords);
        });

        return array_values(array_unique($keywords));
    }

    protected function calculateRelevanceScore(KnowledgeArticle $article, Ticket $ticket, int $matchCount): float
    {
        $baseScore = min($matchCount * 20, 60);

        if ($article->is_featured) {
            $baseScore += 10;
        }

        if ($article->effectiveness_score !== null) {
            $baseScore += ($article->effectiveness_score / 100) * 20;
        }

        $viewBonus = min($article->view_count / 1000, 10);
        $baseScore += $viewBonus;

        return min($baseScore, 100);
    }

    public function trackArticleView(KnowledgeArticle $article, ?Ticket $ticket = null): void
    {
        $article->incrementViewCount();

        if ($ticket) {
            $suggestion = $ticket->knowledgeSuggestions()
                ->where('article_id', $article->id)
                ->first();

            $suggestion?->markAsViewed();
        }

        event(new KnowledgeArticleViewed($article, $ticket));
    }

    public function searchArticles(string $query, ?KnowledgeSection $section = null, bool $publicOnly = true): Builder
    {
        return KnowledgeArticle::query()
            ->when($publicOnly, fn($q) => $q->published())
            ->when($section, fn($q) => $q->inSection($section))
            ->where(function ($q) use ($query) {
                $q->where('title', 'LIKE', "%{$query}%")
                    ->orWhere('content', 'LIKE', "%{$query}%")
                    ->orWhere('excerpt', 'LIKE', "%{$query}%");
            })
            ->orderByDesc('effectiveness_score')
            ->orderByDesc('view_count');
    }
}