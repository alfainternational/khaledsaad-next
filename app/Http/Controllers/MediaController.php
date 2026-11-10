<?php

namespace App\Http\Controllers;

use App\Domain\Lab\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * تقديم ملف مخزّن (plan 2026-11-09 البند 2). One route, two readers, and the row decides which:
 *
 *   - a file belonging to an asset its owner published by link is public, exactly as that asset's page is;
 *   - everything else needs a member of the owning workspace.
 *
 * This is why media URLs inside a saved Markdown asset point here and not at the bucket: a signed bucket link expires
 * and breaks the asset, and an unsigned one never expires and breaks the privacy. The row is checked on every read.
 */
class MediaController extends Controller
{
    public function show(Request $request, Media $media): StreamedResponse
    {
        abort_unless($this->mayRead($request, $media), 404);

        $disk = Storage::disk($media->disk);
        abort_unless($disk->exists($media->path), 404);

        return $disk->response($media->path, null, [
            'Content-Type' => $media->mime,
            // The bytes never change under a path (every store writes a new uuid), so this is safe to cache hard.
            'Cache-Control' => 'private, max-age=86400',
            'X-Robots-Tag' => 'noindex',
        ]);
    }

    private function mayRead(Request $request, Media $media): bool
    {
        $output = $media->output;
        if ($output && $output->share_token !== null) {
            return true;
        }
        if (! $request->user() || ! $media->workspace_id) {
            return false;
        }

        return $request->user()->workspaces()->whereKey($media->workspace_id)->exists();
    }
}
