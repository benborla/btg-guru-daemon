<?php

namespace App\Services\Afl\Utils\Traits;

use Illuminate\Support\Collection;
use App\Models\AflApiResponse;

trait TeamAnalysis
{
    /**
     * Get team performance statistics
     */
    public function getTeamPerformance(): Collection
    {
        $teamStats = $this->matches->flatMap(function ($match) {
            $homeScore = (int) $match['localteam']['@score'];
            $awayScore = (int) $match['visitorteam']['@score'];

            return [
                [
                    'team' => $match['localteam']['@name'],
                    'is_home' => true,
                    'result' => $homeScore > $awayScore ? 'win' : ($homeScore === $awayScore ? 'draw' : 'loss'),
                    'score_for' => $homeScore,
                    'score_against' => $awayScore,
                    'goals_for' => (int) $match['localteam']['@goals'],
                    'behinds_for' => (int) $match['localteam']['@behinds'],
                    'goals_against' => (int) $match['visitorteam']['@goals'],
                    'behinds_against' => (int) $match['visitorteam']['@behinds'],
                    'margin' => $homeScore - $awayScore,
                    'opponent' => $match['visitorteam']['@name'],
                    'venue' => $match['@venue'],
                    'date' => $match['@date'],
                    'match_id' => $match['@id']
                ],
                [
                    'team' => $match['visitorteam']['@name'],
                    'is_home' => false,
                    'result' => $awayScore > $homeScore ? 'win' : ($awayScore === $homeScore ? 'draw' : 'loss'),
                    'score_for' => $awayScore,
                    'score_against' => $homeScore,
                    'goals_for' => (int) $match['visitorteam']['@goals'],
                    'behinds_for' => (int) $match['visitorteam']['@behinds'],
                    'goals_against' => (int) $match['localteam']['@goals'],
                    'behinds_against' => (int) $match['localteam']['@behinds'],
                    'margin' => $awayScore - $homeScore,
                    'opponent' => $match['localteam']['@name'],
                    'venue' => $match['@venue'],
                    'date' => $match['@date'],
                    'match_id' => $match['@id']
                ]
            ];
        });

        return collect($teamStats)->groupBy('team')->map(function ($teamMatches, $teamName) {
            $wins = $teamMatches->where('result', 'win')->count();
            $losses = $teamMatches->where('result', 'loss')->count();
            $draws = $teamMatches->where('result', 'draw')->count();
            $totalMatches = $teamMatches->count();

            return [
                'team' => $teamName,
                'matches_played' => $totalMatches,
                'wins' => $wins,
                'losses' => $losses,
                'draws' => $draws,
                'win_percentage' => $totalMatches > 0 ? round(($wins / $totalMatches) * 100, 2) : 0,
                'avg_score_for' => round($teamMatches->avg('score_for'), 2),
                'avg_score_against' => round($teamMatches->avg('score_against'), 2),
                'avg_margin' => round($teamMatches->avg('margin'), 2),
                'total_points_for' => $teamMatches->sum('score_for'),
                'total_points_against' => $teamMatches->sum('score_against'),
                'home_wins' => $teamMatches->where('is_home', true)->where('result', 'win')->count(),
                'away_wins' => $teamMatches->where('is_home', false)->where('result', 'win')->count(),
                'biggest_win' => $teamMatches->where('result', 'win')->max('margin') ?? 0,
                'biggest_loss' => abs($teamMatches->where('result', 'loss')->min('margin') ?? 0),
                'goal_accuracy' => $this->calculateTeamGoalAccuracy($teamMatches),
                'matches' => $teamMatches->values()
            ];
        });
    }

    /**
     * Get team ladder/standings
     */
    public function getTeamLadder(): Collection
    {
        return $this->getTeamPerformance()
            ->sortByDesc('win_percentage')
            ->values()
            ->map(function ($team, $index) {
                return array_merge($team, [
                    'position' => $index + 1,
                    'points' => ($team['wins'] * 4) + ($team['draws'] * 2) // Standard AFL points system
                ]);
            });
    }

