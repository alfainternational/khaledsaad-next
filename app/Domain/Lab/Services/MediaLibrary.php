<?php

namespace App\Domain\Lab\Services;

use App\Domain\Lab\Models\Media;
use App\Domain\Lab\Models\ToolOutput;
use App\Domain\Workspace\Models\Project;
use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * الملفات المولّدة (plan 2026-11-09 البند 2). Every byte the platform stores goes through here, and every stored byte
 * gets a `media` row in the same breath — the two must not drift, or the disk fills with files nothing can find.
 *
 * It writes to a NAMED disk (`assets`), never to the default one. Sessions, cache, logs and backups stay local, so a
 * wrong S3 credential degrades one feature instead of taking the platform down with it.
 *
 * Reads are deliberately two-shaped: a public asset (one the member means to share) is served from the CDN domain,
 * and everything else only ever leaves as a short-lived signed link. A bucket left open means a leaked URL is valid
 * forever, which is not a trade a platform makes for the convenience of a plain path.
 */
class MediaLibrary
{
    public function __construct(private readonly string $disk = 'assets') {}

    /** Whether a remote disk is actually configured. Without it the media features stay off instead of half-working. */
    public function enabled(): bool
    {
        $config = (array) config('filesystems.disks.'.$this->disk, []);
        if (($config['driver'] ?? 'local') === 'local') {
            return true; // the local fallback is always usable (dev, and tests)
        }

        return filled($config['key'] ?? null) && filled($config['secret'] ?? null) && filled($config['bucket'] ?? null);
    }

    public function disk(): Filesystem
    {
        return Storage::disk($this->disk);
    }

    /**
     * Store raw bytes and record them. The path is content-addressed by a random id, never by the member's own file
     * name: an uploaded name is attacker-controlled and a predictable path is a guessable one.
     */
    public function store(
        string $contents,
        string $extension,
        string $mime,
        ?Project $project = null,
        ?User $user = null,
        string $purpose = 'asset',
        bool $public = false,
        array $attributes = [],
    ): Media {
        $extension = strtolower(preg_replace('/[^a-z0-9]/i', '', $extension) ?: 'bin');
        $folder = $purpose === '' ? 'asset' : Str::slug($purpose);
        $path = trim($folder, '/').'/'.now()->format('Y/m').'/'.Str::uuid()->toString().'.'.$extension;

        $this->disk()->put($path, $contents, $public ? 'public' : 'private');

        return Media::query()->create(array_merge([
            'workspace_id' => $project?->workspace_id,
            'project_id' => $project?->id,
            'user_id' => $user?->id,
            'disk' => $this->disk,
            'path' => $path,
            'kind' => str_starts_with($mime, 'image/') ? 'image' : 'file',
            'purpose' => $purpose,
            'mime' => $mime,
            'bytes' => strlen($contents),
            'checksum' => hash('sha256', $contents),
            'is_public' => $public,
        ], $attributes));
    }

    /** Attach a stored file to the asset it belongs to; from this moment it is no longer an orphan. */
    public function attach(Media $media, ToolOutput $output): Media
    {
        $media->forceFill(['tool_output_id' => $output->id, 'project_id' => $output->project_id])->save();

        return $media;
    }

    /**
     * A URL the browser can load. Public assets go out on the CDN domain when one is configured; everything else is
     * a signed link that expires, so a copied URL stops working instead of becoming a permanent hole in the bucket.
     */
    public function url(Media $media, int $minutes = 30): string
    {
        $disk = Storage::disk($media->disk);

        if ($media->is_public && filled($base = (string) config('filesystems.disks.'.$media->disk.'.url', ''))) {
            return rtrim($base, '/').'/'.ltrim($media->path, '/');
        }

        try {
            return $disk->temporaryUrl($media->path, now()->addMinutes($minutes));
        } catch (Throwable) {
            // A local disk has no signed URLs; the authorising route serves the bytes instead.
            return route('media.show', $media);
        }
    }

    public function delete(Media $media): void
    {
        try {
            Storage::disk($media->disk)->delete($media->path);
        } catch (Throwable $exception) {
            report($exception);
        }
        $media->delete();
    }
}
