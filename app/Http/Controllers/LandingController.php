<?php

namespace App\Http\Controllers;

use App\Services\LandingPageService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** The public landing page. Content comes only from LandingPageService's public payload. */
class LandingController extends Controller
{
    public function __invoke(Request $request, LandingPageService $landing): Response
    {
        return Inertia::render('Welcome', self::props($landing->payload()));
    }

    /** Page props for a payload; the admin preview renders the same page from a draft payload. */
    public static function props(array $payload): array
    {
        return [
            ...$payload,
            // Rendered into <head> server-side (app.blade.php), so crawlers and link previews see it.
            'seo' => [
                'title' => $payload['copy']['landing.seo.title'],
                'description' => $payload['copy']['landing.seo.description'],
                'canonical' => route('home'),
                'image' => url('/images/og-alumni.png'),
            ],
        ];
    }
}
