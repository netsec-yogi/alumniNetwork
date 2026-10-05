<?php

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/** Server-rendered SVG QR codes (no inline styles, so CSP-safe). */
class QrCode
{
    public static function svg(string $content, int $size = 220): string
    {
        $svg = (new Writer(new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd)))->writeString($content);

        // Drop the XML prolog so it can be embedded inline.
        return trim(substr($svg, strpos($svg, '<svg')));
    }
}
