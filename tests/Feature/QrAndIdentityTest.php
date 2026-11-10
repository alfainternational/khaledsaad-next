<?php

namespace Tests\Feature;

use App\Domain\Lab\Models\Tool;
use App\Domain\Lab\Services\VisualStudio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * الموجة 1: رمز QR من بانِي UTM، وحزمة الهوية فوق خط الاستوديو نفسه.
 *
 * The QR tests are mostly about the signature. An open QR endpoint is not a security hole in the usual sense — it
 * fetches nothing — but it hands our domain's reputation to whatever link a stranger encodes, which is worse than
 * a bug because it looks like a feature.
 */
class QrAndIdentityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Http::fake();
    }

    public function test_the_qr_route_renders_only_links_the_platform_signed(): void
    {
        $signed = URL::signedRoute('qr', ['u' => 'https://example.com/landing?utm_source=instagram']);

        $svg = $this->get($signed)->assertOk()->assertHeader('content-type', 'image/svg+xml; charset=utf-8');
        $this->assertStringContainsString('<svg', $svg->getContent());
        $this->assertStringContainsString('</svg>', $svg->getContent());

        // Unsigned, or signed for a different link, is a 403 from the signature middleware.
        $this->get(route('qr', ['u' => 'https://example.com/landing']))->assertForbidden();
        $this->get(str_replace('example.com', 'attacker.example', $signed))->assertForbidden();
    }

    public function test_a_signed_non_web_payload_is_still_refused(): void
    {
        // The signature proves we made the link, not that the payload is sane; a scanner may hand this to a browser.
        $this->get(URL::signedRoute('qr', ['u' => 'javascript:alert(1)']))->assertNotFound();
        $this->get(URL::signedRoute('qr', ['u' => '']))->assertNotFound();
    }

    public function test_the_utm_builder_hands_the_member_a_qr_for_the_tracked_link(): void
    {
        $tool = Tool::query()->where('slug', 'utm-builder')->firstOrFail();

        $this->post(route('tools.generate', $tool), $tool->example_input)->assertRedirect();
        $markdown = session('lab.result.utm-builder')['markdown'];

        $this->assertStringContainsString('رمز QR لهذا الرابط', $markdown);
        $this->assertMatchesRegularExpression('#\!\[رمز QR[^\]]*\]\(https?://[^)]*/qr\?[^)]*signature=#u', $markdown);

        // The QR in the asset is the TRACKED link, otherwise a scan from a poster would be an untraceable visit.
        preg_match('#\((https?://[^)]*/qr\?[^)]*)\)#u', $markdown, $m);
        $this->assertStringContainsString('utm_source', urldecode($m[1]));
        $this->get(html_entity_decode($m[1]))->assertOk();
    }

    public function test_identity_sizes_pad_the_mark_instead_of_cropping_it(): void
    {
        $studio = app(VisualStudio::class);
        $templates = $studio->templates();

        $this->assertSame('contain', $templates['brand_avatar']['fit']);
        $this->assertSame('contain', $templates['app_icon']['fit']);
        $this->assertSame('contain', $templates['og_image']['fit']);
        $this->assertArrayNotHasKey('fit', $templates['instagram_post'], 'a photo is still filled to the frame');

        // A deliberately wide mark: cropping it to a square would cut its edges off.
        $wide = imagecreatetruecolor(1200, 300);
        imagefilledrectangle($wide, 0, 0, 1200, 300, imagecolorallocate($wide, 20, 30, 50));
        imagefilledellipse($wide, 600, 150, 240, 240, imagecolorallocate($wide, 240, 240, 240));
        ob_start();
        imagepng($wide);
        $logo = (string) ob_get_clean();

        $assets = $studio->compose($logo, ['brand_avatar', 'og_image']);

        $this->assertCount(2, $assets);
        $this->assertSame([400, 400], array_slice(getimagesizefromstring($assets[0]['bytes']), 0, 2));
        $this->assertSame([1200, 630], array_slice(getimagesizefromstring($assets[1]['bytes']), 0, 2));
        $this->assertSame('image/png', $assets[0]['mime'], 'a mark with transparency stays PNG');
    }

    public function test_the_studio_page_offers_the_identity_sizes_too(): void
    {
        $this->get('/tools/studio')->assertOk()
            ->assertSee('الهوية — صورة الحساب')
            ->assertSee('الهوية — أيقونة 512')
            ->assertSee('الهوية — صورة المشاركة (OG)');
    }
}
