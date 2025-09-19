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

class NflLiveUpdateScoresCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'nfl:live-update-scores
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
    private const API_NFL_SCORES_URL = "https://www.goalserve.com/getfeed/9645f122eef946c1c7bd08dd5ac0e712/football/nfl-scores?json=1";
    private const CACHE_PREFIX = 'nfl-standings';
    private const DEFAULT_CACHE_TTL = 86400; // 1 day in seconds
    private const HAS_MATCH_URL = '/api/v1/nfl/has-match-today'; // 1 day in seconds
    private const API_NFL_PLAYBYPLAY_URL = "https://www.goalserve.com/getfeed/9645f122eef946c1c7bd08dd5ac0e712/football/nfl-playbyplay-scores?json=1";

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if ($this->hasMatchToday()) {
            $this->info('🏈 Has match today');
            $this->updateScores();
            $this->fetchApiPlayByPlay();
        }
    }

    public function hasMatchToday()
    {
        if ($this->option('test')) {
            return true;
        }

        $response = Http::get(env('APP_URL') . self::HAS_MATCH_URL);
        $status = $response->json();
        $this->info("Has match today: " . json_encode($status));

        return $status['status'] ?? false;
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

    public function fetchApiPlayByPlay()
    {
        $cacheKey = "nfl_api_playbyplay_live";

        $this->info("Fetching LIVE API playbyplay...");
        $response = Http::get(self::API_NFL_PLAYBYPLAY_URL);
        $matches = $response['scores']['category']['match'];

        $this->storePlayByPlay($matches);
        Cache::put($cacheKey, $matches, now()->addDays(1));

        return $matches;
    }

    public function storePlayByPlay($matches)
    {
        $matches = collect($matches);
        if ($matches->count() > 0) {

            $matches->map(function($item){
                NflGamePlaybyplayScores::updateOrCreate(
                    [
                        'contest_id' => $item['contestID']
                    ],
                    [
                        'response' => $item
                    ]
                );
            });
        
        }
    }

    public function updateScores()
    {
        $scores = $this->fetchApiScores();

        $games = collect($scores['scores']['category']['match'] ?? []);

        if ($games->count() == 0) {
            $this->info("No games found");
            return;
        }

        $this->info("Updating scores...");
        $games->map(function($game){

            $this->info( date('Y-m-d H:i:s') ."Updating game: " . $game['contestID']);
            // NflGame::where('contest_id', $game['contestID'])->first()->delete();
            NflGame::updateOrCreate(
                ['contest_id' => $game['contestID']],
                $game
            );
        });

        $this->info("Done updating scores...");
    }


}
