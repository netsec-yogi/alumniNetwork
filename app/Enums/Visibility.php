<?php

namespace App\Enums;

/** Who may see a profile field (SRS 22). Ordered from most to least open. */
enum Visibility: string
{
    case Public = 'public';
    case Alumni = 'alumni';
    case Connections = 'connections';
    case Private = 'private';

    public function label(): string
    {
        return match ($this) {
            self::Public => 'Public',
            self::Alumni => 'Alumni only',
            self::Connections => 'Connections only',
            self::Private => 'Only me',
        };
    }
}
