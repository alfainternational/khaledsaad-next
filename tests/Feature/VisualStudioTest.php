<?php

namespace Tests\Feature;

use App\Domain\Lab\Models\Media;
use App\Domain\Lab\Models\Tool;
use App\Domain\Lab\Models\ToolOutput;
use App\Domain\Lab\Services\VisualStudio;
use App\Domain\Workspace\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * استوديو الأصول البصرية (plan 2026-11-09 البند 4). The studio composes and never generates, so these tests hold two
 * promises: the composition is real (actual pixels at the platform's size, not a stub), and untrusted bytes are
 * treated as untrusted — checked on the decoded header, stored under a name the uploader never chose.
 */
class VisualStudioTest extends TestCase
{
    use RefreshDatabase;

    private Tool $tool;

    private User $user;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('assets');
        Http::fake();
        $this->tool = Tool::query()->where('slug', 'visual-studio')->firstOrFail();
        $this->user = User::factory()->create(['audience' => 'owner']);
        $workspace = Workspace::query()->create(['name' => 'مساحة', 'slug' => 'studio-space']);
        $workspace->members()->attach($this->user, ['role' => 'owner']);
        $this->project = $workspace->projects()->create(['name' => 'متجر نَدى', 'slug' => 'studio-project', 'profile' => []]);
        if ($font = self::arabicFont()) {
            config(['studio.font.path' => $font]);
        }
    }

    /**
     * A font with Arabic coverage if this machine happens to have one. The suite must not depend on it: the server
     * is Linux and the developer's is Windows, so the text layer is tested where a font exists and the refusal path
     * is tested everywhere.
     */
    private static function arabicFont(): ?string
    {
        foreach ([
            'C:/Windows/Fonts/tahoma.ttf',
            'C:/Windows/Fonts/arial.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            resource_path('fonts/arabic-regular.ttf'),
        ] as $path) {
            if (is_file($path) && is_readable($path)) {
                return $path;
            }
        }

        return null;
    }

    /** Real JPEG bytes, because the whole point of this tool is what comes out of the decoder. */
    private static function jpeg(int $width = 1600, int $height = 1200): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefilledrectangle($image, 0, 0, $width, $height, imagecolorallocate($image, 200, 140, 90));
        imagefilledellipse($image, (int) ($width / 2), (int) ($height / 2), (int) ($height / 2), (int) ($height / 2), imagecolorallocate($image, 250, 245, 235));
        ob_start();
        imagejpeg($image, null, 90);

        return (string) ob_get_clean();
    }

    private function upload(string $name = 'product.jpg', ?string $bytes = null): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'studio').'.jpg';
        file_put_contents($path, $bytes ?? self::jpeg());

        return new UploadedFile($path, $name, 'image/jpeg', null, true);
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'project_id' => $this->project->id,
            'templates' => ['instagram_post', 'x_post'],
            'headline' => '',
        ], $override);
    }

    public function test_the_page_states_that_nothing_is_generated_and_nothing_is_charged(): void
    {
        $this->get('/tools/studio')->assertOk()
            ->assertSee($this->tool->name)
            ->assertSee('بلا رصيد · بلا ذكاء اصطناعي · صورتك تبقى ملكك')
            ->assertSee('id="studio-form"', false)
            ->assertSee('جهّز الأصول (بحساب)');
        $this->get('/tools/visual-studio')->assertRedirect(route('tools.studio'));
        $this->get('/tools')->assertOk()->assertSee(route('tools.studio'), false)->assertSee('بلا توليد وبلا رصيد');
    }

    public function test_composing_produces_real_pixels_at_each_platform_size(): void
    {
        $assets = app(VisualStudio::class)->compose(self::jpeg(), ['instagram_post', 'instagram_story']);

        $this->assertCount(2, $assets);
        foreach ($assets as $asset) {
            $info = getimagesizefromstring($asset['bytes']);
            $this->assertSame($asset['width'], $info[0], $asset['key']);
            $this->assertSame($asset['height'], $info[1], $asset['key']);
            $this->assertSame('image/jpeg', $info['mime']);
        }
        $this->assertSame([1080, 1080], [$assets[0]['width'], $assets[0]['height']]);
        $this->assertSame([1080, 1920], [$assets[1]['width'], $assets[1]['height']]);
    }

    public function test_a_headline_changes_the_pixels_it_is_drawn_on(): void
    {
        if (! self::arabicFont()) {
            $this->markTestSkipped('no font with Arabic coverage on this machine; the refusal path is covered below');
        }
        $studio = app(VisualStudio::class);
        $source = self::jpeg();

        $plain = $studio->compose($source, ['instagram_post'])[0]['bytes'];
        $titled = $studio->compose($source, ['instagram_post'], 'بشرة هادئة بلا تهيّج خلال 7 أيام')[0]['bytes'];

        $this->assertNotSame($plain, $titled, 'the headline must actually reach the canvas');
        $this->assertGreaterThan(strlen($plain), strlen($titled), 'a band and text add detail, so the file grows');
        $this->assertSame([1080, 1080], array_slice(getimagesizefromstring($titled), 0, 2));
    }

    public function test_a_headline_is_refused_rather_than_drawn_wrong_when_no_arabic_font_is_installed(): void
    {
        config(['studio.font.path' => storage_path('fonts/missing.ttf')]);
        $studio = app(VisualStudio::class);

        $this->assertFalse($studio->fontAvailable());

        // Sizes and logo still work: only the text layer depends on the font.
        $assets = $studio->compose(self::jpeg(), ['x_post']);
        $this->assertCount(1, $assets);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('تحتاج ملف خط عربي');
        $studio->compose(self::jpeg(), ['x_post'], 'عنوان عربي');
    }

    public function test_an_oversized_image_is_refused_on_its_pixels_not_only_its_bytes(): void
    {
        config(['studio.limits.max_megapixels' => 1]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('أبعاد الصورة كبيرة جداً');
        app(VisualStudio::class)->inspect(self::jpeg(1600, 1200));
    }

    public function test_the_logo_is_checked_on_its_pixels_too_not_only_the_main_image(): void
    {
        // A logo is a small file by validation, which says nothing about how many pixels it decodes into.
        config(['studio.limits.max_megapixels' => 1]);
        $small = self::jpeg(600, 400);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('أبعاد الصورة كبيرة جداً');
        app(VisualStudio::class)->compose($small, ['x_post'], '', self::jpeg(1600, 1200));
    }

    public function test_one_logo_is_placed_at_the_same_scale_on_every_size(): void
    {
        $assets = app(VisualStudio::class)->compose(self::jpeg(), ['instagram_post', 'x_post', 'instagram_story'], '', self::jpeg(400, 400));

        $this->assertCount(3, $assets);
        foreach ($assets as $asset) {
            $this->assertGreaterThan(1000, strlen($asset['bytes']), $asset['key']);
            $this->assertSame($asset['width'], getimagesizefromstring($asset['bytes'])[0]);
        }
    }

    public function test_a_file_that_is_not_an_image_is_refused_whatever_it_claims_to_be(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('تعذّرت قراءة الملف كصورة');
        app(VisualStudio::class)->inspect('<?php echo "not an image";');
    }

    public function test_a_run_stores_one_file_per_size_and_saves_them_as_one_asset(): void
    {
        $this->actingAs($this->user)
            ->post(route('app.tools.studio', $this->tool), $this->payload(['source' => $this->upload(), 'headline' => self::arabicFont() ? 'بشرة هادئة بلا تهيّج' : '']))
            ->assertRedirect(route('tools.studio', ['project' => $this->project->id]).'#studio-result')
            ->assertSessionHasNoErrors();

        $media = Media::query()->get();
        $this->assertCount(2, $media);
        foreach ($media as $file) {
            Storage::disk('assets')->assertExists($file->path);
            $this->assertSame('visual-asset', $file->purpose);
            $this->assertNotNull($file->tool_output_id, 'a studio file is referenced the moment the asset is saved');
            $this->assertGreaterThan(0, $file->bytes);
        }

        $output = ToolOutput::query()->where('output_type', 'visual_assets')->sole();
        $this->assertStringContainsString('لا توليد بالذكاء الاصطناعي في هذه المخرجات', $output->body_markdown);
        $this->assertStringContainsString(route('media.show', $media->first()), $output->body_markdown);
        $this->assertStringNotContainsString('{{', $output->body_markdown);
        $this->assertSame('studio', $output->payload['source']);

        // Nothing was charged and no provider was called: the studio calls no model at all.
        $this->assertSame(0, (int) DB::table('credit_entries')->where('workspace_id', $this->project->workspace_id)->count());
        $this->assertSame(0, DB::table('ai_usages')->count());
        Http::assertNothingSent();
    }

    public function test_the_stored_file_is_never_named_after_the_upload(): void
    {
        $this->actingAs($this->user)
            ->post(route('app.tools.studio', $this->tool), $this->payload(['source' => $this->upload('../../evil shell.php.jpg'), 'templates' => ['x_post']]))
            ->assertRedirect();

        $media = Media::query()->sole();
        $this->assertStringNotContainsString('evil', $media->path);
        $this->assertStringNotContainsString('..', $media->path);
        $this->assertMatchesRegularExpression('#^visual-asset/\d{4}/\d{2}/[0-9a-f-]{36}\.jpg$#', $media->path);
    }

    public function test_a_disguised_file_never_reaches_the_decoder(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'studio').'.jpg';
        file_put_contents($path, '<?php echo "payload"; ?>');
        $disguised = new UploadedFile($path, 'product.jpg', 'image/jpeg', null, true);

        $this->actingAs($this->user)
            ->post(route('app.tools.studio', $this->tool), $this->payload(['source' => $disguised]))
            ->assertRedirect()
            ->assertSessionHasErrors('source');

        $this->assertSame(0, Media::query()->count());
    }

    public function test_an_unknown_size_is_refused_and_a_reader_cannot_run_the_studio(): void
    {
        $this->actingAs($this->user)
            ->post(route('app.tools.studio', $this->tool), $this->payload(['source' => $this->upload(), 'templates' => ['billboard']]))
            ->assertSessionHasErrors('templates.0');

        $reader = User::factory()->create(['audience' => 'owner']);
        $this->project->workspace->members()->attach($reader, ['role' => 'viewer']);
        $this->actingAs($reader)
            ->post(route('app.tools.studio', $this->tool), $this->payload(['source' => $this->upload()]))
            ->assertForbidden();

        $this->assertSame(0, Media::query()->count());
    }
}
