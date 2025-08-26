<?php

namespace App\Models;

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
            "venu_id",
           "venu_name"
    ];

    protected $casts = [
        'fumbles' => 'array',
        'punt_returns' => 'array',
        'punting' => 'array',
        'awayteam' => 'array',
        'hometeam' => 'array',
        'defensive' => 'array',
        'events' => 'array',
        'interceptions' => 'array',
        'kick_returns' => 'array',
        'kicking' => 'array',
        'passing' => 'array',
        'receiving' => 'array',
        'rushing' => 'array',
        // add other JSON columns as needed
    ];
}
