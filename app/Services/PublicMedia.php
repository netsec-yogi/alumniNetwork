<?php

namespace App\Services;

use App\Models\AlumniProfile;
use App\Models\DistinguishedAlumnus;
use App\Models\Event;
use App\Models\EventPhoto;
use App\Models\StoredFile;
use App\Models\Story;
use App\Models\StoryImage;

/**
 * Which non-public files may nevertheless be shown to anyone, because of
 * what they currently belong to. Evaluated on every request, so access
 * follows the content's state (unpublish an event or news item, or stop
 * featuring an alumnus, and the images stop being served to visitors).
 *
 *  - official images of a published, public event;
 *  - images of a published news item / story;
 *  - the profile photo of an alumnus publicly featured on the landing page
 *    (a published distinguished-alumni honour or a published story about them),
 *    while that landing section is shown.
 */
class PublicMedia
{
    public function __construct(private readonly LandingPageService $landing) {}

    public function isPublic(StoredFile $file): bool
    {
        return match ($file->purpose) {
            'event_image' => EventPhoto::where('file_id', $file->id)->where('is_official', true)
                ->whereHas('event', fn ($q) => $q->published()->where('audience', Event::AUDIENCE_PUBLIC))->exists(),
            'news_image' => StoryImage::where('file_id', $file->id)->whereHas('story', fn ($q) => $q->published())->exists(),
            'profile_photo' => ($profile = AlumniProfile::where('photo_file_id', $file->id)->first()) !== null && $this->isFeatured($profile),
            default => false,
        };
    }

    /** Is this alumnus currently shown on the public landing page? */
    public function isFeatured(AlumniProfile $profile): bool
    {
        if (! $profile->isVerified()) {
            return false;
        }
        $sections = $this->landing->visibleSections();

        return (in_array('distinguished', $sections, true) && DistinguishedAlumnus::where('alumni_profile_id', $profile->id)->where('is_published', true)->exists())
            || (in_array('stories', $sections, true) && Story::published()->whereNotIn('type', Story::NEWS_TYPES)->where('alumni_profile_id', $profile->id)->exists());
    }
}
