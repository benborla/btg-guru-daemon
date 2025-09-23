<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\NflGame;
use Carbon\Carbon;
use App\Repositories\Nfl\NflApiRepository;
use App\Models\NflGamePlaybyplayScores;
use App\Models\AflApiResponse;

class AflLiveUpdateScoresCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'afl:live-update-scores
                            {--test : Test mode}';

    /**
     * The console command description.
     */
    protected $description = 'Auto update scores when there is a live game going on';

    /**
     * API configuration
     */
    //http://www.goalserve.com/getfeed/9645f122eef946c1c7bd08dd5ac0e712/football/nfl-standings
    private const API_BASE_URL = 'goalserve.com/getfeed/9645f122eef946c1c7bd08dd5ac0e712/football/nfl-standings';
    private const API_AFL_SCORES_URL = "https://www.goalserve.com/getfeed/9645f122eef946c1c7bd08dd5ac0e712/afl/home?json=1";
    private const CACHE_PREFIX = 'nfl-standings';
    private const DEFAULT_CACHE_TTL = 86400; // 1 day in seconds
    private const LIVE_GAME_DATES_URL = '/api/v1/afl/current-live-games'; // 1 day in seconds

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $liveGames = $this->getLiveGames();
        if ($liveGames->count() > 0 || $this->option('test')) {
            $this->info('AFL Has match today');

            $liveGames->map(function($game){
                $this->info('Fetching afl scores for ' . json_encode($game));
                $res = $this->fetchApiScores($game['date']);
                $this->updateScores($res, $game);
            });
        } else {
            $this->info('No AFL games today ' . date('Y-m-d H:i:s'));
        }
    }

    public function getLiveGames()
    {
        $response = Http::get(env('APP_URL') . self::LIVE_GAME_DATES_URL);
        $response = $response->json();

        return collect($response['data'] ?? []);
    }

    public function fetchApiScores($gameDate)
    {
        $response = Http::get(self::API_AFL_SCORES_URL . '&date=' . $gameDate);
        // $response = Http::get(self::API_AFL_SCORES_URL . '&date=' . '19.09.2025');

        return $response->json();
    }

    public function updateScores($apiResponse, $game)
    {
        $uri = "/afl/home?json=1&date=" . $game['date'];

        
        $this->info("Updating AFL scores..." . $uri);
        AflApiResponse::updateOrCreate(
            ['uri' => $uri],
            [
                'response' => $apiResponse,
                'match_date' => $game['date'],
                'round' => $game['round']
            ]
        );
        $this->info("Done updating AFL scores...");
    }


}
