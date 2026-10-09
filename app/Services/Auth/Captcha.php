<?php

namespace App\Services\Auth;

use Illuminate\Contracts\Session\Session;

/**
 * Self-hosted image CAPTCHA (no third-party script, CSP unchanged). The
 * answer lives only as a keyed hash in the session, expires after five
 * minutes and is single-use: any check — right or wrong — consumes it, so
 * every attempt needs a fresh image.
 */
class Captcha
{
    private const KEY = 'captcha.challenge';

    private const TTL = 300;

    // No easily-confused characters (0/O, 1/I/L, 5/S, 2/Z, 8/B).
    private const ALPHABET = 'ACDEFGHJKMNPQRTUVWXY34679';

    public function __construct(private readonly Session $session) {}

    /** New challenge: stores its hash and returns the PNG bytes. */
    public function issue(): string
    {
        $answer = '';
        for ($i = 0; $i < 5; $i++) {
            $answer .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }
        $this->session->put(self::KEY, self::payload($answer));

        return $this->render($answer);
    }

    /** Checks and consumes the current challenge (case-insensitive, spaces ignored). */
    public function check(?string $input): bool
    {
        $stored = $this->session->pull(self::KEY);
        if (! is_array($stored) || ($stored['expires'] ?? 0) < time() || $input === null) {
            return false;
        }

        return hash_equals($stored['hash'], self::hash(strtoupper(preg_replace('/\s+/', '', $input))));
    }

    /** @return array{hash: string, expires: int} (also used by tests to plant a known answer) */
    public static function payload(string $answer): array
    {
        return ['hash' => self::hash(strtoupper($answer)), 'expires' => time() + self::TTL];
    }

    public static function sessionKey(): string
    {
        return self::KEY;
    }

    private static function hash(string $answer): string
    {
        return hash_hmac('sha256', 'captcha|'.$answer, (string) config('app.key'));
    }

    /** Distorted text: each glyph scaled, rotated and offset, over noise lines and dots. */
    private function render(string $answer): string
    {
        $w = 220;
        $h = 70;
        $img = imagecreatetruecolor($w, $h);
        imagefill($img, 0, 0, imagecolorallocate($img, 245, 245, 250));

        for ($i = 0; $i < 6; $i++) {
            $c = imagecolorallocate($img, random_int(150, 210), random_int(150, 210), random_int(170, 230));
            imagesetthickness($img, random_int(1, 2));
            imageline($img, random_int(0, $w), random_int(0, $h), random_int(0, $w), random_int(0, $h), $c);
        }

        foreach (str_split($answer) as $i => $char) {
            $glyph = imagecreatetruecolor(10, 16);
            imagefill($glyph, 0, 0, imagecolorallocate($glyph, 255, 255, 255));
            imagestring($glyph, 5, 1, 0, $char, imagecolorallocate($glyph, random_int(20, 90), random_int(20, 60), random_int(110, 170)));
            $big = imagecreatetruecolor(30, 48);
            imagecopyresampled($big, $glyph, 0, 0, 0, 0, 30, 48, 10, 16);
            $rot = imagerotate($big, random_int(-25, 25), imagecolorallocate($big, 255, 255, 255));
            imagecolortransparent($rot, imagecolorallocate($rot, 255, 255, 255));
            imagecopymerge($img, $rot, 18 + $i * 38 + random_int(-4, 4), random_int(2, 14), 0, 0, imagesx($rot), imagesy($rot), 100);
        }

        for ($i = 0; $i < 350; $i++) {
            imagesetpixel($img, random_int(0, $w - 1), random_int(0, $h - 1), imagecolorallocate($img, random_int(100, 200), random_int(100, 200), random_int(100, 200)));
        }

        ob_start();
        imagepng($img);

        return (string) ob_get_clean();
    }
}
