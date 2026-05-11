<?php

namespace App\Enums;

enum ScheduleStatus: string
{
    case Open      = 'open';
    case Full      = 'full';
    case Cancelled = 'cancelled';
}
