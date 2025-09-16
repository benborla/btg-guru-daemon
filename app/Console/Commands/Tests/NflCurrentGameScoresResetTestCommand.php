<?php

namespace App\Console\Commands\Tests;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\NflGame;
use Carbon\Carbon;
use App\Repositories\Nfl\NflApiRepository;

class NflCurrentGameScoresResetTestCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'nfl:current-game-scores-reset-test';

    /**
     * The console command description.
     */
    protected $description = 'Reset current game scores';

    /**
     * API configuration
     */
    //http://www.goalserve.com/getfeed/9645f122eef946c1c7bd08dd5ac0e712/football/nfl-standings
    private const API_BASE_URL = 'goalserve.com/getfeed/9645f122eef946c1c7bd08dd5ac0e712/football/nfl-standings';
    private const API_NFL_SCORES_URL = "https://www.goalserve.com/getfeed/9645f122eef946c1c7bd08dd5ac0e712/football/nfl-scores?json=1";
    private const CACHE_PREFIX = 'nfl-standings';
    private const DEFAULT_CACHE_TTL = 86400; // 1 day in seconds

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->updateScores();

    }

    public function fetchApiScores()
    {
        $cacheKey = "nfl_api_scores_live";

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $this->info("Fetching LIVE API scores...");
        $response = Http::get(self::API_NFL_SCORES_URL);

        Cache::put($cacheKey, $response->json(), now()->addSeconds(10));

        return $response->json();
    }

    public function updateScores()
    {
        $scores = $this->fetchApiScores();

        $games = collect($scores['scores']['category']['match'] ?? []);

        if ($games->count() == 0) {
            $this->info("No games found");
            return;
        }

        $this->info("Resetting scores...");
        $games->map(function($game){

            $this->info( date('Y-m-d H:i:s') ."Resetting game: " . $game['contestID']);
            NflGame::updateOrCreate(
                ['contest_id' => $game['contestID']],
                [
                    'awayteam' => [
                        ...$game['awayteam'],
                        'totalscore' => 0,
                        'q1' => 0,
                        'q2' => 0,
                        'q3' => 0,
                        'q4' => 0,
                        'ot' => 0,
                        'status' => 'Not Started'
                    ],
                    'hometeam' => [
                        ...$game['hometeam'],
                        'totalscore' => 0,
                        'q1' => 0,
                        'q2' => 0,
                        'q3' => 0,
                        'q4' => 0,
                        'ot' => 0,
                        'status' => 'Not Started'
                    ]

                ]
            );
        });

        $this->info("Done resetting scores...");
    }


}
