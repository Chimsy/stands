<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case BankTransfer = 'bank-transfer';
    case MobileMoney = 'mobile-money';
    case Card = 'card';
}