    /**
     * Get best attacking teams
     */
    public function getBestAttackingTeams(): Collection
    {
        return $this->getTeamPerformance()
            ->sortByDesc('avg_score_for')
            ->map(function ($team) {
                return [
                    'team' => $team['team'],
                    'avg_score_for' => $team['avg_score_for'],
                    'total_points_for' => $team['total_points_for'],
                    'matches_played' => $team['matches_played'],
                    'goal_accuracy' => $team['goal_accuracy']
                ];
            });
    }

    /**
     * Get best defensive teams
     */
    public function getBestDefensiveTeams(): Collection
    {
        return $this->getTeamPerformance()
            ->sortBy('avg_score_against')
            ->map(function ($team) {
                return [
                    'team' => $team['team'],
                    'avg_score_against' => $team['avg_score_against'],
                    'total_points_against' => $team['total_points_against'],
                    'matches_played' => $team['matches_played']
                ];
            });
    }

    /**
     * Get home vs away performance
     */
    public function getHomeAwayPerformance(): Collection
    {
        return $this->getTeamPerformance()->map(function ($team) {
            $homeMatches = collect($team['matches'])->where('is_home', true);
            $awayMatches = collect($team['matches'])->where('is_home', false);

            return [
                'team' => $team['team'],
                'home_record' => [
                    'played' => $homeMatches->count(),
                    'wins' => $homeMatches->where('result', 'win')->count(),
                    'losses' => $homeMatches->where('result', 'loss')->count(),
                    'win_percentage' => $homeMatches->count() > 0
                        ? round(($homeMatches->where('result', 'win')->count() / $homeMatches->count()) * 100, 2)
                        : 0,
                    'avg_score' => round($homeMatches->avg('score_for'), 2)
                ],
                'away_record' => [
                    'played' => $awayMatches->count(),
                    'wins' => $awayMatches->where('result', 'win')->count(),
                    'losses' => $awayMatches->where('result', 'loss')->count(),
                    'win_percentage' => $awayMatches->count() > 0
                        ? round(($awayMatches->where('result', 'win')->count() / $awayMatches->count()) * 100, 2)
                        : 0,
                    'avg_score' => round($awayMatches->avg('score_for'), 2)
                ]
            ];
        });
    }

    /**
     * Get team head-to-head records
     */
    public function getHeadToHeadRecord(string $team1, string $team2): array
    {
        $h2hMatches = $this->matches->filter(function ($match) use ($team1, $team2) {
            $teams = [$match['localteam']['@name'], $match['visitorteam']['@name']];
            return in_array($team1, $teams) && in_array($team2, $teams);
        });

        if ($h2hMatches->isEmpty()) {
            return [
                'team1' => $team1,
                'team2' => $team2,
                'matches_played' => 0,
                'team1_wins' => 0,
                'team2_wins' => 0,
                'draws' => 0,
                'team1_avg_score' => 0,
                'team2_avg_score' => 0,
                'message' => 'No matches found between these teams',
                'matches' => []
            ];
        }

        $team1Wins = 0;
        $team2Wins = 0;
        $draws = 0;
        $team1TotalScore = 0;
        $team2TotalScore = 0;

        foreach ($h2hMatches as $match) {
            $homeTeam = $match['localteam']['@name'];
            $awayTeam = $match['visitorteam']['@name'];
            $homeScore = (int) $match['localteam']['@score'];
            $awayScore = (int) $match['visitorteam']['@score'];

            if ($homeTeam === $team1) {
                $team1TotalScore += $homeScore;
                $team2TotalScore += $awayScore;
                if ($homeScore > $awayScore) $team1Wins++;
                elseif ($awayScore > $homeScore) $team2Wins++;
                else $draws++;
            } else {
                $team1TotalScore += $awayScore;
                $team2TotalScore += $homeScore;
                if ($awayScore > $homeScore) $team1Wins++;
                elseif ($homeScore > $awayScore) $team2Wins++;
                else $draws++;
            }
        }

        return [
            'team1' => $team1,
            'team2' => $team2,
            'matches_played' => $h2hMatches->count(),
            'team1_wins' => $team1Wins,
            'team2_wins' => $team2Wins,
            'draws' => $draws,
            'team1_avg_score' => round($team1TotalScore / $h2hMatches->count(), 2),
            'team2_avg_score' => round($team2TotalScore / $h2hMatches->count(), 2),
            'team1_total_score' => $team1TotalScore,
            'team2_total_score' => $team2TotalScore,
            'highest_scoring_match' => $h2hMatches->map(function ($match) {
                return (int)$match['localteam']['@score'] + (int)$match['visitorteam']['@score'];
            })->max(),
            'matches' => $h2hMatches->values()
        ];
    }

