<?php

namespace App\Console\Commands;

use App\Services\Afl\AflService;
use Illuminate\Console\Command;
use App\Models\AflApiResponse;
use Illuminate\Support\Str;
use App\Models\AflSchedule;
use App\Models\Types\AflRequestType;

class FetchAflAllRecordCommand extends Command
{
    /**
     * The name and signature of the command.
     *
     * @var string
     */
    protected $signature = 'api:afl:all {--yes : Skip confirmation prompt}';

    /**
     * The command description.
     *
     * @var string
     */
    protected $description = 'Fetch ALL AFL data starting from Opening Round to the current round from GoalServe API';

    protected AflService $service;

    public function __construct(AflService $aflService)
    {
        $this->service = $aflService;
        parent::__construct();
    }

    public function handle()
    {
        return $this->all();
    }

    public function all(): int
    {
        $this->info('Fetching ALL AFL data starting from Opening Round to the current round from GoalServe API...');
        $this->info('');

        $this->call('api:afl');
        // Get all rounds to process
        $rounds = iterate_through_current_round_until_start();
        $totalRounds = count($rounds);

        // Process each round
        foreach ($rounds as $round) {
            // Get schedules for this round
            $schedules = AflSchedule::byRound($round)->get();
            $totalSchedules = $schedules->count();

            // Skip if no schedules found
            if ($totalSchedules <= 0) {
                $this->info("Round {$round}: No matches found");
                continue;
            }

            $this->info("Processing Round {$round}: {$totalSchedules} matches");

            // Process all schedules for this round
            foreach ($schedules as $schedule) {
                // Uncomment when ready to make actual API calls
                $startTime = microtime(true);
                // check whether this historical data is already fetched
                $uri = AflApiResponse::URI_LIVE . "&date=" . $schedule->date;
                if (AflApiResponse::where('uri', $uri)->exists()) {
                    $this->info("Round {$round}: {$schedule->date} already fetched");
                    continue;
                }

                $data = $this->service->getApiLiveData('date=' . $schedule->date);
                $responseTime = microtime(true) - $startTime;

                if (!empty($data['response'])) {
                    AflApiResponse::create([
                        'uri' => $data['uri'],
                        'round' => $schedule->round,
                        'match_date' => $schedule->date,
                        'response' => $data['response'],
                        'response_code' => $data['response_code'],
                        'response_time' => round($responseTime),
                        'request_id' => Str::uuid(),
                        'request_type' => AflRequestType::Record->name,
                    ]);
                }
            }

            $this->info("Round {$round}: {$totalSchedules} matches processed");
        }

        // Call again the schedules and standings
        $this->info('Fetching schedules and standings...');

        $this->call('api:afl:schedules');
        $this->call('api:afl:standings');
        $this->call('afl:schedule');

        return Command::SUCCESS;
    }
}
