<?php

namespace App\Console\Commands;

use App\Services\Afl\AflService;
use Illuminate\Console\Command;
use App\Models\AflApiResponse;
use App\Events\{AflDataUpdate, AflGetLiveMatch};
use Illuminate\Support\Str;
use App\Jobs\AflLiveDataSyncJob;
use App\Models\AflSchedule;
use Carbon\Carbon;
use function Laravel\Prompts\progress;
use App\Models\Types\AflRequestType;

class FetchAflAllRecordCommand extends Command
{
    /**
     * The name and signature of the command.
     *
     * @var string
     */
    protected $signature = 'api:afl:all';

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
        $this->warn('This command will truncate the AFL API responses table');
        if (!$this->confirm('Do you want to proceed?')) {
            return Command::FAILURE;
        }
        AflApiResponse::truncate();

        // Get all rounds to process
        $rounds = iterate_through_current_round_until_start();
        $totalRounds = count($rounds);

        // Create a progress bar for rounds
        progress(
            'Processing AFL rounds',
            $totalRounds,
            function ($step) use ($rounds, $totalRounds) {
                if ($step >= $totalRounds) {
                    return;
                }

                $round = $rounds[$step];

                // Get schedules for this round
                $schedules = AflSchedule::byRound($round)->get();
                $totalSchedules = $schedules->count();

                // Return early with a message if no schedules found
                if ($totalSchedules <= 0) {
                    return "Round {$round}: No matches found";
                }

                // Simulate processing all schedules for this round
                foreach ($schedules as $schedule) {

                    // Uncomment when ready to make actual API calls
                    $startTime = microtime(true);
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

                // Return a message showing the round number and match count
                return "Round {$round}: {$totalSchedules} matches processed";
            }
        );

        // Call again the schedules and standings
        $this->info('Fetching schedules and standings...');
        $this->call('api:afl:schedules');
        $this->call('api:afl:standings');

        return Command::SUCCESS;
    }
}
