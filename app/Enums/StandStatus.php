<?php

namespace App\Enums;

enum StandStatus: string
{
    case Available = 'available';
    case Sold = 'sold';
    case InProgress = 'in-progress';
}
