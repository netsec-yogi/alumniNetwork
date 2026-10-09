<?php

namespace App\Http\Controllers;

use App\Models\StoredFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Serves stored files after an authorisation check; storage paths are never exposed (SRS 72). */
class FileController extends Controller
{
    public function show(Request $request, StoredFile $file, ?string $variant = null): StreamedResponse
    {
        $this->authorize('view', $file);

        $path = $variant === 'thumb' && $file->thumb_path ? $file->thumb_path : $file->path;
        abort_unless(Storage::disk('local')->exists($path), 404);

        $disposition = $file->isImage() ? 'inline' : 'attachment';

        return Storage::disk('local')->response($path, $file->isImage() ? null : $file->original_name, [
            'Content-Type' => $file->mime,
            'X-Content-Type-Options' => 'nosniff',
            // Even if a file were somehow active content, it gets no origin.
            // (Sanitised SVG logos may style themselves; styles can't run script.)
            'Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; ".($file->mime === 'image/svg+xml' ? "style-src 'unsafe-inline'; " : '').'sandbox',
            'Cache-Control' => $file->visibility === StoredFile::PUBLIC ? 'public, max-age=86400' : 'private, max-age=3600',
        ], $disposition);
    }
}
