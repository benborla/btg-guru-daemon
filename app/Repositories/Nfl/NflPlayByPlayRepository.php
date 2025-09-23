<?php

namespace App\Repositories\Nfl;

use App\Models\NflGame;
use App\Models\NflGamePlaybyplayScores;

class NflPlayByPlayRepository
{

    public function __construct(
        private NflGamePlaybyplayScores $model
    ) {}



    public function getPlayByPlay($contestId)
    {
         $data = $this->model->where('contest_id', $contestId)->first() ?? [];
         $matchData = NflGame::where('contest_id', $contestId)->first() ?? [];

         $response = $data->response ?? [];

         // reconstruct the data for plays
         $drives = $response['playbyplay']['drive'] ?? [];

         if (isset($drives['id'])) {
            $drives = [$drives];
         }

         $drives = collect($drives)->reverse();

         $drives = $drives->map(function($drive) use($matchData) {
            $plays = $drive['play'] ?? [];

            if (isset($plays['id'])) {
               $plays = [$plays];
            }

            $quarter = "";
            $plays = collect($plays)->reverse()->map(function($play) use(&$quarter) {
               $quarter = trim(explode("-", $play['minute'])[1]); 
               return [
                  ...$play,
                  'type_name' => $this->getTypeName($play['type'], $play),
                  'quarter' => $quarter,
                  'away_score' => (int) $play['awayscore'] ?? 0,
                  'home_score' => (int) $play['localscore'] ?? 0
               ];
            });

            $awayScore = $plays->sum('away_score');
            $homeScore = $plays->sum('home_score');


            $drive['team_image_name'] = str_replace(" ", "_", $drive['name']);
            $lastPlay = $plays->filter(function($play) {
               return $play['type'] != 'Off TO' && $play['type'] != 'EH' && $play['type'] != 'PASS';
            })->last();


            $quarter = $lastPlay['quarter'] ?? $quarter;



            return [
                ...$drive,
                'play' => $plays,
                'quarter' => $quarter,
                'first_play_type' => $this->getTypeName($lastPlay['type'], [], true) ?? '',
                'away_short_name' => $matchData['away_team_short_name'] ?? '',
                'home_short_name' => $matchData['home_team_short_name'] ?? '',
                'away_score' => $awayScore,
                'home_score' => $homeScore
            ];
         });

         $byQuarter = [];

         foreach ($drives as $drive) {
            $byQuarter[$drive['quarter']][] = $drive;
         }

         $response['playbyplay']['drive'] = $drives;
         $response['byquarter'] = $byQuarter;


         return [
            'playbyplay' => [
                ...$response
            ],
            'matchData' => $matchData
         ];
    }

    private function getTypeName($type, $data = [], $forParent = false) {
        switch ($type) {
            case 'TD':
                return $forParent ? 'Touchdown' : ($data['yards'] ?? 0) . '-yd Pass';
            case '2PT':
                return 'Touchdown';
            case 'FG':
                return 'Field Goal';
            case 'KICK':
                return 'Kickoff';
            case 'RUSH':
                return ($data['yards'] ?? 0) . '-yd Run';
            case 'PASS':
                return ($data['yards'] ?? 0) . '-yd Pass';
            case 'PASS INCOMPLETE':
                return 'Incompletion';
            case 'PASS TD':
                return $forParent ? 'Touchdown' : 'Passing Touchdown';
            case 'PUNT':
                return 'Punt';
            case 'EG':
                return 'End of Game';
            case 'PAT':
                return 'PAT';
            case 'FUMBLE REC':
                return 'Fumble';
                    default:
                return $type;
        }
    }
}