<?php

namespace App\Services\Content;

use App\Services\LandingPageService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Editable text of the public landing page, keyed by stable names such as
 * "landing.hero.title". The defaults below are the page's original text, so
 * the page reads correctly with nothing configured.
 *
 * Types: "text" (one line), "textarea" (plain paragraph) and "rich" (a small
 * Markdown subset: bold, italic, links, lists, paragraphs). Rich text is
 * rendered and sanitised here, on the server; raw HTML is stripped and only
 * an allow-list of tags survives. Required fields never render empty: a
 * blank value falls back to the default.
 */
class LandingCopy extends PublishableSettings
{
    public const SECTIONS = [
        'hero' => 'Hero',
        'stats' => 'Statistics',
        'community' => 'Community',
        'events' => 'Events',
        'news' => 'News & announcements',
        'distinguished' => 'Distinguished alumni',
        'stories' => 'Alumni stories',
        'gallery' => 'Gallery',
        'chapters' => 'Chapters',
        'network' => 'Global network',
        'join' => 'Join',
        'support' => 'Support / giving',
        'footer' => 'Footer',
        'seo' => 'Search & sharing',
    ];

    private const RICH_TAGS = '<p><br><strong><em><a><ul><ol><li>';

    private const CACHE_KEY = 'landing.copy.v1';

    public function area(): string
    {
        return 'landing_copy';
    }

    protected function auditPrefix(): string
    {
        return 'landing_content';
    }

