<?php

namespace App\Enums;

/** SRS 35. */
enum EventType: string
{
    case AlumniMeet = 'alumni_meet';
    case Reunion = 'reunion';
    case Webinar = 'webinar';
    case Lecture = 'lecture';
    case Career = 'career';
    case Chapter = 'chapter';
    case Networking = 'networking';
    case Sports = 'sports';
    case Cultural = 'cultural';
    case Fundraising = 'fundraising';

    public function label(): string
    {
        return match ($this) {
            self::AlumniMeet => 'Alumni meet',
            self::Reunion => 'Reunion',
            self::Webinar => 'Webinar',
            self::Lecture => 'Guest lecture',
            self::Career => 'Career event',
            self::Chapter => 'Chapter event',
            self::Networking => 'Networking',
            self::Sports => 'Sports',
            self::Cultural => 'Cultural',
            self::Fundraising => 'Fundraising',
        };
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $t) => ['value' => $t->value, 'label' => $t->label()], self::cases());
    }
}
