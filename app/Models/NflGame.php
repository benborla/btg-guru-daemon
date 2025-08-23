<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NflGame extends Model
{
    protected $fillable = [
        'attendance',
        'awayteam',
        'ball_on',
        'drive',
        'id',
        'name',
        'number',
        'ot',
        'q1',
        'q2',
        'q3',
        'q4',
        'totalscore',

    ];

    protected $casts = [
        'game_date' => 'datetime',
        'home_score' => 'integer',
        'away_score' => 'integer',
    ];
}
