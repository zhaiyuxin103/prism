<?php

namespace App\Enums;

enum CreditEntryType: string
{
    case Purchase = 'purchase';
    case Reservation = 'reservation';
    case Settlement = 'settlement';
}
