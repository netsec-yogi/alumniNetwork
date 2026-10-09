<?php

namespace App\Services;

use App\Models\AlumniProfile;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\DistinguishedAlumnus;
use App\Models\EngagementActivity;
use App\Models\Event;
use App\Models\GalleryItem;
use App\Models\SiteSetting;
use App\Models\Startup;
use App\Models\StoredFile;
use App\Models\Story;
use App\Services\Content\LandingCopy;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Everything the public landing page shows, built for a signed-out visitor.
 *
 * Privacy is enforced HERE, not in the page: every item is assembled field
 * by field from an allow-list (no models are serialised), only published /
 * public / approved records are queried, per-person fields honour the
 * owner's privacy settings for a guest viewer, and geography is published
 * only as aggregates above a minimum group size (k-anonymity), so no
 * individual can be located. No database IDs, emails, phones, online
 * meeting links or internal notes ever leave this class.
 */
class LandingPageService
{
    public const CACHE_KEY = 'landing.page.v1';

    /** Smallest group size published in geographic breakdowns. */
    public const MIN_GROUP = 3;

    public const SECTIONS = [
        'stats' => 'Alumni in numbers',
        'community' => 'The community',
        'events' => 'Upcoming events',
        'news' => 'News & announcements',
        'distinguished' => 'Distinguished alumni',
        'stories' => 'Alumni stories',
        'gallery' => 'Photo gallery',
        'chapters' => 'Alumni chapters',
        'network' => 'Global network',
        'join' => 'Join the network',
        'support' => 'Support your alma mater',
    ];

    public const STATS = [
        'alumni' => 'Verified alumni',
        'active' => 'Active this year',
        'countries' => 'Countries',
        'cities' => 'Cities',
        'companies' => 'Companies',
        'chapters' => 'Alumni chapters',
        'startups' => 'Alumni startups',
        'distinguished' => 'Distinguished alumni',
    ];

    public function __construct(private readonly ProfileVisibility $visibility) {}

    /** @return array{sections: list<array{key: string, label: string, visible: bool}>, stats: array<string, bool>} */
    public function settings(): array
    {
        $saved = SiteSetting::get('landing', []);
        $order = collect($saved['sections'] ?? [])->pluck('key')->merge(array_keys(self::SECTIONS))->unique()->filter(fn ($k) => isset(self::SECTIONS[$k]));
        $hidden = collect($saved['sections'] ?? [])->where('visible', false)->pluck('key')->all();

        return [
            'sections' => $order->map(fn ($k) => ['key' => $k, 'label' => self::SECTIONS[$k], 'visible' => ! in_array($k, $hidden, true)])->values()->all(),
            'stats' => collect(self::STATS)->mapWithKeys(fn ($l, $k) => [$k => (bool) ($saved['stats'][$k] ?? true)])->all(),
        ];
    }

    /** @return list<string> keys of the sections currently shown */
    public function visibleSections(): array
    {
        return collect($this->settings()['sections'])->where('visible', true)->pluck('key')->all();
    }