    /**
     * Get team performance by venue
     */
    public function getTeamPerformanceByVenue(string $teamName): Collection
    {
        $teamMatches = $this->getTeamPerformance()
            ->where('team', $teamName)
            ->first()['matches'] ?? collect();

        return collect($teamMatches)->groupBy('venue')->map(function ($venueMatches, $venue) use ($teamName) {
            return [
                'venue' => $venue,
                'team' => $teamName,
                'matches_played' => $venueMatches->count(),
                'wins' => $venueMatches->where('result', 'win')->count(),
                'losses' => $venueMatches->where('result', 'loss')->count(),
                'draws' => $venueMatches->where('result', 'draw')->count(),
                'win_percentage' => $venueMatches->count() > 0
                    ? round(($venueMatches->where('result', 'win')->count() / $venueMatches->count()) * 100, 2)
                    : 0,
                'avg_score_for' => round($venueMatches->avg('score_for'), 2),
                'avg_score_against' => round($venueMatches->avg('score_against'), 2),
                'avg_margin' => round($venueMatches->avg('margin'), 2),
                'best_performance' => $venueMatches->max('score_for'),
                'worst_performance' => $venueMatches->min('score_for')
            ];
        });
    }

    /**
     * Get all team names
     */
    public function getAllTeamNames(): Collection
    {
        return $this->matches->flatMap(function ($match) {
            return [
                $match['localteam']['@name'],
                $match['visitorteam']['@name']
            ];
        })->unique()->values();
    }

    /**
     * Get all head-to-head records between teams
     */
    public function getAllHeadToHeadRecords(): Collection
    {
        $teams = $this->getAllTeamNames();
        $h2hRecords = collect();
        $processedPairs = collect();

        foreach ($teams as $team1) {
            foreach ($teams as $team2) {
                if ($team1 === $team2) continue;

                // Create a sorted pair to avoid duplicates (A vs B same as B vs A)
                $pair = collect([$team1, $team2])->sort()->values()->implode('|');

                if ($processedPairs->contains($pair)) continue;

                $h2h = $this->getHeadToHeadRecord($team1, $team2);

                if ($h2h['matches_played'] > 0) {
                    $h2hRecords->push($h2h);
                }

                $processedPairs->push($pair);
            }
        }

        return $h2hRecords;
    }

    /**
     * Get head-to-head matrix (all teams vs all teams)
     */
    public function getHeadToHeadMatrix(): Collection
    {
        $teams = $this->getAllTeamNames();
        $matrix = collect();

        foreach ($teams as $team1) {
            $teamRow = [
                'team' => $team1,
                'opponents' => collect()
            ];

            foreach ($teams as $team2) {
                if ($team1 === $team2) {
                    $teamRow['opponents']->put($team2, [
                        'matches_played' => 0,
                        'wins' => 0,
                        'losses' => 0,
                        'draws' => 0,
                        'avg_score_for' => 0,
                        'avg_score_against' => 0
                    ]);
                    continue;
                }

                $h2h = $this->getHeadToHeadRecord($team1, $team2);

                $teamRow['opponents']->put($team2, [
                    'matches_played' => $h2h['matches_played'],
                    'wins' => $h2h['team1_wins'],
                    'losses' => $h2h['team2_wins'],
                    'draws' => $h2h['draws'],
                    'avg_score_for' => $h2h['team1_avg_score'] ?? 0,
                    'avg_score_against' => $h2h['team2_avg_score'] ?? 0
                ]);
            }

            $matrix->push($teamRow);
        }

        return $matrix;
    }

