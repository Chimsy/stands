<?php

namespace App\Enums;

enum SaleType: string
{
    /** Settled in full on the day of sale. */
    case Cash = 'cash';

    /** A deposit followed by equal monthly instalments. */
    case PaymentPlan = 'payment-plan';
}