    public function saveSettings(array $sections, array $stats): void
    {
        SiteSetting::put('landing', [
            'sections' => collect($sections)->filter(fn ($s) => isset(self::SECTIONS[$s['key'] ?? '']))->map(fn ($s) => ['key' => $s['key'], 'visible' => (bool) $s['visible']])->values()->all(),
            'stats' => collect(self::STATS)->mapWithKeys(fn ($l, $k) => [$k => (bool) ($stats[$k] ?? false)])->all(),
        ]);
        $this->flush();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addMinutes(10), fn () => $this->build());
    }

    /** The draft text on the current content, never cached: for the admin preview only. */
    public function previewPayload(): array
    {
        return [...$this->build(), 'copy' => app(LandingCopy::class)->forPage(draft: true)];
    }

    /** @return array<string, mixed> */
    private function build(): array
    {
        $settings = $this->settings();
        $visible = collect($settings['sections'])->where('visible', true)->pluck('key')->all();
        $on = fn (string $key) => in_array($key, $visible, true);

        return [
            'sections' => $visible,
            'stats' => $on('stats') ? $this->stats($settings['stats']) : [],
            'events' => $on('events') ? $this->events() : [],
            'news' => $on('news') ? $this->stories(news: true) : [],
            'distinguished' => $on('distinguished') ? $this->distinguished() : [],
            'stories' => $on('stories') ? $this->stories(news: false) : [],
            'gallery' => $on('gallery') ? $this->gallery() : ['items' => [], 'categories' => []],
            'chapters' => $on('chapters') ? $this->chapters() : [],
            'network' => $on('network') ? $this->network() : ['countries' => [], 'other' => 0],
            'heroPhotos' => $this->heroPhotos(),
            'copy' => app(LandingCopy::class)->forPage(),
        ];
    }

    /** @return list<array{key: string, label: string, value: int}> */
    private function stats(array $enabled): array
    {
        $verified = AlumniProfile::verified();
        $values = [
            'alumni' => fn () => (clone $verified)->count(),
            'active' => fn () => EngagementActivity::where('activity_date', '>=', now()->subYear())->distinct()->count('alumni_profile_id'),
            'countries' => fn () => (clone $verified)->whereNotNull('country')->distinct()->count('country'),
            'cities' => fn () => (clone $verified)->whereNotNull('city')->distinct()->count('city'),
            'companies' => fn () => (clone $verified)->whereNotNull('company')->distinct()->count('company'),
            'chapters' => fn () => Community::where('kind', Community::KIND_CHAPTER)->count(),
            'startups' => fn () => Startup::has('founders')->count(),
            'distinguished' => fn () => DistinguishedAlumnus::where('is_published', true)->count(),
        ];

        // Only real, non-zero figures: nothing is invented to fill the row.
        return collect(self::STATS)
            ->filter(fn ($label, $key) => $enabled[$key] ?? false)
            ->map(fn ($label, $key) => ['key' => $key, 'label' => $label, 'value' => (int) $values[$key]()])
            ->filter(fn ($s) => $s['value'] > 0)
            ->values()->all();
    }

    /** @return list<array<string, mixed>> */
    private function events(): array
    {
        return Event::published()->upcoming()
            ->where('audience', Event::AUDIENCE_PUBLIC)
            ->with('featuredPhoto.file')
            ->orderByDesc('is_featured')->orderBy('starts_at')
            ->limit(6)->get()
            ->map(function (Event $e) {
                $full = $e->capacity !== null && $e->confirmedSeats() >= $e->capacity;

                return [
                    'slug' => $e->slug,
                    'title' => $e->title,
                    'category' => $e->type->label(),
                    'summary' => Str::limit((string) ($e->summary ?: strip_tags((string) $e->description)), 160),
                    'date' => $e->starts_at->format('D, j M Y'),
                    'time' => $e->starts_at->format('g:i A'),
                    'day' => $e->starts_at->format('j'),
                    'month' => $e->starts_at->format('M'),
                    // The online meeting link is for registrants only and is never sent here.
                    'where' => $e->is_online ? 'Online' : $e->venue,
                    'featured' => $e->is_featured,
                    // Only the featured gallery image; the full gallery loads on the event page.
                    'cover' => $e->featuredPhoto?->file->url(),
                    'registration' => match (true) {
                        $e->registration_opens_at?->isFuture() => 'opens_soon',
                        ! $e->registrationOpen() => 'closed',
                        $full => 'waitlist',
                        default => 'open',
                    },
                    'closes' => $e->registration_closes_at?->format('j M Y'),
                ];
            })->all();
    }

    /** @return list<array<string, mixed>> */
    private function stories(bool $news): array
    {
        return Story::published()
            ->when($news, fn ($q) => $q->whereIn('type', Story::NEWS_TYPES), fn ($q) => $q->whereNotIn('type', Story::NEWS_TYPES))
            ->with(['cover', 'featuredImage.file', 'author:id,name', 'profile.user:id,name', 'profile.photo', 'profile.programme:id,name,code'])
            ->orderByDesc('is_featured')->orderBy('display_order')->orderByDesc('published_at')
            ->limit($news ? 4 : 3)->get()
            ->map(function (Story $s) use ($news) {
                $profile = $s->profile?->isVerified() ? $s->profile : null;

                return [
                    'slug' => $s->slug,
                    'title' => $s->title,
                    'type' => Story::TYPES[$s->type] ?? $s->type,
                    'excerpt' => Str::limit((string) $s->excerpt, 220),
                    'date' => $s->published_at->format('j M Y'),
                    'featured' => $s->is_featured,
                    'cover' => $s->featuredImage?->file->url() ?? $this->publicUrl($s->cover),
                    // News carries a byline (staff author's name); stories carry the alumnus they feature.
                    'author' => $news ? $s->author?->name : null,
                    'alumnus' => $news || ! $profile ? null : [
                        'name' => $profile->displayName(),
                        'batch' => $profile->graduation_year,
                        'programme' => $profile->programme?->code,
                        'organization' => $this->visibility->canSee($profile, 'company', null) ? $profile->company : null,
                        // Featured in a published story: PublicMedia lets visitors load this photo.
                        'photo' => $profile->photo?->url(),
                    ],
                ];
            })->all();
    }

    /** @return list<array<string, mixed>> */
    private function distinguished(): array
    {
        return DistinguishedAlumnus::where('is_published', true)
            ->whereHas('profile', fn ($q) => $q->verified())
            ->with(['profile.user:id,name', 'profile.photo', 'profile.programme:id,name,code'])
            ->orderByDesc('is_featured')->orderBy('display_order')->orderByDesc('award_year')
            ->limit(8)->get()
            ->map(function (DistinguishedAlumnus $d) {
                $p = $d->profile;
                $see = fn (string $field) => $this->visibility->canSee($p, $field, null);

                return [
                    'name' => $p->displayName(),
                    'programme' => $p->programme?->name,
                    'batch' => $p->graduation_year,
                    'category' => DistinguishedAlumnus::CATEGORIES[$d->category] ?? $d->category,
                    'year' => $d->award_year,
                    'citation' => Str::limit((string) $d->citation, 180),
                    // Per-field privacy, evaluated for a signed-out viewer.
                    'designation' => $see('designation') ? $p->designation : null,
                    'organization' => $see('company') ? $p->company : null,
                    // A published honour features this alumnus publicly (see PublicMedia).
                    'photo' => $p->photo?->url(),
                ];
            })->all();
    }

    /** @return array{items: list<array<string, mixed>>, categories: array<string, string>} */
    private function gallery(): array
    {
        $items = GalleryItem::publiclyVisible()->with('file')
            ->orderByDesc('is_featured')->orderBy('display_order')->latest()
            ->limit(12)->get()
            ->map(fn (GalleryItem $g) => [
                'title' => $g->title,
                'caption' => $g->caption,
                'category' => $g->category,
                'thumb' => $g->file->url(true),
                'full' => $g->file->url(),
            ]);

        return [
            'items' => $items->all(),
            'categories' => collect(GalleryItem::CATEGORIES)->only($items->pluck('category')->unique()->all())->all(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function chapters(): array
    {
        return Community::where('kind', Community::KIND_CHAPTER)->where('show_on_landing', true)
            ->withCount(['memberships as members' => fn ($q) => $q->where('status', CommunityMember::ACTIVE)])
            ->withCount(['events as upcoming_events' => fn ($q) => $q->published()->upcoming()->where('audience', Event::AUDIENCE_PUBLIC)])
            ->orderByDesc('members')->limit(8)->get()
            ->map(fn (Community $c) => [
                'slug' => $c->slug,
                'name' => $c->name,
                'city' => $c->city,
                'country' => $c->country,
                'members' => $c->members,
                'upcoming_events' => $c->upcoming_events,
                'coordinator' => $c->coordinator_name,
            ])->all();
    }

    /**
     * Alumni per country, as aggregates only. Countries below MIN_GROUP are
     * folded into "other" so a lone alumnus abroad can't be singled out.
     *
     * @return array{countries: list<array{country: string, count: int}>, other: int}
     */
    private function network(): array
    {
        $byCountry = AlumniProfile::verified()->whereNotNull('country')
            ->selectRaw('country, count(*) as n')->groupBy('country')->orderByDesc('n')->pluck('n', 'country');

        $shown = $byCountry->filter(fn ($n) => $n >= self::MIN_GROUP)->take(10);

        return [
            'countries' => $shown->map(fn ($n, $country) => ['country' => (string) $country, 'count' => (int) $n])->values()->all(),
            'other' => (int) $byCountry->except($shown->keys()->all())->sum(),
        ];
    }

    /** @return list<string> a few featured public photos for the hero collage */
    private function heroPhotos(): array
    {
        return GalleryItem::publiclyVisible()->where('is_featured', true)->with('file')
            ->orderBy('display_order')->limit(4)->get()
            ->map(fn (GalleryItem $g) => $g->file->url())->all();
    }

    /** URL for a file only if it is explicitly public; never a member-only or private file. */
    private function publicUrl(?StoredFile $file): ?string
    {
        return $file?->visibility === StoredFile::PUBLIC ? $file->url() : null;
    }
}
