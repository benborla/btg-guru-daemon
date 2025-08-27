<?php

// app/Console/Commands/FetchNflScores.php
namespace App\Console\Commands;

use App\Models\NflApiResponse;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class FetchNflSchedules extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'nfl:api:fetch-schedules
                            {--force : Force refresh cache}
                            {--store : Store results in database}';

    /**
     * The console command description.
     */
    protected $description = 'Fetch NFL schedules from API with caching support';

    /**
     * API configuration
     */
    private const API_BASE_URL = 'goalserve.com/getfeed/9645f122eef946c1c7bd08dd5ac0e712/football/nfl-schedule';
    private const CACHE_PREFIX = 'nfl_schedule';
    private const DEFAULT_CACHE_TTL = 86400; // 1 day in seconds


    private const API_SCORE_BASE_URL = 'goalserve.com/getfeed/9645f122eef946c1c7bd08dd5ac0e712/football/nfl-scores';
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🏈 Fetching NFL Scores...');

        try {
            $options = $this->parseOptions();
            $cacheKey = $this->buildCacheKey($options);
            $cacheTtl = $this->determineCacheTtl($options);

            $this->line("Cache Key: {$cacheKey}");
            $this->line("Cache TTL: {$cacheTtl} seconds (" . $this->formatDuration($cacheTtl) . ")");

            // Check if we should use cache
            if (!$this->option('force') && Cache::has($cacheKey)) {
                $this->info('📦 Using cached data...');
                $schedules = Cache::get($cacheKey);
                $fromCache = true;
            } else {
                $this->info('🌐 Fetching from API...');
                $schedules = $this->fetchFromApi($options);

                if (empty($schedules)) {
                    $this->error('❌ No scores returned from API');
                    return Command::FAILURE;
                }

                // Cache the results
                Cache::put($cacheKey, collect($schedules), $cacheTtl);
                $fromCache = false;
            }

            // Display results
            /* $this->displayResults($scores, $fromCache); */

            // Store in database if requested
            if ($this->option('store')) {
                $this->storeInDatabase($schedules);
            }

            $this->info('✅ Command completed successfully!');
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Error: {$e->getMessage()}");
            Log::error('NFL fetch command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }

    /**
     * Parse command options
     */
    private function parseOptions(): array
    {
        $date = date('Y-m-d');
        /* $week = $this->option('week'); */
        /* $season = $this->option('season') ?? date('Y'); */
        /**/
        /* // Validate date format */
        /* if ($date && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) { */
        /*     throw new \InvalidArgumentException('Date must be in YYYY-MM-DD format'); */
        /* } */
        /**/
        /* // Validate week */
        /* if ($week && ($week < 1 || $week > 22)) { */
        /*     throw new \InvalidArgumentException('Week must be between 1 and 22'); */
        /* } */

        return [
            'date' => $date,
            /* 'week' => $week, */
            /* 'season' => $season, */
            /* 'live_only' => $this->option('live'), */
        ];
    }

    /**
     * Build cache key based on options
     */
    private function buildCacheKey(array $options): string
    {
        $parts = [self::CACHE_PREFIX];

        if ($options['date']) {
            $parts[] = "date_{$options['date']}";
        }
        /* if ($options['week']) { */
        /*     $parts[] = "week_{$options['week']}"; */
        /* } */
        /* if ($options['season']) { */
        /*     $parts[] = "season_{$options['season']}"; */
        /* } */
        /* if ($options['live_only']) { */
        /*     $parts[] = 'live'; */
        /* } */

        return implode('_', $parts);
    }

    /**
     * Determine cache TTL based on options
     */
    private function determineCacheTtl(array $options): int
    {
        // Live games get shorter cache
        /* if ($options['live_only']) { */
        /*     return 300; // 5 minutes for live games */
        /* } */

        // Specific date gets 1 day cache
        if ($options['date']) {
            $targetDate = Carbon::parse($options['date']);

            // If it's today or future, shorter cache
            if ($targetDate->isToday() || $targetDate->isFuture()) {
                return 3600; // 1 hour for today/future games
            }

            // Past games get longer cache
            return self::DEFAULT_CACHE_TTL; // 1 day
        }

        // Current week gets shorter cache during season
        /* if ($this->isNflSeason()) { */
        /*     return 3600; // 1 hour during active season */
        /* } */

        return self::DEFAULT_CACHE_TTL; // 1 day default
    }

    /**
     * Fetch data from API
     */
    private function fetchFromApi(array $options): array
    {
        $params = ['json' => 1];

        // Add parameters based on options
        /* if ($options['date']) { */
        /*     $params['date'] = $options['date']; */
        /* } */
        /* if ($options['week']) { */
        /*     $params['week'] = $options['week']; */
        /* } */
        /* if ($options['season']) { */
        /*     $params['season'] = $options['season']; */
        /* } */

        $this->line('API URL: ' . self::API_BASE_URL);
        $this->line('Parameters: ' . json_encode($params));

        // Make the API request with progress
        $bar = $this->output->createProgressBar(1);
        $bar->setMessage('Calling API...');
        $bar->start();

        $response = Http::timeout(30)
            ->retry(3, 1000)
            ->get(self::API_BASE_URL, $params);

        $bar->advance();
        $bar->finish();
        $this->newLine();

        if (!$response->successful()) {
            throw new \Exception("API request failed with status: {$response->status()}");
        }

        $data = $response->json();

        // Log API response info
        Log::info('NFL API fetch completed', [
            'status' => $response->status(),
            /* 'games_count' => count($data['scores']['category']['match'] ?? []), */
            'options' => []//$options
        ]);


        return $data;
    }


    private function fetchNflScoresApi($date)
    {
        $params = ['json' => 1 , 'date' => $date];
        $url = "";

        $this->line('API URL: ' . self::API_SCORE_BASE_URL);
        $this->line('Parameters: ' . json_encode($params));

        // Make the API request with progress
        $bar = $this->output->createProgressBar(1);
        $bar->setMessage('Calling API...');
        $bar->start();

        $response = Http::timeout(30)
            ->retry(3, 1000)
            ->get(self::API_SCORE_BASE_URL, $params);

        $bar->advance();
        $bar->finish();
        $this->newLine();

        if (!$response->successful()) {
            throw new \Exception("API request failed with status: {$response->status()}");
        }

        /* $data = $response->json(); */

        // Log API response info
        Log::info('NFL SCORE API fetch completed', [
            'status' => $response->status(),
            /* 'games_count' => count($data['scores']['category']['match'] ?? []), */
            'options' => []//$options
        ]);


        return $response->json();
    }

    /**
     * Store results in database
     */
    private function storeInDatabase($schedules): void
    {
        $this->info('💾 Storing in database...');

        $bar = $this->output->createProgressBar(count($schedules));
        $stored = 0;
        $updated = 0;

        NflApiResponse::updateOrCreate(
            [ 'date_fetched' => date('Y-m-d') ],
            [
                'season' => $schedules['shedules']['season'],
                'response' => json_encode($schedules),
                'date_fetched' => date('Y-m-d')
            ]
        );

        /* dd(collect($schedules->first())['season']); */
        // get all scores
        collect($schedules->first()['tournament'])->map(function($season) use($schedules) {

            $weeks = collect($season['week'])->map(function($game, $week) use($season,$schedules ){

                $matches = collect($game['matches'])->map(function($match) use($season, $game, $schedules, $week){
                    $parsed = Carbon::parse($match['date']);

                    // Convert to desired format (d.m.Y)
                    $formatted = $parsed->format('d.m.Y');
                    $this->call('nfl:fetch-scores', [
                        '--date' => $formatted,
                        '--force' => true,
                        '--store' => true,
                        '--season' =>  collect($schedules->first())['season'],
                        '--season_type_id' => $season['id'],
                        '--season_type_name' => $season['name'],
                        '--week' => $week+1
                    ]);
                });

            });
        });



        $bar->finish();
        $this->newLine();
        $this->info("📊 Database updated: {$stored} created, {$updated} updated");
    }

    /**
     * Check if game is live
     */
    private function isGameLive(array $game): bool
    {
        $status = strtolower($game['status'] ?? '');
        $liveStatuses = ['1st', '2nd', '3rd', '4th', 'ot', 'half', 'halftime'];

        return in_array($status, $liveStatuses);
    }

    /**
     * Check if it's NFL season
     */
    private function isNflSeason(): bool
    {
        $now = Carbon::now();
        return ($now->month >= 9) || ($now->month <= 2);
    }

    /**
     * Format duration in human readable format
     */
    private function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return "{$seconds} seconds";
        } elseif ($seconds < 3600) {
            return round($seconds / 60) . " minutes";
        } else {
            return round($seconds / 3600) . " hours";
        }
    }
}