    /**
     * key => [section, label, type, default, required, max, hint]
     *
     * @return array<string, array{section: string, label: string, type: string, default: string, required: bool, max: int, hint: ?string}>
     */
    public static function fields(): array
    {
        static $fields = null;
        if ($fields !== null) {
            return $fields;
        }

        $f = fn (string $section, string $label, string $default, string $type = 'text', bool $required = true, ?int $max = null, ?string $hint = null) => compact('section', 'label', 'type', 'default', 'required', 'hint') + ['max' => $max ?? match ($type) {
            'text' => 120,
            'textarea' => 400,
            default => 1200,
        }];
        $heading = fn (string $s, string $eyebrow, string $title, ?string $button, string $emptyTitle, string $emptyText) => array_filter([
            "landing.{$s}.eyebrow" => $f($s, 'Eyebrow (small label above the title)', $eyebrow, required: false),
            "landing.{$s}.title" => $f($s, 'Title', $title),
            "landing.{$s}.subtitle" => $f($s, 'Subtitle', '', 'textarea', false, hint: 'Optional. Shown under the title.'),
            "landing.{$s}.button" => $button === null ? null : $f($s, 'Button text', $button, max: 40),
            "landing.{$s}.empty_title" => $f($s, 'Message when there is nothing to show: title', $emptyTitle),
            "landing.{$s}.empty_text" => $f($s, 'Message when there is nothing to show: text', $emptyText, 'textarea'),
        ]);

        $features = [
            ['Connect with alumni', 'Rediscover batchmates and grow your network across every programme and year.'],
            ['Find classmates', 'Search by batch, programme, company or city — with privacy each member controls.'],
            ['Find mentors', 'Get matched with alumni who have walked the path you’re on.'],
            ['Career opportunities', 'Jobs, internships and referrals shared by people who sat in the same classrooms.'],
            ['Alumni discussions', 'A feed and groups for questions, wins and conversations that matter.'],
            ['Events', 'Reunions, webinars, campus visits and meetups near you.'],
            ['Chapters', 'City and country chapters that keep the IIITM spirit local.'],
            ['Knowledge sharing', 'Guest lectures, research collaboration and talks for students.'],
            ['Startups', 'Discover and back ventures founded by fellow alumni.'],
        ];
        $featureFields = [];
        foreach ($features as $i => [$title, $body]) {
            $n = $i + 1;
            $featureFields["landing.community.feature_{$n}_title"] = $f('community', "Feature {$n}: title", $title, max: 60);
            $featureFields["landing.community.feature_{$n}_text"] = $f('community', "Feature {$n}: text", $body, 'textarea', max: 200);
        }
        $areas = ['Scholarships', 'Support students', 'Research', 'Infrastructure', 'Mentorship', 'Entrepreneurship', 'Industry connect'];
        $areaFields = [];
        foreach ($areas as $i => $label) {
            $n = $i + 1;
            $areaFields["landing.support.area_{$n}"] = $f('support', "Support area {$n}", $label, max: 40);
        }

        return $fields = [
            'landing.hero.badge' => $f('hero', 'Badge above the title', 'ABV-IIITM Gwalior · Alumni Association', required: false, max: 80),
            'landing.hero.title' => $f('hero', 'Title', 'Where memories connect.', max: 80),
            'landing.hero.subtitle' => $f('hero', 'Subtitle (highlighted second line)', 'Where alumni thrive.', required: false, max: 80),
            'landing.hero.description' => $f('hero', 'Description', 'One home for every IIITM graduate — reconnect with your batch, find mentors and opportunities, meet up across chapters worldwide, and keep the institute’s story going.', 'rich', max: 600),
            'landing.hero.primary_button' => $f('hero', 'Primary button (visitors)', 'Join Alumni Network', max: 40),
            'landing.hero.primary_button_member' => $f('hero', 'Primary button (signed-in members)', 'Open your network', max: 40),
            'landing.hero.secondary_button' => $f('hero', 'Secondary button', 'Explore Alumni', max: 40),

            'landing.stats.title' => $f('stats', 'Section name', 'Alumni in numbers', max: 60, hint: 'Read out by screen readers; the statistics row has no visible heading.'),

            'landing.community.eyebrow' => $f('community', 'Eyebrow (small label above the title)', 'More than a directory', required: false),
            'landing.community.title' => $f('community', 'Title', 'A social network built for IIITM alumni'),
            'landing.community.subtitle' => $f('community', 'Subtitle', 'Everything you need to stay close to your people and give back — in one place.', 'textarea', false),
            ...$featureFields,

            ...$heading('events', 'Save the date', 'Upcoming events', 'All events', 'New events are on the way', 'Reunions, webinars and chapter meets are announced here first — check back soon, or join to get notified.'),
            ...$heading('news', 'From the alumni office', 'News & announcements', 'All news', 'No news yet', 'Announcements and institute updates will appear here as they’re published.'),
            ...$heading('distinguished', 'Hall of fame', 'Distinguished alumni', 'All honourees', 'Honourees coming soon', 'The alumni association’s distinguished alumni will be celebrated here.'),
            ...$heading('stories', 'Journeys', 'Alumni stories', 'All stories', 'Stories are being written', 'Interviews and journeys of IIITM alumni will be featured here.'),
            ...$heading('gallery', 'Moments', 'Photo gallery', null, 'Photos coming soon', 'Pictures from alumni meets, reunions and campus events will be shared here.'),
            ...$heading('chapters', 'Find your people, wherever you are', 'Alumni chapters', 'View all chapters', 'Chapters launching soon', 'City and country chapters bring IIITM alumni together locally.'),

            'landing.network.eyebrow' => $f('network', 'Eyebrow (small label above the title)', 'Global network', required: false),
            'landing.network.title' => $f('network', 'Title', 'IIITM alumni, all over the map'),
            'landing.network.description' => $f('network', 'Description', 'Wherever you land, there’s likely a batchmate nearby. Counts are aggregates of verified alumni — no one’s location is ever shown individually.', 'rich', false, 600),

            'landing.join.title' => $f('join', 'Title', 'Your ABV-IIITM journey doesn’t end at graduation.'),
            'landing.join.description' => $f('join', 'Description', 'Verify once with your roll number and unlock your batch, mentors, jobs and events.', 'rich', false, 600),
            'landing.join.primary_button' => $f('join', 'Primary button', 'Join Alumni Network', max: 40),
            'landing.join.secondary_button' => $f('join', 'Second button', 'Create profile', max: 40),
            'landing.join.signin_button' => $f('join', 'Sign-in button', 'Sign in', max: 40),
            'landing.join.explore_link' => $f('join', 'Explore link', 'Explore alumni', max: 40),

            'landing.support.eyebrow' => $f('support', 'Eyebrow (small label above the title)', 'Support your alma mater', required: false),
            'landing.support.title' => $f('support', 'Title', 'Give back. Inspire forward.'),
            'landing.support.description' => $f('support', 'Description', 'Every contribution funds the next generation of IIITM students. Gifts are receipted (80G where eligible) and processed through a secure hosted checkout.', 'rich', false, 800),
            'landing.support.primary_button' => $f('support', 'Primary button', 'Give now', max: 40),
            'landing.support.secondary_button' => $f('support', 'Secondary button', 'See campaigns', max: 40),
            ...$areaFields,

            'landing.footer.copyright' => $f('footer', 'Copyright line', '© {year} ABV-IIITM Gwalior Alumni Association', max: 160, hint: '{year} is replaced with the current year.'),
            'landing.footer.text' => $f('footer', 'Footer text', '', 'textarea', false, hint: 'Optional. A short line such as the office address.'),

            'landing.seo.title' => $f('seo', 'Page title (browser tab and search results)', 'ABV-IIITM Gwalior Alumni Network', max: 70),
            'landing.seo.description' => $f('seo', 'Description for search results and link previews', 'Reconnect with ABV-IIITM Gwalior alumni worldwide — find classmates and mentors, join chapters and events, and stay part of the institute’s story.', 'textarea', max: 300),
        ];
    }

