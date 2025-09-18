<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NflGame extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        "attendance",
        "awayteam",
        "hometeam",
        "contest_id",
        "date",
        "datetime_utc",
        "defensive",
        "events",
        "formatted_date",
        "fumbles",
        "interceptions",
        "kick_returns",
        "kicking",
        "passing",
        "punt_returns",
        "punting",
        "receiving",
        "rushing",
        "status",
        "team_stats",
        "time",
        "timer",
        "timezone",
        "venue_id",
        "venue_name",
        "season",
        "season_type_id",
        "season_type_name",
        "week",
    ];

    protected $casts = [
        'fumbles' => 'array',
        'punt_returns' => 'array',
        'punting' => 'array',
        'awayteam' => 'json',
        'hometeam' => 'json',
        'defensive' => 'array',
        'events' => 'array',
        'interceptions' => 'array',
        'kick_returns' => 'array',
        'kicking' => 'array',
        'passing' => 'array',
        'receiving' => 'array',
        'rushing' => 'array',
        'team_stats' => 'array'
    ];

    protected $appends = [
        'away_team_id',
        'home_team_id',
        'away_team_image_name',
        'home_team_image_name',
        'away_team_name',
        'home_team_name',
        'away_team_short_name',
        'home_team_short_name',
        'home_score',
        'away_score',
        'home_q1',
        'home_q2',
        'home_q3',
        'home_q4',
        'home_ot',
        'away_q1',
        'away_q2',
        'away_q3',
        'away_q4',
        'away_ot',
        'home_passing_stats',
        'away_passing_stats',
        'home_team_stats',
        'away_team_stats',
        'home_rushing_stats',
        'away_rushing_stats',
        'home_receiving_stats',
        'away_receiving_stats',
        'current_match',
        'events_sorted',
        'has_ot',
        'formatted_full_time',
        'spreadbox',
        'score_page_time',
        'current_game_status',
        'current_quarter',
        
    ];

    public function getCurrentQuarterAttribute()
    {
       $events = $this->events ?? [];

       if (empty($events)) {
         return null;
       }

       $firstQuarter = $events['firstquarter'] ?? [];
       $secondQuarter = $events['secondquarter'] ?? [];
       $thirdQuarter = $events['thirdquarter'] ?? [];
       $fourthQuarter = $events['fourthquarter'] ?? [];
       $overtime = $events['overtime'] ?? [];

       if (!empty($firstQuarter)) {
         return '1st';
       }

       if (!empty($secondQuarter)) {
         return '2nd';
       }

       if (!empty($thirdQuarter)) {
         return '3rd';
       }

       if (!empty($fourthQuarter)) {
         return '4th';
       }

       if (!empty($overtime)) {
         return 'OT';
       }

       return null;
    }

    public function getCurrentGameStatusAttribute()
    {
      $australiaTime = Carbon::parse($this->datetime_utc, 'UTC')->setTimeZone('Australia/Sydney');
      $status = $this->status == 'After Over Time' ? "(OT)" : $this->status;

      return $this->status == 'Not Started' ? $australiaTime->format('D m/d g:i A') : $status;
    }

    public function getScorePageTimeAttribute()
    {
      return Carbon::parse($this->datetime_utc, 'UTC')->setTimeZone('Australia/Sydney')->format('D m/d g:i A');
    }

    public function getSpreadboxAttribute()
    {
      $awayq1 = $this->away_q1 ?? 0;
      $awayq2 = $this->away_q2 ?? 0;
      $away1h = $awayq1 + $awayq2;
      $awayq3 = $this->away_q3 ?? 0;
      $awayq4 = $this->away_q4 ?? 0;
      $away2h = $awayq3 + $awayq4;
      $awayot = $this->away_ot ?? 0;
      $awayTotal = $awayq1 + $awayq2 + $awayq3 + $awayq4 + $awayot;

      $homeq1 = $this->home_q1 ?? 0;
      $homeq2 = $this->home_q2 ?? 0;
      $home1h = $homeq1 + $homeq2;
      $homeq3 = $this->home_q3 ?? 0;
      $homeq4 = $this->home_q4 ?? 0;
      $home2h = $homeq3 + $homeq4;
      $homeot = $this->home_ot ?? 0;
      $homeTotal = $homeq1 + $homeq2 + $homeq3 + $homeq4 + $homeot;

      $data[] = [
        'name' => '1st',
        'away_score' => $awayq1,
        'away_spread' => $awayq1 < $homeq1 ? "+" . ($homeq1 - $awayq1) : ($awayq1 == $homeq1 ? "0" : "-" . ($awayq1 - $homeq1)),
        'home_score' => $homeq1,
        'home_spread' => $homeq1 < $awayq1 ? "+" . ($awayq1 - $homeq1) : ($homeq1 == $awayq1 ? "0" : "-" . ($homeq1 - $awayq1))
      ]; 

      $data[] = [
        'name' => '2nd',
        'away_score' => $awayq2,
        'away_spread' => $awayq2 < $homeq2 ? "+" . ($homeq2 - $awayq2) : ($awayq2 == $homeq2 ? "0" : "-" . ($awayq2 - $homeq2)),
        'home_score' => $homeq2,
        'home_spread' => $homeq2 < $awayq2 ? "+" . ($awayq2 - $homeq2) : ($homeq2 == $awayq2 ? "0" : "-" . ($homeq2 - $awayq2))
      ]; 

      $data[] = [
        'name' => 'Half',
        'away_score' => $away1h,
        'away_spread' => $away1h < $home1h ? "+" . ($home1h - $away1h) : ($away1h == $home1h ? "0" : "-" . ($away1h - $home1h)),
        'home_score' => $home1h,
        'home_spread' => $home1h < $away1h ? "+" . ($away1h - $home1h) : ($home1h == $away1h ? "0" : "-" . ($home1h - $away1h))
      ]; 

      $data[] = [
        'name' => '3rd',
        'away_score' => $awayq3,
        'away_spread' => $awayq3 < $homeq3 ? "+" . ($homeq3 - $awayq3) : ($awayq3 == $homeq3 ? "0" : "-" . ($awayq3 - $homeq3)),
        'home_score' => $homeq3,
        'home_spread' => $homeq3 < $awayq3 ? "+" . ($awayq3 - $homeq3) : ($homeq3 == $awayq3 ? "0" : "-" . ($homeq3 - $awayq3))
      ]; 

      $data[] = [
        'name' => '4th',
        'away_score' => $awayq4,
        'away_spread' => $awayq4 < $homeq4 ? "+" . ($homeq4 - $awayq4) : ($awayq4 == $homeq4 ? "0" : "-" . ($awayq4 - $homeq4)),
        'home_score' => $homeq4,
        'home_spread' => $homeq4 < $awayq4 ? "+" . ($awayq4 - $homeq4) : ($homeq4 == $awayq4 ? "0" : "-" . ($homeq4 - $awayq4))
      ]; 

      $data[] = [
        'name' => '2nd Half',
        'away_score' => $away2h,
        'away_spread' => $away2h < $home2h ? "+" . ($home2h - $away2h) : ($away2h == $home2h ? "0" : "-" . ($away2h - $home2h)),
        'home_score' => $home2h,
        'home_spread' => $home2h < $away2h ? "+" . ($away2h - $home2h) : ($home2h == $away2h ? "0" : "-" . ($home2h - $away2h))
      ]; 

      $data[] = [
        'name' => 'Match',
        'away_score' => $awayTotal,
        'away_spread' => $awayTotal < $homeTotal ? "+" . ($homeTotal - $awayTotal) : ($awayTotal == $homeTotal ? "0" : "-" . ($awayTotal - $homeTotal)),
        'home_score' => $homeTotal,
        'home_spread' => $homeTotal < $awayTotal ? "+" . ($awayTotal - $homeTotal) : ($homeTotal == $awayTotal ? "0" : "-" . ($homeTotal - $awayTotal))
      ]; 


      return $data;
    }

    public function getFormattedFullTimeAttribute()
    {
        return Carbon::parse($this->datetime_utc, 'UTC')->setTimeZone('Australia/Sydney')->format('M j g:i a');
    }

    public function getEventsSortedAttribute()
    {
        $events =  $this->events ?? [];
        $emptyEvent = [
           "type" => "-",
           "team" => "-",
           "player_id" => "-",
           "player" => "-",
           "min" => "-",
           "id" => "-",
           "home_score" => "-",
           "away_score" => "-"
        ];

        $sorted = [];

        if (!empty($events['overtime'])) {
          $ot = collect($events['overtime']['event'])->reverse();

          if (isset($ot['type'])) {
            $ot = [$ot];
          } else {
            $ot = $ot->values();
          }

          $sorted[] = [
            'name' => 'Overtime',
            'short' => 'OT',
            'events' => $ot,
            'start_name' => 'Start of Overtime',
            'end_name' => $this->status == 'After Over Time' ? 'End of Overtime' : '-',
          ];
        }

        if(!empty($events['fourthquarter'])) {
          $q4 = collect($events['fourthquarter']['event'])->reverse();

          if (isset($q4['type'])) {
            $q4 = [$q4];
          } else {
            $q4 = $q4->values();
          }

          $sorted[] = [
            'name' => '4th Quarter',
            'short' => '4th',
            'events' => $q4,
            'start_name' => 'Start of 4th Quarter',
            'end_name' =>  !empty($events['overtime']) ? 'End of 4th Quarter' : ($this->status == 'Final' ? 'Final' : '-'),
          ];
        } else {
          // must still show an empty entry
          // must show the final score
          if (!empty($this->events['thirdquarter'])) {
          $sorted[] = [
            'name' => '4th Quarter',
            'short' => '4th',
            'events' => [
              [...$emptyEvent, 'home_score' => $this->home_score, 'away_score' => $this->away_score]
            ],
            'start_name' => '',
            'end_name' =>  '4th Quarter'
          ];
          }
        }

        if (!empty($events['thirdquarter'])) {
          $q3 = collect($events['thirdquarter']['event'])->reverse();

          if (isset($q3['type'])) {
            $q3 = [$q3];
          } else {
            $q3 = $q3->values();
          }

          $sorted[] = [
            'name' => '3rd Quarter',
            'short' => '3rd',
            'events' => $q3,
            'start_name' => 'Start of 3rd Quarter',
            'end_name' => $events['fourthquarter'] ? 'End of 3rd Quarter' : (empty($events['fourthquarter']) ? 'Final' : ''),
          ];
        }

        if (!empty($events['secondquarter'])) {
          $q2 = collect($events['secondquarter']['event'])->reverse();

          if (isset($q2['type'])) {
            $q2 = [$q2];
          } else {
            $q2 = $q2->values();
          }

          $sorted[] = [
            'name' => '2nd Quarter',
            'short' => '2nd',
            'events' => $q2,
            'start_name' => 'Start of 2nd Quarter',
            'end_name' => !empty($events['thirdquarter']) ? 'End of 2nd Quarter' : '-',
          ];
        }

        if (!empty($events['firstquarter'])) {
          $q1 = collect($events['firstquarter']['event'])->reverse();

          if (isset($q1['type'])) {
            $q1 = [$q1];
          } else {
            $q1 = $q1->values();
          }

          $sorted[] = [
            'name' => '1st Quarter',
            'short' => '1st',
            'start_name' => 'Start of 1st Quarter',
            'end_name' => !empty($events['secondquarter']) ? 'End of 1st Quarter' : '',
            'events' => $q1
          ];
        }

        return $sorted;
    }

    public function getHasOtAttribute()
    {
        return $this->home_ot > 0 || $this->away_ot > 0;
    }

    public function getAwayTeamIdAttribute()
    {
        return $this->awayteam['id'];
    }

    public function getHomeTeamIdAttribute()
    {
        return $this->hometeam['id'];
    }

    public function getCurrentMatchAttribute()
    {
        return Carbon::parse($this->datetime_utc, 'UTC')->setTimeZone('Australia/Sydney')->isToday();
    }

    public function getHomeTeamStatsAttribute()
    {
        $homeStats =  $this->team_stats['hometeam'] ?? [];

        if (empty($homeStats)) return [];

        return [
            'yards' => $homeStats['yards']['total'] ?? 0,
            'turnovers' => $homeStats['turnovers']['total'] ?? 0,
            'rushings' => $homeStats['rushings']['total'] ?? 0,
            'passings' => $homeStats['passing']['total'] ?? 0,
            'first_downs' => $homeStats['first_downs']['total'] ?? 0,
            'possession' => $homeStats['posession']['total'] ?? 0,
            'penalties' => $homeStats['penalties']['total'] ?? 0,
        ];
    }

    public function getAwayTeamStatsAttribute()
    {
        $awayStats =  $this->team_stats['awayteam'] ?? [];

        if (empty($awayStats)) return [];

        return [
            'yards' => $awayStats['yards']['total'] ?? 0,
            'turnovers' => $awayStats['turnovers']['total'] ?? 0,
            'rushings' => $awayStats['rushings']['total'] ?? 0,
            'passings' => $awayStats['passing']['total'] ?? 0,
            'first_downs' => $awayStats['first_downs']['total'] ?? 0,
            'possession' => $awayStats['posession']['total'] ?? 0,
            'penalties' => $awayStats['penalties']['total'] ?? 0,
        ];
    }

    public function getHomeQ1Attribute()
    {
        return (int) $this->hometeam['q1'];
    }

    public function getHomeQ2Attribute()
    {
        return (int) $this->hometeam['q2'];
    }

    public function getHomeQ3Attribute()
    {
        return (int) $this->hometeam['q3'];
    }

    public function getHomeQ4Attribute()
    {
        return (int) $this->hometeam['q4'];
    }

    public function getHomeOtAttribute()
    {
        return (int) $this->hometeam['ot'];
    }

    public function getAwayQ1Attribute()
    {
        return (int) $this->awayteam['q1'];
    }

    public function getAwayQ2Attribute()
    {
        return (int) $this->awayteam['q2'];
    }

    public function getAwayQ3Attribute()
    {
        return (int) $this->awayteam['q3'];
    }

    public function getAwayQ4Attribute()
    {
        return (int) $this->awayteam['q4'];
    }

    public function getAwayOtAttribute()
    {
        return (int) $this->awayteam['ot'];
    }

    public function getAwayRushingStatsAttribute()
    {
        return $this->rushing['awayteam']['player'][0] ?? [];
    }

    public function getHomeRushingStatsAttribute()
    {
        return $this->rushing['hometeam']['player'][0] ?? [];
    }

    public function getHomeReceivingStatsAttribute()
    {
        return $this->receiving['hometeam']['player'][0] ?? [];
    }

    public function getAwayReceivingStatsAttribute()
    {
        return $this->receiving['awayteam']['player'][0] ?? [];
    }
    
    public function getAwayPassingStatsAttribute()
    {
        $awayTeam = $this->passing['awayteam']['player'] ?? [];
        $passingStats = [];

        if (isset($awayTeam['name'])) {
          $awayTeam = [$awayTeam]; 
        }
    
        
        foreach ($awayTeam as $player) {
            if (isset($player['yards']) && $player['yards'] > 0) {
                $passingStats[] = [
                    'name' => $player['name'],
                    'id' => $player['id'],
                    'yards' => $player['yards'],
                    'comp_att' => $player['comp_att'],
                    'touchdowns' => $player['passing_touch_downs'],
                    'interceptions' => $player['interceptions'],
                    'rating' => $player['rating'] ?? null
                ];
            }
        }
        
        return $passingStats[0] ?? [];
    }

