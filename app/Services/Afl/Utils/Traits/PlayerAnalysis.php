<?php

namespace App\Services\Afl\Utils\Traits;

use Illuminate\Support\Collection;

trait PlayerAnalysis
{
    /**
     * Get all players from all matches
     */
    public function getAllPlayers(): Collection
    {
        return $this->matches->flatMap(function ($match) {
            return collect($match['lineups']['lineup'])->flatMap(function ($lineup) use ($match) {
                return collect($lineup['player'])->map(function ($player) use ($lineup, $match) {
                    return array_merge($player, [
                        'team_type' => $lineup['@team'], // localteam or visitorteam
                        'team_name' => $lineup['@team'] === 'localteam'
                            ? $match['localteam']['@name']
                            : $match['visitorteam']['@name'],
                        'match_id' => $match['@id'],
                        'opponent' => $lineup['@team'] === 'localteam'
                            ? $match['visitorteam']['@name']
                            : $match['localteam']['@name'],
                        'venue' => $match['@venue'],
                        'date' => $match['@date']
                    ]);
                });
            });
        });
    }

    /**
     * Get top goal scorers
     */
    public function getTopGoalScorers(int $limit = 10): Collection
    {
        return $this->getAllPlayers()
            ->filter(function ($player) {
                return (int) $player['@goals'] > 0;
            })
            ->sortByDesc('@goals')
            ->take($limit)
            ->map(function ($player) {
                return [
                    'name' => $player['@name'],
                    'team' => $player['team_name'],
                    'goals' => (int) $player['@goals'],
                    'behinds' => (int) $player['@behinds'],
                    'points' => (int) $player['@points'],
                    'goal_accuracy' => $this->calculateGoalAccuracy($player),
                    'match_id' => $player['match_id']
                ];
            });
    }

    /**
     * Get players with most disposals
     */
    public function getTopDisposals(int $limit = 10): Collection
    {
        return $this->getAllPlayers()
            ->sortByDesc('@disposals')
            ->take($limit)
            ->map(function ($player) {
                return [
                    'name' => $player['@name'],
                    'team' => $player['team_name'],
                    'disposals' => (int) $player['@disposals'],
                    'kicks' => (int) $player['@kicks'],
                    'handballs' => (int) $player['@handballs'],
                    'disposal_efficiency' => $this->calculateDisposalEfficiency($player),
                    'match_id' => $player['match_id']
                ];
            });
    }

    /**
     * Get players with most tackles
     */
    public function getTopTacklers(int $limit = 10): Collection
    {
        return $this->getAllPlayers()
            ->sortByDesc('@tackles')
            ->take($limit)
            ->map(function ($player) {
                return [
                    'name' => $player['@name'],
                    'team' => $player['team_name'],
                    'tackles' => (int) $player['@tackles'],
                    'disposals' => (int) $player['@disposals'],
                    'contested_possessions' => (int) $player['@contested_possessions'],
                    'match_id' => $player['match_id']
                ];
            });
    }

    /**
     * Get players with best kick efficiency
     */
    public function getBestKickEfficiency(int $minKicks = 5, int $limit = 10): Collection
    {
        return $this->getAllPlayers()
            ->filter(function ($player) use ($minKicks) {
                return (int) $player['@kicks'] >= $minKicks;
            })
            ->map(function ($player) {
                return array_merge($player, [
                    'kick_efficiency' => $this->calculateKickEfficiency($player)
                ]);
            })
            ->sortByDesc('kick_efficiency')
            ->take($limit)
            ->map(function ($player) {
                return [
                    'name' => $player['@name'],
                    'team' => $player['team_name'],
                    'kick_efficiency' => round($player['kick_efficiency'], 2),
                    'effective_kicks' => (int) $player['@effective_kicks'],
                    'total_kicks' => (int) $player['@kicks'],
                    'disposals' => (int) $player['@disposals'],
                    'match_id' => $player['match_id']
                ];
            });
    }

    /**
     * Get best overall performers (custom performance score)
     */
    public function getBestPerformers(int $limit = 10): Collection
    {
        return $this->getAllPlayers()
            ->map(function ($player) {
                $performanceScore = $this->calculatePerformanceScore($player);

                return array_merge($player, [
                    'performance_score' => $performanceScore
                ]);
            })
            ->sortByDesc('performance_score')
            ->take($limit)
            ->map(function ($player) {
                return [
                    'name' => $player['@name'],
                    'team' => $player['team_name'],
                    'performance_score' => (int) $player['performance_score'],
                    'disposals' => (int) $player['@disposals'],
                    'goals' => (int) $player['@goals'],
                    'tackles' => (int) $player['@tackles'],
                    'marks' => (int) $player['@marks'],
                    'match_id' => $player['match_id']
                ];
            });
    }

    /**
     * Get players by team
     */
    public function getPlayersByTeam(string $teamName): Collection
    {
        return $this->getAllPlayers()
            ->where('team_name', $teamName);
    }

    /**
     * Find player statistics
     */
    public function findPlayer(string $playerName): Collection
    {
        return $this->getAllPlayers()
            ->filter(function ($player) use ($playerName) {
                return stripos($player['@name'], $playerName) !== false;
            });
    }

    /**
     * Calculate goal accuracy percentage
     */
    protected function calculateGoalAccuracy(array $player): float
    {
        $goals = (int) $player['@goals'];
        $behinds = (int) $player['@behinds'];
        $totalShots = $goals + $behinds;

        return $totalShots > 0 ? round(($goals / $totalShots) * 100, 2) : 0;
    }

    /**
     * Calculate kick efficiency percentage
     */
    protected function calculateKickEfficiency(array $player): float
    {
        $kicks = (int) $player['@kicks'];
        $effective = (int) $player['@effective_kicks'];

        return $kicks > 0 ? round(($effective / $kicks) * 100, 2) : 0;
    }

    /**
     * Calculate disposal efficiency percentage
     */
    protected function calculateDisposalEfficiency(array $player): float
    {
        $disposals = (int) $player['@disposals'];
        $errors = (int) ($player['@errors'] ?? 0);
        $effective = $disposals - $errors;

        return $disposals > 0 ? round(($effective / $disposals) * 100, 2) : 0;
    }

    /**
     * Calculate custom performance score
     */
    protected function calculatePerformanceScore(array $player): int
    {
        return ((int) $player['@disposals'] * 1) +
            ((int) $player['@goals'] * 6) +
            ((int) $player['@tackles'] * 2) +
            ((int) $player['@marks'] * 1) +
            ((int) $player['@contested_possessions'] * 1.5) +
            ((int) $player['@inside_fifty'] * 0.5);
    }
}
