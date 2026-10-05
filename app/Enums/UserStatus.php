<?php

namespace App\Enums;

enum UserStatus: string
{
    case Active = 'active';
    /** Blocked by an administrator; cannot sign in. */
    case Suspended = 'suspended';
    /** Closed at the user's request (SRS 111); the record is retained. */
    case Deactivated = 'deactivated';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
