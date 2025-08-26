<?php

// app/Console/Commands/FetchNflScores.php
namespace App\Console\Commands;

use App\Dto\NflScoreData;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\NflGame;
use Carbon\Carbon;

class FetchNflScores extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'nfl:fetch-scores
                            {--date= : Specific date to fetch (YYYY-MM-DD format)}
                            {--week= : NFL week number to fetch}
                            {--season : NFL season year (default: current year)}
                            {--season_type_id : NFL season year (default: current year)}
                            {--season_type_name : NFL season year (default: current year)}
                            {--force : Force refresh cache}
                            {--store : Store results in database}
                            {--live : Fetch only live games with short cache}';

    /**
     * The console command description.
     */
    protected $description = 'Fetch NFL scores from API with caching support';

    /**
     * API configuration
     */
    private const API_BASE_URL = 'goalserve.com/getfeed/9645f122eef946c1c7bd08dd5ac0e712/football/nfl-scores';
    private const CACHE_PREFIX = 'nfl_scores';
    private const DEFAULT_CACHE_TTL = 86400; // 1 day in seconds

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🏈 Fetching NFL Scores...');
        $scores = null;

        try {
            $options = $this->parseOptions();
            $cacheKey = $this->buildCacheKey($options);
            $cacheTtl = $this->determineCacheTtl($options);

            $this->line("Cache Key: {$cacheKey}");
            $this->line("Cache TTL: {$cacheTtl} seconds (" . $this->formatDuration($cacheTtl) . ")");

            // Check if we should use cache
            if (!$this->option('force') && Cache::has($cacheKey)) {
                $this->info('📦 Using cached data...');
                $scores = Cache::get($cacheKey);
                $fromCache = true;
            } else {
                $this->info('🌐 Fetching from API...');
                $scores = $this->fetchFromApi($options);

                if (empty($scores)) {
                    $this->error('❌ No scores returned from API');
                    return Command::FAILURE;
                }

                // Cache the results
                Cache::put($cacheKey, $scores, $cacheTtl);
                $fromCache = false;
            }

            // Display results
            /* $this->displayResults($scores, $fromCache); */

            // Store in database if requested
            if ($this->option('store')) {
                $this->storeInDatabase($scores);
            }

            $this->info('✅ Command completed successfully!');
            return Command::SUCCESS;

        } catch (\Exception $e) {
            /* $this->error("❌ Error: {$e->getMessage()}"); */
            Log::error('NFL fetch command failed', [
                'error' => $e->getMessage(),
                /* 'trace' => $e->getTraceAsString() */
            ]);
        }
    }

    /**
     * Parse command options
     */
    private function parseOptions(): array
    {
        $date = $this->option('date');
        $week = $this->option('week');
        $season = $this->option('season') ?? date('Y');
        $seasonTypeId = $this->option('season_type_id') ?? '';
        $seasonTypeName = $this->option('season_type_name') ?? '';

        // Validate week
        /* if ($week && ($week < 1 || $week > 22)) { */
        /*     throw new \InvalidArgumentException('Week must be between 1 and 22'); */
        /* } */

        return [
            'date' => $date,
            'week' => $week,
            'season' => $season,
            'season_type_id' => $seasonTypeId,
            'season_type_name' => $seasonTypeName,
            'live_only' => $this->option('live'),
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
        if ($options['week']) {
            $parts[] = "week_{$options['week']}";
        }
        if ($options['season']) {
            $parts[] = "season_{$options['season']}";
        }
        if ($options['live_only']) {
            $parts[] = 'live';
        }

        return implode('_', $parts);
    }

    /**
     * Determine cache TTL based on options
     */
    private function determineCacheTtl(array $options): int
    {
        // Live games get shorter cache
        if ($options['live_only']) {
            return 300; // 5 minutes for live games
        }

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
        if ($this->isNflSeason()) {
            return 3600; // 1 hour during active season
        }

        return self::DEFAULT_CACHE_TTL; // 1 day default
    }

    /**
     * Fetch data from API
     */
    private function fetchFromApi(array $options): array
    {
        $params = ['json' => 1];

        // Add parameters based on options
        if ($options['date']) {
            $params['date'] = $options['date'];
        }
        /* if ($options['week']) { */
        /*     $params['week'] = $options['week']; */
        /* } */
        /* if ($options['season']) { */
        /*     $params['season'] = $options['season']; */
        /* } */

        $this->line('API URL: ' . self::API_BASE_URL);
        $this->line('Parameters: ' . json_encode($params));
        $this->line('options: ' . json_encode($options));

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
            'games_count' => count($data['scores']['category']['match'] ?? []),
            'options' => $options
        ]);

        $scores = $data['scores']['category']['match'] ?? [];

        // Filter live games if requested
        if ($options['live_only']) {
            $scores = $scores->filter(function ($game) {
                return $this->isGameLive($game);
            });
        }

        return $scores;
    }

    /**
     * Display results in a nice table
     */
    private function displayResults(\Illuminate\Support\Collection $scores, bool $fromCache): void
    {
        $this->newLine();
        $this->info($fromCache ? '📦 Cached Results:' : '🌐 Fresh Results:');
        $this->line("Found {$scores->count()} games");

        if ($scores->isEmpty()) {
            $this->warn('No games found');
            return;
        }

        // Prepare table data
        $tableData = [];
        foreach ($scores['category']['match'] as $game) {
            $homeTeam = $game['hometeam']['name'] ?? $game['home_team'] ?? 'Unknown';
            $awayTeam = $game['awayteam']['name'] ?? $game['away_team'] ?? 'Unknown';
            $homeScore = $game['hometeam']['totalscore'] ?? $game['home_score'] ?? '0';
            $awayScore = $game['awayteam']['totalscore'] ?? $game['away_score'] ?? '0';
            $status = $game['status'] ?? 'Unknown';
            $date = $game['date'] ?? $game['formatted_date'] ?? 'Unknown';

            $tableData[] = [
                substr($awayTeam, 0, 20), // Truncate long names
                $awayScore,
                substr($homeTeam, 0, 20),
                $homeScore,
                $status,
                $date,
            ];
        }

        $this->table(
            ['Away Team', 'Score', 'Home Team', 'Score', 'Status', 'Date'],
            $tableData
        );
    }

    /**
     * Store results in database
     */
    private function storeInDatabase($scores): void
    {
        $this->info('💾 Storing in database...');

        $bar = $this->output->createProgressBar(count($scores));
        $options = $this->parseOptions();
        $stored = 0;
        $updated = 0;
        $others = [
            'season' => $options['season'],
            'week' => $options['week'],
            'season_type_id' => $options['season_type_id'],
            'season_type_name' => $options['season_type_name']
        ];

        if (isset($scores['contestID'])) {

            $data = NflScoreData::fromApiResponse($scores)->toArray();
            $data = [
                ...$data,
                ...$others
            ];

            $game = NflGame::updateOrCreate(
                ['contest_id' => $data['contest_id']],
                $data
            );
        } else {

            foreach ($scores as $gameData) {
                $data = NflScoreData::fromApiResponse($gameData)->toArray();

                $game = NflGame::updateOrCreate(
                    ['contest_id' => $data['contest_id']],
                    [
                        ...$data,
                        ...$others
                    ]
                );

                if ($game->wasRecentlyCreated) {
                    $stored++;
                } else {
                    $updated++;
                }

                $bar->advance();
            }
        }


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
