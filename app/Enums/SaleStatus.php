<?php

namespace App\Enums;

enum SaleStatus: string
{
    /** Sold, but the buyer still owes money. */
    case Outstanding = 'outstanding';

    /** Paid in full. */
    case Settled = 'settled';
}
