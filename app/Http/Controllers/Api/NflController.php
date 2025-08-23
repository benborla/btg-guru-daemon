<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\Nfl\NflScoresRepository;
use Illuminate\Http\JsonResponse;

class NflController extends Controller
{

    public function __construct(
        private NflScoresRepository $repository
    ) {}

    public function scores(Request $request)
    {
        $week = $request->query('week');
        $forceRefresh = $request->boolean('refresh');

        /* if ($forceRefresh) { */
        /*     $scores = $this->repository->refreshScores($week); */
        /* } else { */
            $scores = $this->repository->getScores($week);
        /* } */

        return response()->json([
            'data' => $scores,
            'cached' => !$forceRefresh,
            'timestamp' => now()->toISOString(),
        ]);
    }

    public function liveScores()
    {
        return response()->json([
            'data' => $this->repository->getLiveScores(),
            'updated_at' => now()->toISOString(),
        ]);
    }

    public function realTimeScores()
    {
        return response()->json([
            'data' => $this->repository->getRealTimeScores(),
            'updated_at' => now()->toISOString(),
            'cache_ttl' => 5, // Let frontend know cache duration
        ]);
    }

    public function dynamicScores()
    {
        $scores = $this->repository->getScoresWithDynamicTtl();

        return response()->json([
            'data' => $scores,
            'updated_at' => now()->toISOString(),
            'has_live_games' => $scores->contains(fn($game) => $this->isGameLive($game)),
            'next_update' => now()->addSeconds($this->calculateClientRefreshInterval())->toISOString(),
        ]);
    }

    private function calculateClientRefreshInterval(): int
    {
        // Frontend should refresh based on game status
        $now = Carbon::now();
        $isGameTime = in_array($now->dayOfWeek, [0, 1, 4]) &&
                     $now->hour >= 13 && $now->hour <= 23;

        return $isGameTime ? 10 : 60; // 10 seconds during games, 1 minute otherwise
    }

    public function teamsInfo() : JsonResponse
    {

        $data = $this->repository->getTeamsInfo();

        return response()->json($data);
    }

    public function teamSchedule($teamId = null) : JsonResponse
    {
        $allTeams = $this->repository->getTeamsInfo();
        $teamInfo = $allTeams->firstWhere('id', $teamId);

        return response()->json([
            'teams' => $this->repository->getTeamsInfo()->sortBy('name')->values(),
            'teamInfo' => $teamInfo ?? [],
            'data' => []
        ]);
    }
}
