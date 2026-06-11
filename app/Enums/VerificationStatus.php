<?php

namespace App\Enums;

enum VerificationStatus: string
{
    case Unsubmitted = 'unsubmitted';
    case Pending     = 'pending';
    case Approved    = 'approved';
    case Rejected    = 'rejected';

    public const VALID_TRANSITIONS = [
        'unsubmitted' => ['pending'],
        'pending'     => ['approved', 'rejected'],
        'approved'    => ['rejected'], // 撤銷驗證，原因必填
        'rejected'    => ['pending'],  // 重新送審
    ];

    public function canTransitionTo(self $newStatus): bool
    {
        return in_array($newStatus->value, self::VALID_TRANSITIONS[$this->value] ?? []);
    }
}
