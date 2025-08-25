<?php

namespace App\Repositories\Nfl;

use App\Dto\NflStandingsDto;
use Illuminate\Support\Facades\Cache;
use App\Models\NflGame;
use App\Repositories\Interfaces\NflScoresRepositoryInterface;
use App\Services\Nfl\NflApiService;
use Illuminate\Support\Collection;

class NflScoresRepository implements NflScoresRepositoryInterface
{
    protected $cacheKey = 'nfl_scores_season_';
    protected $cacheKeyStanding = 'nfl-standings_season_';

    public function __construct(
        private NflApiService $apiService,
        private NflGame $model
    ) {}

    /**
     * Get scores with database fallback
     */
    public function getScores(?string $week = null): Collection
    {
        try {
            // Try API/cache first
            $apiScores = $this->apiService->getScores($week);

            if ($apiScores->isNotEmpty()) {
                // Optionally store in database for historical data
                $this->storeScores($apiScores);
                return $apiScores;
            }
        } catch (\Exception $e) {
            Log::error('Failed to get scores from API', ['error' => $e->getMessage()]);
        }

        // Fallback to database
        return $this->getScoresFromDatabase($week);
    }

    private function storeScores(Collection $scores): void
    {
        foreach ($scores as $score) {
            $this->model->updateOrCreate(
                ['game_id' => $score['id']],
                [
                    'home_team' => $score['home_team'],
                    'away_team' => $score['away_team'],
                    'home_score' => $score['home_score'] ?? 0,
                    'away_score' => $score['away_score'] ?? 0,
                    'status' => $score['status'],
                    'game_date' => Carbon::parse($score['date']),
                    'updated_at' => now(),
                ]
            );
        }
    }

    private function getScoresFromDatabase(?string $week): Collection
    {
        $query = $this->model->query();

        if ($week) {
            $query->where('week', $week);
        }

        return $query->orderBy('game_date')->get();
    }

    public function getTeamsInfo() :Collection
    {
        $scores = Cache::get($this->cacheKey . date('Y'));

        if (empty($scores)) return collect([]);

        // map teamsInfo
        $scores = $scores->flatMap(function ($match) {
            $home = $match['hometeam'];
            $away = $match['awayteam'];
            return [
                $match['hometeam']['name'] => [
                    'name' => $home['name'],
                    'id' => $home['id'],
                    'image_name' => str_replace(' ', '_', $home['name'])
                ],
                $match['awayteam']['name'] => [
                    'name' => $away['name'],
                    'id' => $away['id'],
                    'image_name' => str_replace(' ', '_', $away['name'])
                ],
            ];
        })->unique()->values();


        return $scores;
    }

    public function getTeamInfo(string $teamId) : Collection
    {
        $data = Cache::get($this->cacheKey . date('Y'));

        if (empty($data)) return collect([]);

    }

    public function getTeamStandings(string $season, string $teamId) :array
    {
        $data = Cache::get($this->cacheKeyStanding . $season);

        return (new NflStandingsDto($data))->getTeamStandings($season, $teamId);
    }
}

