<?php

namespace App\Enums;

enum BookingStatus: string
{
    case Pending           = 'pending';
    case Confirmed         = 'confirmed';
    case Completed         = 'completed';
    case Rejected          = 'rejected';
    case Expired           = 'expired';
    case MemberCancelled   = 'member_cancelled';
    case ProviderCancelled = 'provider_cancelled';
}
