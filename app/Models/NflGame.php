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
        'rushing' => 'array'
    ];

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

        $filtered =  $data->groupBy('week')->map(function($matches) {
            $matchesWithActualDate = $matches->map(function($match){

                $date = Carbon::createFromFormat('d.m.Y', $match->formatted_date)->format('Y-m-d');
                $match['actual_date'] = $date;

                return $match;
            });

            $minDate = $matchesWithActualDate->min('actual_date');
            $maxDate = $matchesWithActualDate->max('actual_date');

            $checkDate = Carbon::parse(date('Y-m-d'));

            if ($checkDate->between($minDate, $maxDate)) {

                return $matchesWithActualDate;
            }
        })->filter();

        if ($filtered->count() > 0) return $filtered->first();

        return [];
    }
}