public function getHomePassingStatsAttribute()
{
    $homeTeam = $this->passing['hometeam']['player'] ?? [];
    $passingStats = [];

    if (isset($homeTeam['name'])) {
       $homeTeam = [$homeTeam]; 
    }
    
    foreach ($homeTeam as $player) {
        if (isset($player['yards']) && $player['yards'] > 0) {

            $passingStats[] = [
                'name' => $player['name'],
                'id' => $player['id'],
                'yards' => $player['yards'],
                'comp_att' => $player['comp_att'],
                'touchdowns' => $player['passing_touch_downs'],
                'interceptions' => $player['interceptions'],
                'rating' => $player['rating'] ?? null
            ];
        }
    }
    
    return $passingStats[0] ?? [];
}


    public function getNflTeamAbbrieviation($teamId)
    {
        $teams = '[
            {
              "ID": 1696,
              "Name": "Arizona Cardinals",
              "Abbreviation": "ARI",
              "Conference": "NFC",
              "Division": "West"
            },
            {
              "ID": 1690,
              "Name": "Atlanta Falcons",
              "Abbreviation": "ATL",
              "Conference": "NFC",
              "Division": "South"
            },
            {
              "ID": 1683,
              "Name": "Baltimore Ravens",
              "Abbreviation": "BAL",
              "Conference": "AFC",
              "Division": "North"
            },
            {
              "ID": 1689,
              "Name": "Buffalo Bills",
              "Abbreviation": "BUF",
              "Conference": "AFC",
              "Division": "East"
            },
            {
              "ID": 1684,
              "Name": "Carolina Panthers",
              "Abbreviation": "CAR",
              "Conference": "NFC",
              "Division": "South"
            },
            {
              "ID": 1703,
              "Name": "Chicago Bears",
              "Abbreviation": "CHI",
              "Conference": "NFC",
              "Division": "North"
            },
            {
              "ID": 1679,
              "Name": "Cincinnati Bengals",
              "Abbreviation": "CIN",
              "Conference": "AFC",
              "Division": "North"
            },
            {
              "ID": 1699,
              "Name": "Cleveland Browns",
              "Abbreviation": "CLE",
              "Conference": "AFC",
              "Division": "North"
            },
            {
              "ID": 1680,
              "Name": "Dallas Cowboys",
              "Abbreviation": "DAL",
              "Conference": "NFC",
              "Division": "East"
            },
            {
              "ID": 1708,
              "Name": "Denver Broncos",
              "Abbreviation": "DEN",
              "Conference": "AFC",
              "Division": "West"
            },
            {
              "ID": 1695,
              "Name": "Detroit Lions",
              "Abbreviation": "DET",
              "Conference": "NFC",
              "Division": "North"
            },
            {
              "ID": 1698,
              "Name": "Green Bay Packers",
              "Abbreviation": "GB",
              "Conference": "NFC",
              "Division": "North"
            },
            {
              "ID": 1697,
              "Name": "Houston Texans",
              "Abbreviation": "HOU",
              "Conference": "AFC",
              "Division": "South"
            },
            {
              "ID": 1706,
              "Name": "Indianapolis Colts",
              "Abbreviation": "IND",
              "Conference": "AFC",
              "Division": "South"
            },
            {
              "ID": 1687,
              "Name": "Jacksonville Jaguars",
              "Abbreviation": "JAX",
              "Conference": "AFC",
              "Division": "South"
            },
            {
              "ID": 1691,
              "Name": "Kansas City Chiefs",
              "Abbreviation": "KC",
              "Conference": "AFC",
              "Division": "West"
            },
            {
              "ID": 1692,
              "Name": "Miami Dolphins",
              "Abbreviation": "MIA",
              "Conference": "AFC",
              "Division": "East"
            },
            {
              "ID": 1701,
              "Name": "Minnesota Vikings",
              "Abbreviation": "MIN",
              "Conference": "NFC",
              "Division": "North"
            },
            {
              "ID": 1681,
              "Name": "New England Patriots",
              "Abbreviation": "NE",
              "Conference": "AFC",
              "Division": "East"
            },
            {
              "ID": 1682,
              "Name": "New Orleans Saints",
              "Abbreviation": "NO",
              "Conference": "NFC",
              "Division": "South"
            },
            {
              "ID": 1710,
              "Name": "New York Giants",
              "Abbreviation": "NYG",
              "Conference": "NFC",
              "Division": "East"
            },
            {
              "ID": 1709,
              "Name": "New York Jets",
              "Abbreviation": "NYJ",
              "Conference": "AFC",
              "Division": "East"
            },
            {
              "ID": 5566,
              "Name": "Las Vegas Raiders",
              "Abbreviation": "LV",
              "Conference": "AFC",
              "Division": "West"
            },
            {
              "ID": 1686,
              "Name": "Philadelphia Eagles",
              "Abbreviation": "PHI",
              "Conference": "NFC",
              "Division": "East"
            },
            {
              "ID": 1694,
              "Name": "Pittsburgh Steelers",
              "Abbreviation": "PIT",
              "Conference": "AFC",
              "Division": "North"
            },
            {
              "ID": 1702,
              "Name": "Los Angeles Chargers",
              "Abbreviation": "LAC",
              "Conference": "AFC",
              "Division": "West"
            },
            {
              "ID": 1707,
              "Name": "San Francisco 49ers",
              "Abbreviation": "SF",
              "Conference": "NFC",
              "Division": "West"
            },
            {
              "ID": 1704,
              "Name": "Seattle Seahawks",
              "Abbreviation": "SEA",
              "Conference": "NFC",
              "Division": "West"
            },
            {
              "ID": 5117,
              "Name": "Los Angeles Rams",
              "Abbreviation": "LAR",
              "Conference": "NFC",
              "Division": "West"
            },
            {
              "ID": 1693,
              "Name": "Tampa Bay Buccaneers",
              "Abbreviation": "TB",
              "Conference": "NFC",
              "Division": "South"
            },
            {
              "ID": 1705,
              "Name": "Tennessee Titans",
              "Abbreviation": "TEN",
              "Conference": "AFC",
              "Division": "South"
            },
            {
              "ID": 5753,
              "Name": "Washington Commanders",
              "Abbreviation": "WAS",
              "Conference": "NFC",
              "Division": "East"
            }
        ]'; 

        $teams = collect(json_decode($teams, true));
        
        return $teams->where('ID', $teamId)->first();
    }

    public function getHomeScoreAttribute()
    {
        return (int) $this->hometeam['totalscore'];
    }

    public function getAwayScoreAttribute()
    {
        return (int) $this->awayteam['totalscore'];
    }

    public function getAwayTeamShortNameAttribute()
    {
        return $this->getNflTeamAbbrieviation($this->awayteam['id'])['Abbreviation'];
    }

    public function getHomeTeamShortNameAttribute()
    {
        return $this->getNflTeamAbbrieviation($this->hometeam['id'])['Abbreviation'];
    }

    public function getAwayTeamNameAttribute()
    {
        return $this->awayteam['name'];
    }

    public function getHomeTeamNameAttribute()
    {
        return $this->hometeam['name'];
    }

    public function getAwayTeamImageNameAttribute()
    {
        return str_replace(' ', '_', $this->awayteam['name']);
    }

    public function getHomeTeamImageNameAttribute()
    {
        return str_replace(' ', '_', $this->hometeam['name']);
    }

    public static function getTeamSchedule($season, $seasonType)
    {
        return static::where([
            'season' => $season,
            'season_type_id' => $seasonType
        ])->get();
    }

    public static function getCurrentScheduledGames($season)
    {
        $data = static::where([
            'season' => $season,
        ])->get();

        $filtered =  $data->groupBy('season_type_id')->map(function($matches) {
            $matchesWithActualDate = $matches->map(function($match){

                $date = Carbon::createFromFormat('d.m.Y', $match->formatted_date)->format('Y-m-d');
                $match['actual_date'] = $date;

                return $match;
            });

            $minDate = $matchesWithActualDate->min('actual_date');

            $checkDate = Carbon::parse(date('Y-m-d'));

            if ($checkDate->lte($minDate)) {

                return $matchesWithActualDate;
            }
        });


        if ($filtered->count() > 0) return $filtered->first();

        return [];
    }

    public static function getGamesBySeason($season)
    {
        return static::where([
            'season' => $season,
        ])->get();

    }
}
