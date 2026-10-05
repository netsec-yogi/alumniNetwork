<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['consent_type', 'version', 'granted', 'ip_address', 'user_agent'])]
class Consent extends Model
{
    public const UPDATED_AT = null;

    public const TERMS = 'terms';

    public const PRIVACY = 'privacy_policy';

    public const COMMUNICATIONS = 'communications';

    protected function casts(): array
    {
        return ['granted' => 'boolean'];
    }
}
