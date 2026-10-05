<?php

namespace App\Enums;

/** Alumni verification states (SRS 20). */
enum VerificationStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Rejected = 'rejected';
    case Suspended = 'suspended';
    case Archived = 'archived';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