    /**
     * Get team form (recent performance)
     */
    public function getTeamForm(string $teamName, int $lastNGames = 5): array
    {
        $teamPerformance = $this->getTeamPerformance()->where('team', $teamName)->first();

        if (!$teamPerformance) {
            return [
                'team' => $teamName,
                'message' => 'Team not found',
                'form' => []
            ];
        }

        $recentMatches = collect($teamPerformance['matches'])
            ->sortByDesc('date')
            ->take($lastNGames);

        $form = $recentMatches->map(function ($match) {
            return [
                'result' => $match['result'],
                'score_for' => $match['score_for'],
                'score_against' => $match['score_against'],
                'margin' => $match['margin'],
                'opponent' => $match['opponent'],
                'venue' => $match['venue'],
                'date' => $match['date'],
                'is_home' => $match['is_home']
            ];
        });

        return [
            'team' => $teamName,
            'games_analyzed' => $recentMatches->count(),
            'wins' => $recentMatches->where('result', 'win')->count(),
            'losses' => $recentMatches->where('result', 'loss')->count(),
            'draws' => $recentMatches->where('result', 'draw')->count(),
            'win_percentage' => $recentMatches->count() > 0
                ? round(($recentMatches->where('result', 'win')->count() / $recentMatches->count()) * 100, 2)
                : 0,
            'avg_score_for' => round($recentMatches->avg('score_for'), 2),
            'avg_score_against' => round($recentMatches->avg('score_against'), 2),
            'avg_margin' => round($recentMatches->avg('margin'), 2),
            'form_string' => $recentMatches->pluck('result')->map(function ($result) {
                return strtoupper(substr($result, 0, 1));
            })->implode(''),
            'form' => $form->values()
        ];
    }

    /**
     * Get team streaks (winning/losing streaks)
     */
    public function getTeamStreaks(): Collection
    {
        return $this->getTeamPerformance()->map(function ($team) {
            $matches = collect($team['matches'])->sortBy('date');

            $currentStreak = 0;
            $currentStreakType = null;
            $longestWinStreak = 0;
            $longestLossStreak = 0;
            $tempStreak = 0;
            $tempType = null;

            foreach ($matches as $match) {
                if ($tempType === null) {
                    $tempType = $match['result'];
                    $tempStreak = 1;
                } elseif ($tempType === $match['result']) {
                    $tempStreak++;
                } else {
                    // Streak broken, record if it's a record
                    if ($tempType === 'win' && $tempStreak > $longestWinStreak) {
                        $longestWinStreak = $tempStreak;
                    } elseif ($tempType === 'loss' && $tempStreak > $longestLossStreak) {
                        $longestLossStreak = $tempStreak;
                    }

                    $tempType = $match['result'];
                    $tempStreak = 1;
                }
            }

            // Check final streak
            if ($tempType === 'win' && $tempStreak > $longestWinStreak) {
                $longestWinStreak = $tempStreak;
            } elseif ($tempType === 'loss' && $tempStreak > $longestLossStreak) {
                $longestLossStreak = $tempStreak;
            }

            // Current streak is the last streak
            $currentStreak = $tempStreak;
            $currentStreakType = $tempType;

            return [
                'team' => $team['team'],
                'current_streak' => $currentStreak,
                'current_streak_type' => $currentStreakType,
                'longest_win_streak' => $longestWinStreak,
                'longest_loss_streak' => $longestLossStreak,
                'current_streak_description' => $currentStreak . ' ' . ($currentStreakType === 'win' ? 'wins' : ($currentStreakType === 'loss' ? 'losses' : 'draws'))
            ];
        });
    }

