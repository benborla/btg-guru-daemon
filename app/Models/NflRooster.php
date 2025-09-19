<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NflRooster extends Model
{
    protected $fillable = [
        'team_id',
        'id',
        'type',
        'name',
        'age',
        'college',
        'experience_years',
        'height',
        'number',
        'position',
        'salarycap',
        'weight',
    ];
}

