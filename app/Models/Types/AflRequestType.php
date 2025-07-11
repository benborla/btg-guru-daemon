<?php

namespace App\Models\Types;

enum AflRequestType
{
    case Live;
    case Schedules;
    case Standings;
    case Record;
}