    /**
     * Calculate team goal accuracy
     */
    protected function calculateTeamGoalAccuracy(Collection $teamMatches): float
    {
        $totalGoals = $teamMatches->sum('goals_for');
        $totalBehinds = $teamMatches->sum('behinds_for');
        $totalShots = $totalGoals + $totalBehinds;

        return $totalShots > 0 ? round(($totalGoals / $totalShots) * 100, 2) : 0;
    }

    /**
     * Get team statistics for a specific team
     */
    public function getTeamStats(string $teamName): ?array
    {
        return $this->getTeamPerformance()->where('team', $teamName)->first();
    }

    /**
     * Get comprehensive team report
     */
    public function getTeamReport(string $teamName): array
    {
        $teamStats = $this->getTeamStats($teamName);

        if (!$teamStats) {
            return [
                'team' => $teamName,
                'message' => 'Team not found'
            ];
        }

        return [
            'team' => $teamName,
            'overview' => $teamStats,
            'form' => $this->getTeamForm($teamName, 5),
            'streaks' => $this->getTeamStreaks()->where('team', $teamName)->first(),
            'home_away' => $this->getHomeAwayPerformance()->where('team', $teamName)->first(),
            'venue_performance' => $this->getTeamPerformanceByVenue($teamName),
            'ladder_position' => $this->getTeamLadder()->search(function ($team) use ($teamName) {
                return $team['team'] === $teamName;
            }) + 1
        ];
    }

    /**
     * Compare two teams
     */
    public function compareTeams(string $team1, string $team2): array
    {
        $team1Stats = $this->getTeamStats($team1);
        $team2Stats = $this->getTeamStats($team2);
        $h2h = $this->getHeadToHeadRecord($team1, $team2);

        if (!$team1Stats || !$team2Stats) {
            return [
                'message' => 'One or both teams not found'
            ];
        }

        return [
            'comparison' => [
                'team1' => $team1Stats,
                'team2' => $team2Stats
            ],
            'head_to_head' => $h2h,
            'advantage' => [
                'better_attack' => $team1Stats['avg_score_for'] > $team2Stats['avg_score_for'] ? $team1 : $team2,
                'better_defense' => $team1Stats['avg_score_against'] < $team2Stats['avg_score_against'] ? $team1 : $team2,
                'better_win_rate' => $team1Stats['win_percentage'] > $team2Stats['win_percentage'] ? $team1 : $team2,
                'better_margin' => $team1Stats['avg_margin'] > $team2Stats['avg_margin'] ? $team1 : $team2
            ]
        ];
    }

    public function getTeamStandings(): array
    {
        $standings = AflApiResponse::getLatestStandings()->first();
        $data = collect($standings->response['standings']['category']['team']);

        foreach ($data as $team) {
            $points_for = (int) $team['@points_for'];
            $points_against = (int) $team['@points_against'];

            // Calculate percentage, avoid division by zero
            $percentage = ($points_against > 0) ? ($points_for / $points_against) * 100 : 0;
            $teams[] = [
                'name' => (string) $team['@name'],
                'id' => (string) $team['@id'],
                'position' => (int) $team['@pos'],
                'games_played' => (int) $team['@gp'],
                'wins' => (int) $team['@w'],
                'draws' => (int) $team['@d'],
                'losses' => (int) $team['@l'],
                'points_for' => (int) $team['@points_for'],
                'points_against' => (int) $team['@points_against'],
                'points' => (int) $team['@pts'],
                'recent_form' => (string) $team['@recent_form'],
                'description' => (string) $team['description']['@value'],
                'percentage' => round($percentage, 2),
            ];
        }

        return $teams;
    }
}
