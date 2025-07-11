<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AflApiResponse;
use App\Models\AflSchedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AflFormatSchedule extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'afl:schedule';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update & Format AFL schedule';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Updating & Formatting AFL schedule...');
        $schedule = AflApiResponse::query()->getLatestSchedule()->first();
        
        if (!$schedule) {
            $this->error('No schedule data found in API responses');
            return Command::FAILURE;
        }
        
        try {
            DB::beginTransaction();
            
            AflSchedule::truncate();
            $this->info('Cleared existing schedule data');
            
            $this->processScheduleData($schedule->response);
            
            DB::commit();
            $this->info('AFL schedule data successfully updated');
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Failed to update AFL schedule: ' . $e->getMessage());
            Log::error('AFL schedule update failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return Command::FAILURE;
        }
    }
    
    /**
     * Process the schedule data from the API response and store it in the database.
     *
     * @param array $response The parsed API response
     * @return void
     * @throws \Exception If response structure is invalid
     */
    private function processScheduleData(array $response)
    {
        if (!isset($response['results']['tournament']['round'])) {
            throw new \Exception('Invalid API response structure: missing rounds data');
        }
        
        $rounds = $response['results']['tournament']['round'];
        $schedulesAdded = 0;
        
        foreach ($rounds as $round) {
            if (!isset($round['week'])) {
                continue;
            }
            
            $weeks = is_array($round['week']) ? $round['week'] : [$round['week']];
            
            foreach ($weeks as $week) {
                $weekNumber = $week['@number'];
                
                if (!isset($week['match'])) {
                    continue;
                }
                
                $matches = is_array($week['match']) ? $week['match'] : [$week['match']];
                
                foreach ($matches as $match) {
                    $this->createScheduleRecord($match, $weekNumber);
                    $schedulesAdded++;
                }
            }
        }
        
        $this->info("Added {$schedulesAdded} schedule records");
    }
    
    /**
     * Create a schedule record from match data.
     *
     * @param array $match Match data from API
     * @param string|int $weekNumber Week number/round identifier
     * @return void
     */
    private function createScheduleRecord(array $match, $weekNumber)
    {
        AflSchedule::create([
            'round' => $weekNumber,
            'match_id' => $match['@id'],
            'date' => $match['@date'],
            'time' => $match['@time'],
            'status' => $match['@status'],
            'venue' => $match['@venue'],
            'local_team' => $this->cleanTeamData($match['localteam']),
            'visitor_team' => $this->cleanTeamData($match['visitorteam']),
        ]);
    }
    
    /**
     * Clean team data by removing @ prefix from keys.
     *
     * @param array $teamData Raw team data from API
     * @return array Cleaned team data
     */
    private function cleanTeamData(array $teamData)
    {
        $cleanedData = [];
        foreach ($teamData as $key => $value) {
            $cleanKey = str_replace('@', '', $key);
            $cleanedData[$cleanKey] = $value;
        }
        return $cleanedData;
    }
}
