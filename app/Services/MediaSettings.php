<?php

namespace App\Services;

use App\Models\SiteSetting;

/**
 * Admin-configurable image size limits (KB) for optimised uploads. The
 * upload pipeline compresses images to fit these; values are clamped to
 * safe bounds so a typo can't disable the limit or exceed server limits.
 */
class MediaSettings
{
    public const DEFAULTS = ['event_image_kb' => 300, 'news_image_kb' => 300, 'profile_photo_kb' => 200];

    public const LABELS = ['event_image_kb' => 'Event image', 'news_image_kb' => 'News image', 'profile_photo_kb' => 'Profile photo'];

    public const MIN_KB = 50;

    public const MAX_KB = 2048;

    /** @return array<string, int> */
    public function all(): array
    {
        $saved = SiteSetting::get('media', []);

        return collect(self::DEFAULTS)->map(fn ($default, $key) => $this->clamp((int) ($saved[$key] ?? $default)))->all();
    }

    public function limit(string $key): int
    {
        return $this->all()[$key] ?? throw new \InvalidArgumentException("Unknown media limit {$key}");
    }

    /** @param  array<string, int>  $values */
    public function save(array $values): void
    {
        SiteSetting::put('media', collect(self::DEFAULTS)->map(fn ($d, $k) => $this->clamp((int) ($values[$k] ?? $d)))->all());
    }

    private function clamp(int $kb): int
    {
        return max(self::MIN_KB, min(self::MAX_KB, $kb));
    }
}
