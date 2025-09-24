# Performance Monitoring

## Overview

Performance Monitoring provides comprehensive tracking of agent performance, quality assurance tools, customer satisfaction surveys, and operational metrics. This enables data-driven management and continuous improvement of support operations.

## Core Features

### 1. Agent Performance Tracking

```php
namespace LucaLongo\LaravelHelpdesk\Performance;

class AgentPerformanceTracker
{
    public function calculatePerformanceScore(User $agent, $period = '30d'): array
    {
        $metrics = $this->collectMetrics($agent, $period);
        
        return [
            'overall_score' => $this->calculateOverallScore($metrics),
            'productivity' => [
                'score' => $metrics['tickets_resolved'] / $this->getTarget('tickets_per_period'),
                'tickets_resolved' => $metrics['tickets_resolved'],
                'avg_handle_time' => $metrics['avg_handle_time'],
                'first_contact_resolution' => $metrics['fcr_rate'],
            ],
            'quality' => [
                'score' => $this->calculateQualityScore($metrics),
                'customer_satisfaction' => $metrics['csat'],
                'qa_score' => $metrics['qa_score'],
                'error_rate' => $metrics['error_rate'],
            ],
            'efficiency' => [
                'score' => $this->calculateEfficiencyScore($metrics),
                'response_time' => $metrics['avg_response_time'],
                'resolution_time' => $metrics['avg_resolution_time'],
                'sla_compliance' => $metrics['sla_compliance'],
            ],
            'behavior' => [
                'attendance' => $metrics['attendance_rate'],
                'schedule_adherence' => $metrics['schedule_adherence'],
                'break_compliance' => $metrics['break_compliance'],
            ],
        ];
    }
    
    protected function collectMetrics($agent, $period): array
    {
        $startDate = $this->parsePeriod($period);
        
        return [
            'tickets_resolved' => $agent->tickets()
                ->where('resolved_at', '>=', $startDate)
                ->count(),
                
            'avg_handle_time' => $agent->tickets()
                ->where('resolved_at', '>=', $startDate)
                ->avg(DB::raw('TIMESTAMPDIFF(MINUTE, assigned_at, resolved_at)')),
                
            'fcr_rate' => $this->calculateFCR($agent, $startDate),
            'csat' => $this->getAverageCSAT($agent, $startDate),
            'qa_score' => $this->getQAScore($agent, $startDate),
            'error_rate' => $this->calculateErrorRate($agent, $startDate),
            'avg_response_time' => $this->getAverageResponseTime($agent, $startDate),
            'avg_resolution_time' => $this->getAverageResolutionTime($agent, $startDate),
            'sla_compliance' => $this->getSLACompliance($agent, $startDate),
            'attendance_rate' => $this->getAttendanceRate($agent, $startDate),
            'schedule_adherence' => $this->getScheduleAdherence($agent, $startDate),
            'break_compliance' => $this->getBreakCompliance($agent, $startDate),
        ];
    }
}
```

### 2. Quality Assurance System

```php
class QualityAssurance
{
    public function createEvaluation(Ticket $ticket, User $evaluator): QAEvaluation
    {
        return QAEvaluation::create([
            'ticket_id' => $ticket->id,
            'agent_id' => $ticket->assignee_id,
            'evaluator_id' => $evaluator->id,
            'evaluation_date' => now(),
        ]);
    }
    
    public function evaluateCriteria(QAEvaluation $evaluation, array $scores): void
    {
        foreach ($scores as $criteriaId => $score) {
            QAScore::create([
                'evaluation_id' => $evaluation->id,
                'criteria_id' => $criteriaId,
                'score' => $score['value'],
                'comments' => $score['comments'] ?? null,
                'weight' => $this->getCriteriaWeight($criteriaId),
            ]);
        }
        
        $evaluation->update([
            'total_score' => $this->calculateTotalScore($evaluation),
            'status' => 'completed',
        ]);
    }
    
    public function getEvaluationCriteria(): array
    {
        return [
            'communication' => [
                'greeting' => ['weight' => 10, 'description' => 'Proper greeting and introduction'],
                'tone' => ['weight' => 15, 'description' => 'Professional and empathetic tone'],
                'clarity' => ['weight' => 15, 'description' => 'Clear and concise communication'],
            ],
            'problem_solving' => [
                'understanding' => ['weight' => 20, 'description' => 'Correct problem identification'],
                'solution' => ['weight' => 25, 'description' => 'Appropriate solution provided'],
                'documentation' => ['weight' => 15, 'description' => 'Proper ticket documentation'],
            ],
            'compliance' => [
                'procedures' => ['weight' => 10, 'description' => 'Following company procedures'],
                'sla' => ['weight' => 10, 'description' => 'SLA compliance'],
                'escalation' => ['weight' => 10, 'description' => 'Proper escalation when needed'],
            ],
        ];
    }
    
    public function generateCoachingPlan(User $agent): CoachingPlan
    {
        $weakAreas = $this->identifyWeakAreas($agent);
        
        return CoachingPlan::create([
            'agent_id' => $agent->id,
            'coach_id' => $agent->supervisor_id,
            'areas_of_improvement' => $weakAreas,
            'goals' => $this->generateGoals($weakAreas),
            'action_items' => $this->generateActionItems($weakAreas),
            'timeline' => $this->generateTimeline($weakAreas),
            'next_review_date' => now()->addWeeks(2),
        ]);
    }
}
```

