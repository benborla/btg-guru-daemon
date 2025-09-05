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
        'current_match'
    ];

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
            'turnovers' => $homeStats['turnovers']['turnovers'] ?? 0,
            'first_downs' => $homeStats['first_downs']['total'] ?? 0,
            'possession' => $homeStats['posession']['total'] ?? 0,
        ];
    }

    public function getAwayTeamStatsAttribute()
    {
        $awayStats =  $this->team_stats['awayteam'] ?? [];

        if (empty($awayStats)) return [];

        return [
            'yards' => $awayStats['yards']['total'] ?? 0,
            'turnovers' => $awayStats['turnovers']['turnovers'] ?? 0,
            'first_downs' => $awayStats['first_downs']['total'] ?? 0,
            'possession' => $awayStats['posession']['total'] ?? 0,
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
        return $this->awayteam['ot'];
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
        
        foreach ($awayTeam as $player) {
            if (isset($player['yards']) && $player['yards'] > 0) {
                $passingStats[] = [
                    'name' => $player['name'],
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
    
    foreach ($homeTeam as $player) {
        if (isset($player['yards']) && $player['yards'] > 0) {
            $passingStats[] = [
                'name' => $player['name'],
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
