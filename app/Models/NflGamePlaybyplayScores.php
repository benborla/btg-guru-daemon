<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NflGamePlaybyplayScores extends Model
{
    //
    protected $table = 'nfl_game_playbyplay_scores';
    protected $fillable = [
        'contest_id',
        'response',
    ];

    protected $casts = [
        'response' => 'array',
    ];
}