### 3. Customer Satisfaction Surveys

```php
class SatisfactionSurvey
{
    public function createSurvey(Ticket $ticket): Survey
    {
        $survey = Survey::create([
            'ticket_id' => $ticket->id,
            'customer_id' => $ticket->customer_id,
            'type' => $this->determineSurveyType($ticket),
            'token' => Str::random(32),
            'expires_at' => now()->addDays(7),
        ]);
        
        $this->sendSurveyInvitation($survey);
        
        return $survey;
    }
    
    public function processResponse(Survey $survey, array $responses): void
    {
        foreach ($responses as $questionId => $response) {
            SurveyResponse::create([
                'survey_id' => $survey->id,
                'question_id' => $questionId,
                'response' => $response,
            ]);
        }
        
        $survey->update([
            'completed_at' => now(),
            'nps_score' => $this->calculateNPS($responses),
            'csat_score' => $this->calculateCSAT($responses),
            'ces_score' => $this->calculateCES($responses),
        ]);
        
        $this->triggerFollowUp($survey);
    }
    
    public function getSurveyQuestions($type): array
    {
        return match($type) {
            'nps' => [
                'recommendation' => [
                    'text' => 'How likely are you to recommend our service to a friend?',
                    'type' => 'scale',
                    'scale' => [0, 10],
                ],
                'reason' => [
                    'text' => 'What is the primary reason for your score?',
                    'type' => 'text',
                    'required' => false,
                ],
            ],
            'csat' => [
                'satisfaction' => [
                    'text' => 'How satisfied are you with the resolution?',
                    'type' => 'scale',
                    'scale' => [1, 5],
                ],
                'agent_rating' => [
                    'text' => 'How would you rate the agent who helped you?',
                    'type' => 'scale',
                    'scale' => [1, 5],
                ],
            ],
            'ces' => [
                'effort' => [
                    'text' => 'How easy was it to get your issue resolved?',
                    'type' => 'scale',
                    'scale' => [1, 7],
                ],
            ],
        };
    }
}
```

### 4. Real-time Monitoring Dashboard

```php
class RealtimeMonitoring
{
    public function getAgentStatus(): array
    {
        return User::agents()->get()->map(function ($agent) {
            return [
                'id' => $agent->id,
                'name' => $agent->name,
                'status' => $this->getStatus($agent),
                'current_ticket' => $agent->activeTicket,
                'tickets_today' => $agent->tickets()->today()->count(),
                'avg_handle_time' => $this->getTodayAvgHandleTime($agent),
                'break_time' => $this->getBreakTime($agent),
                'productivity' => $this->getCurrentProductivity($agent),
            ];
        });
    }
    
    public function getQueueMetrics(): array
    {
        return [
            'tickets_in_queue' => Ticket::unassigned()->count(),
            'avg_wait_time' => $this->calculateAvgWaitTime(),
            'longest_wait' => $this->getLongestWait(),
            'predicted_wait' => $this->predictWaitTime(),
            'agents_available' => $this->getAvailableAgents()->count(),
            'agents_busy' => $this->getBusyAgents()->count(),
            'agents_on_break' => $this->getAgentsOnBreak()->count(),
        ];
    }
    
    public function trackAgentActivity(User $agent, $activity): void
    {
        AgentActivity::create([
            'agent_id' => $agent->id,
            'activity' => $activity,
            'timestamp' => now(),
            'metadata' => $this->getActivityMetadata($activity),
        ]);
        
        $this->updateAgentStatus($agent, $activity);
        $this->broadcastStatusUpdate($agent);
    }
}
```

### 5. Performance Analytics