    public function defaults(): array
    {
        return array_map(fn ($f) => $f['default'], self::fields());
    }

    protected function normalize(array $values): array
    {
        $out = [];
        foreach (self::fields() as $key => $field) {
            $value = $values[$key] ?? $field['default'];
            $value = is_scalar($value) ? (string) $value : $field['default'];
            // No control characters; one line for "text" fields.
            $value = preg_replace($field['type'] === 'text' ? '/[\x00-\x1F\x7F]+/u' : '/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]+/u', $field['type'] === 'text' ? ' ' : '', $value) ?? '';
            $out[$key] = Str::substr(trim(str_replace("\r\n", "\n", $value)), 0, $field['max']);
        }

        return $out;
    }

    protected function flushPublished(): void
    {
        Cache::forget(self::CACHE_KEY);
        app(LandingPageService::class)->flush();
    }

    /**
     * Ready for the page: required blanks filled from defaults, rich text
     * rendered to sanitised HTML, {year} substituted.
     *
     * @return array<string, string>
     */
    public function forPage(bool $draft = false): array
    {
        if ($draft) {
            return $this->render($this->draft());
        }

        return Cache::rememberForever(self::CACHE_KEY, fn () => $this->render($this->live()));
    }

    /** @return array<string, string> */
    private function render(array $values): array
    {
        $out = [];
        foreach (self::fields() as $key => $field) {
            $value = $values[$key] ?? '';
            if ($value === '' && $field['required']) {
                $value = $field['default'];
            }
            $value = str_replace('{year}', now()->format('Y'), $value);
            $out[$key] = $field['type'] === 'rich' && $value !== '' ? self::richHtml($value) : $value;
        }

        return $out;
    }

    /** Markdown subset → sanitised HTML. Raw HTML is stripped, unsafe links dropped, tags allow-listed. */
    public static function richHtml(string $markdown): string
    {
        $html = Str::markdown($markdown, ['html_input' => 'strip', 'allow_unsafe_links' => false, 'max_nesting_level' => 5]);
        $html = strip_tags($html, self::RICH_TAGS);
        // Links open safely in a new tab; nothing else carries attributes.
        $html = preg_replace_callback('/<a\b[^>]*>/i', function ($m) {
            preg_match('/href="([^"]*)"/i', $m[0], $href);
            $url = html_entity_decode($href[1] ?? '', ENT_QUOTES);

            return preg_match('#^(https?://|mailto:|/)#i', $url)
                ? '<a href="'.e($url).'" rel="noopener nofollow" target="_blank">'
                : '<a>';
        }, $html) ?? '';
        $html = preg_replace('/<(p|strong|em|ul|ol|li)\b[^>]*>/i', '<$1>', $html) ?? '';

        return trim($html);
    }
}
