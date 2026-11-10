<?php

namespace Tests\Feature;

use App\Domain\Lab\Models\Media;
use App\Domain\Lab\Models\Tool;
use App\Domain\Lab\Models\ToolOutput;
use App\Domain\Lab\Services\AssetVault;
use App\Domain\Lab\Services\MediaLibrary;
use App\Domain\Workspace\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * تخزين الملفات (plan 2026-11-09 البند 2). The platform never stored a file before this, so these tests fix the
 * contract while it is still cheap to change: bytes and row are written together, a stored path is never guessable
 * from what the member uploaded, a private file does not leave without authorisation, and an orphan is cleaned up.
 */
class MediaLibraryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('assets');
        $this->user = User::factory()->create(['audience' => 'owner']);
        $workspace = Workspace::query()->create(['name' => 'مساحة', 'slug' => 'media-space']);
        $workspace->members()->attach($this->user, ['role' => 'owner']);
        $this->project = $workspace->projects()->create(['name' => 'مشروع', 'slug' => 'media-project', 'profile' => []]);
    }

    private function library(): MediaLibrary
    {
        return app(MediaLibrary::class);
    }

    private function store(string $purpose = 'poster', bool $public = false): Media
    {
        return $this->library()->store('BYTES-'.$purpose, 'png', 'image/png', $this->project, $this->user, $purpose, $public, [
            'original_name' => '../../etc/passwd.png', 'width' => 1080, 'height' => 1080,
        ]);
    }

    public function test_storing_writes_the_file_and_its_row_together(): void
    {
        $media = $this->store();

        Storage::disk('assets')->assertExists($media->path);
        $this->assertSame('BYTES-poster', Storage::disk('assets')->get($media->path));
        $this->assertSame(hash('sha256', 'BYTES-poster'), $media->checksum);
        $this->assertSame(strlen('BYTES-poster'), (int) $media->bytes);
        $this->assertSame('image', $media->kind);
        $this->assertSame($this->project->workspace_id, (int) $media->workspace_id);
        $this->assertTrue($media->isOrphan(), 'a file is an orphan until an asset references it');
    }

    public function test_the_stored_path_is_never_built_from_what_the_member_uploaded(): void
    {
        $media = $this->store();

        $this->assertStringStartsWith('poster/'.now()->format('Y/m').'/', $media->path);
        $this->assertStringEndsWith('.png', $media->path);
        $this->assertStringNotContainsString('passwd', $media->path, 'an uploaded name is attacker-controlled');
        $this->assertStringNotContainsString('..', $media->path);
        $this->assertMatchesRegularExpression('#^poster/\d{4}/\d{2}/[0-9a-f-]{36}\.png$#', $media->path);
    }

    public function test_attaching_to_an_asset_takes_the_file_out_of_the_prune_window(): void
    {
        $media = $this->store();
        $output = $this->asset();

        $this->library()->attach($media, $output);

        $this->assertFalse($media->fresh()->isOrphan());
        $this->assertSame($output->id, (int) $media->fresh()->tool_output_id);
    }

    public function test_a_public_file_is_served_from_the_cdn_domain_and_a_private_one_is_not(): void
    {
        config(['filesystems.disks.assets.url' => 'https://cdn.example.test']);
        $public = $this->store('poster', true);
        $private = $this->store('upload');

        $this->assertSame('https://cdn.example.test/'.$public->path, $this->library()->url($public));

        // A private file never gets the plain CDN path; it leaves signed and short-lived (or through the route when
        // the disk cannot sign at all).
        $privateUrl = $this->library()->url($private);
        $this->assertStringNotContainsString('cdn.example.test', $privateUrl);
        $this->assertTrue(
            str_contains($privateUrl, 'expiration=') || $privateUrl === route('media.show', $private),
            'expected a signed or authorising URL, got '.$privateUrl
        );
    }

    public function test_a_private_file_is_refused_to_a_stranger_and_served_to_a_member(): void
    {
        $media = $this->store();

        $this->get(route('media.show', $media))->assertNotFound();
        $this->actingAs(User::factory()->create())->get(route('media.show', $media))->assertNotFound();
        $this->actingAs($this->user)->get(route('media.show', $media))->assertOk()->assertHeader('content-type', 'image/png');
    }

    public function test_a_file_of_a_shared_asset_is_public_exactly_as_that_asset_is(): void
    {
        $media = $this->store();
        $output = $this->asset();
        $this->library()->attach($media, $output);

        $this->get(route('media.show', $media))->assertNotFound();

        app(AssetVault::class)->share($output);
        $this->get(route('media.show', $media->fresh()))->assertOk();

        app(AssetVault::class)->unshare($output->fresh());
        $this->get(route('media.show', $media->fresh()))->assertNotFound();
    }

    public function test_prune_deletes_old_orphans_and_never_touches_a_members_asset(): void
    {
        $orphan = $this->store();
        $orphan->forceFill(['created_at' => now()->subDays(120)])->save();
        $young = $this->store();
        $young->forceFill(['created_at' => now()->subDays(10)])->save();
        $attached = $this->store();
        $attached->forceFill(['created_at' => now()->subDays(400)])->save();
        $this->library()->attach($attached, $this->asset());

        $this->artisan('platform:prune-media --dry-run')->assertSuccessful();
        $this->assertSame(3, Media::query()->count(), 'a dry run deletes nothing');

        $this->artisan('platform:prune-media')->assertSuccessful();

        $this->assertNull(Media::query()->find($orphan->id));
        Storage::disk('assets')->assertMissing($orphan->path);
        $this->assertNotNull(Media::query()->find($young->id), 'the grace period is 90 days');
        $this->assertNotNull(Media::query()->find($attached->id), 'a referenced file is the member\'s, not the cron\'s');
        Storage::disk('assets')->assertExists($attached->path);
    }

    private function asset(): ToolOutput
    {
        $tool = Tool::query()->where('kind', 'generator')->firstOrFail();

        return app(AssetVault::class)->save($this->user, $this->project, $tool, 'أصل', 'market_panel', '# أصل');
    }
}