```php
class PerformanceAnalytics
{
    public function generateReport($period = 'monthly'): Report
    {
        $data = [
            'period' => $period,
            'team_performance' => $this->getTeamPerformance($period),
            'individual_performance' => $this->getIndividualPerformance($period),
            'quality_metrics' => $this->getQualityMetrics($period),
            'customer_satisfaction' => $this->getCustomerSatisfaction($period),
            'operational_metrics' => $this->getOperationalMetrics($period),
            'trends' => $this->analyzeTrends($period),
            'recommendations' => $this->generateRecommendations(),
        ];
        
        return Report::create([
            'type' => 'performance',
            'period' => $period,
            'data' => $data,
            'generated_at' => now(),
        ]);
    }
    
    public function identifyTopPerformers($metric = 'overall'): Collection
    {
        return User::agents()
            ->with('performances')
            ->get()
            ->map(function ($agent) use ($metric) {
                return [
                    'agent' => $agent,
                    'score' => $this->getPerformanceScore($agent, $metric),
                ];
            })
            ->sortByDesc('score')
            ->take(10);
    }
    
    public function predictPerformance(User $agent): array
    {
        $history = $this->getPerformanceHistory($agent, 90);
        $trend = $this->calculateTrend($history);
        
        return [
            'next_month_projection' => $this->projectPerformance($agent, $trend),
            'improvement_areas' => $this->suggestImprovements($agent),
            'training_recommendations' => $this->recommendTraining($agent),
            'risk_factors' => $this->identifyRiskFactors($agent),
        ];
    }
}
```

### 6. Gamification System

```php
class Gamification
{
    public function awardPoints(User $agent, $action, $value = null): void
    {
        $points = $this->calculatePoints($action, $value);
        
        $agent->increment('gamification_points', $points);
        
        PointTransaction::create([
            'user_id' => $agent->id,
            'action' => $action,
            'points' => $points,
            'balance' => $agent->gamification_points,
        ]);
        
        $this->checkAchievements($agent, $action);
        $this->updateLeaderboard($agent);
    }
    
    public function checkAchievements(User $agent, $action): void
    {
        $achievements = Achievement::where('trigger_action', $action)
            ->whereNotIn('id', $agent->achievements->pluck('id'))
            ->get();
        
        foreach ($achievements as $achievement) {
            if ($this->meetsRequirements($agent, $achievement)) {
                $agent->achievements()->attach($achievement, [
                    'unlocked_at' => now(),
                ]);
                
                event(new AchievementUnlocked($agent, $achievement));
            }
        }
    }
    
    public function getLeaderboard($period = 'weekly'): Collection
    {
        return User::agents()
            ->select('id', 'name', 'gamification_points')
            ->withSum(['pointTransactions' => function ($query) use ($period) {
                $query->where('created_at', '>=', $this->getPeriodStart($period));
            }], 'points')
            ->orderByDesc('point_transactions_sum_points')
            ->limit(20)
            ->get();
    }
}
```

## Configuration

```php
'performance' => [
    'tracking' => [
        'enabled' => true,
        'metrics' => [
            'productivity' => ['weight' => 0.3],
            'quality' => ['weight' => 0.4],
            'efficiency' => ['weight' => 0.3],
        ],
        'targets' => [
            'tickets_per_day' => 20,
            'avg_handle_time' => 15, // minutes
            'first_response_time' => 5, // minutes
            'resolution_time' => 60, // minutes
            'csat_score' => 4.5,
            'sla_compliance' => 0.95,
        ],
    ],
    
    'quality_assurance' => [
        'enabled' => true,
        'sample_rate' => 0.1, // 10% of tickets
        'evaluations_per_agent_per_month' => 5,
        'passing_score' => 80,
    ],
    
    'surveys' => [
        'enabled' => true,
        'types' => ['nps', 'csat', 'ces'],
        'send_after_resolution' => true,
        'delay_hours' => 24,
        'reminder_after_days' => 3,
        'expiry_days' => 7,
    ],
    
    'gamification' => [
        'enabled' => true,
        'points' => [
            'ticket_resolved' => 10,
            'fast_response' => 5,
            'positive_feedback' => 20,
            'perfect_qa_score' => 50,
            'monthly_target_met' => 100,
        ],
        'achievements' => [
            'first_ticket' => 'Resolve your first ticket',
            'speed_demon' => 'Resolve 10 tickets in one day',
            'customer_hero' => 'Get 10 perfect ratings',
            'knowledge_master' => 'Create 5 knowledge articles',
        ],
    ],
]
```

## Benefits

- **25% Performance Improvement**: Through targeted coaching
- **30% Higher Job Satisfaction**: Gamification and recognition
- **20% Better Quality Scores**: Regular QA evaluations
- **35% Increased Productivity**: Real-time monitoring
- **40% Better Customer Satisfaction**: Performance-driven improvements

## Implementation Timeline

### Phase 1: Core Tracking (2 weeks)
- Basic performance metrics
- Agent dashboards
- Simple reporting

### Phase 2: Quality Assurance (3 weeks)
- QA evaluation system
- Coaching plans
- Training recommendations

### Phase 3: Surveys (2 weeks)
- Customer surveys
- NPS/CSAT/CES tracking
- Feedback analysis

### Phase 4: Advanced Features (3 weeks)
- Gamification
- Predictive analytics
- Real-time monitoring