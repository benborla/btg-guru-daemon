<?php

namespace App\Console\Commands;

use App\Services\Afl\AflService;
use Illuminate\Console\Command;
use App\Models\AflApiResponse;
use App\Events\{AflDataUpdate, AflGetLiveMatch};
use Illuminate\Support\Str;
use App\Jobs\AflLiveDataSyncJob;
use Carbon\Carbon;

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

        foreach ($rounds = iterate_through_current_round_until_start() as $round) {
            $this->info('Fetching AFL data for round ' . $round['round'] . '...');
            // get the starting time in seconds
            $startTime = microtime(true);
            $data = $this->service->getApiLiveData($round['start']);
            $endTime = microtime(true);
            $responseTime = $endTime - $startTime;

            // Convert the date format into: dd.mm.yyyy
            $formattedDate = Carbon::parse($round['start'])->format('d.m.Y');
            // Pass the date parameter directly to the API call
            $data = $this->service->getApiLiveData('date=' . $formattedDate);
            $uri = $data['uri'];

            if (empty($data['response'])) {
                $this->error('Failed to fetch AFL data');
                return Command::FAILURE;
            }

            $this->info('Successfully fetched AFL data');
            // Update database with the new content
            $response = $data['response'];

            // Create or update based on $uri
            $latestData = AflApiResponse::create([
                'uri' => $uri,
                'round' => $round['round'],
                'match_date' => $round['start'],
                'response' => $response,
                'response_code' => $data['response_code'],
                'response_time' => round($responseTime),
                'request_id' => Str::uuid(),
            ]);

            $this->info('Event broadcast successfully');
            $this->info('Event Summary');
            $this->info('URI: ' . $uri);
            $this->info('Response Code: HTTP/2 ' . $data['response_code']);
            $this->info('API call took: ' . round($responseTime) . ' seconds');
            // insert new line
            $this->info('');
        }
        // Call again the schedules and standings
        $this->info('Fetching schedules and standings...');
        $this->call('api:afl:schedules');
        $this->call('api:afl:standings');

        return Command::SUCCESS;
    }
}
