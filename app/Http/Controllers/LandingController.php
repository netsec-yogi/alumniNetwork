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
        $title = 'ABV-IIITM Gwalior Alumni Network';
        $description = 'Reconnect with ABV-IIITM Gwalior alumni worldwide — find classmates and mentors, join chapters and events, and stay part of the institute’s story.';

        return Inertia::render('Welcome', [
            ...$landing->payload(),
            // Rendered into <head> server-side (app.blade.php), so crawlers and link previews see it.
            'seo' => [
                'title' => $title,
                'description' => $description,
                'canonical' => route('home'),
                'image' => url('/images/og-alumni.png'),
            ],
        ]);
    }
}
