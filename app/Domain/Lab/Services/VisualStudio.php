<?php

namespace App\Domain\Lab\Services;

use ArPHP\I18N\Arabic;
use DomainException;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Geometry\Factories\RectangleFactory;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Typography\FontFactory;

/**
 * استوديو الأصول البصرية — بلا توليد (plan 2026-11-09 البند 4، §د من مراجعة التكلفة الصفرية).
 *
 * It composes; it does not generate. The member's own photo is cropped to each platform's size, the headline is set
 * on a readable band and the logo is placed. Every step is deterministic, free and full quality — which is exactly
 * why this ships while photoreal generation does not: generation is the one thing whose free tier is genuinely worse.
 *
 * Two things here are easy to get wrong and both would be shipped as garbage rather than as an error:
 *
 *  1. **Arabic needs shaping.** GD draws glyphs in logical order with no joining, so a headline rendered naively
 *     comes out as disconnected, reversed letters. `ar-php` converts to presentation forms, applies bidi and wraps
 *     the lines; Intervention then only draws what it is given, with its own wrapping left off.
 *  2. **A decode bomb is not a byte count.** An image costs width × height × 4 bytes of RAM to open, so a small file
 *     can still be a 50MP PNG that needs 200MB. `inspect()` reads the header and refuses on pixels, before decoding.
 */
class VisualStudio
{
    public function __construct(private readonly MediaLibrary $media) {}

    /** The studio needs a font with Arabic coverage; without one the headline is refused, never drawn wrong. */
    public function fontAvailable(): bool
    {
        $path = (string) config('studio.font.path');

        return $path !== '' && is_file($path) && is_readable($path);
    }

    public function templates(): array
    {
        return (array) config('studio.templates', []);
    }

    /**
     * Header-only validation of untrusted bytes: format, dimensions and pixel budget, before anything is decoded.
     *
     * @return array{width:int, height:int, mime:string}
     *
     * @throws DomainException with a message meant for the member
     */
    public function inspect(string $bytes): array
    {
        $limits = (array) config('studio.limits');
        if (strlen($bytes) > (int) $limits['max_bytes']) {
            throw new DomainException('حجم الصورة أكبر من '.(int) round($limits['max_bytes'] / 1048576).' ميغابايت. صغّرها ثم أعد المحاولة.');
        }

        $info = @getimagesizefromstring($bytes);
        if ($info === false || ! isset($info[0], $info[1], $info['mime'])) {
            throw new DomainException('تعذّرت قراءة الملف كصورة. المقبول: JPG أو PNG أو WebP.');
        }
        if (! in_array($info['mime'], (array) $limits['mimes'], true)) {
            throw new DomainException('صيغة غير مدعومة ('.$info['mime'].'). المقبول: JPG أو PNG أو WebP.');
        }

        $megapixels = ($info[0] * $info[1]) / 1_000_000;
        if ($megapixels > (float) $limits['max_megapixels']) {
            throw new DomainException('أبعاد الصورة كبيرة جداً ('.number_format($megapixels, 1).' ميغابكسل). الحد '.(float) $limits['max_megapixels'].'. صغّرها ثم أعد المحاولة.');
        }

        return ['width' => (int) $info[0], 'height' => (int) $info[1], 'mime' => (string) $info['mime']];
    }

    /**
     * Compose one asset per requested template.
     *
     * @param  array<int, string>  $templates
     * @return array<int, array{key:string, label:string, bytes:string, mime:string, extension:string, width:int, height:int}>
     */
    public function compose(string $source, array $templates, string $headline = '', ?string $logo = null): array
    {
        $available = $this->templates();
        $chosen = array_values(array_unique(array_filter($templates, fn ($key) => isset($available[$key]))));
        $chosen = array_slice($chosen, 0, max(1, (int) config('studio.limits.max_templates', 6)));
        if ($chosen === []) {
            throw new DomainException('اختر مقاساً واحداً على الأقل.');
        }

        $info = $this->inspect($source);
        $headline = trim(mb_substr($headline, 0, (int) config('studio.headline.max_chars', 90)));
        if ($headline !== '' && ! $this->fontAvailable()) {
            throw new DomainException('كتابة العنوان على الصورة تحتاج ملف خط عربي مثبّتاً على الخادم. المقاسات والشعار تعمل بدونه.');
        }

        // The logo is untrusted bytes too: a 2MB PNG can still be 10000×10000, which is a decode bomb either way.
        if ($logo !== null) {
            $this->inspect($logo);
        }

        $manager = new ImageManager(new GdDriver);
        $transparent = in_array($info['mime'], ['image/png', 'image/webp'], true);

        $out = [];
        foreach ($chosen as $key) {
            $template = $available[$key];
            $image = $manager->read($source)->cover((int) $template['width'], (int) $template['height']);

            if ($headline !== '') {
                $this->drawHeadline($image, $headline, $template);
            }
            if ($logo !== null) {
                // Re-read per size rather than reuse: scaleDown() mutates, so a shared logo would shrink each round.
                $this->placeLogo($image, $manager->read($logo));
            }

            $encoded = $transparent
                ? $image->toPng()
                : $image->toJpeg((int) config('studio.limits.quality', 88));

            $out[] = [
                'key' => $key,
                'label' => (string) $template['label'],
                'bytes' => (string) $encoded,
                'mime' => $transparent ? 'image/png' : 'image/jpeg',
                'extension' => $transparent ? 'png' : 'jpg',
                'width' => (int) $template['width'],
                'height' => (int) $template['height'],
            ];
        }

        return $out;
    }

    /**
     * The headline sits on a solid band, not straight on the photo: contrast over an unknown image cannot be
     * guaranteed, and an unreadable headline is a worse asset than none. The band is sized to the wrapped text.
     */
    private function drawHeadline(ImageInterface $image, string $headline, array $template): void
    {
        $config = (array) config('studio.headline');
        $width = $image->width();
        $height = $image->height();

        $size = (int) max((int) $config['min_font'], min((int) $config['max_font'], (int) round($width / 20)));
        $inset = (int) round($width * 0.06);
        $boxWidth = $width - (2 * $inset);
        // Arabic advance width averages about half the em; this is what decides where ar-php breaks the lines.
        $perLine = max(8, (int) floor($boxWidth / ($size * 0.52)));

        $shaped = $this->shape($headline, $perLine);
        $lines = substr_count($shaped, "\n") + 1;
        $lineHeight = 1.35;
        $padding = (int) round($size * 0.55);
        $bandHeight = (int) round(($lines * $size * $lineHeight) + (2 * $padding));

        $bottomSafe = (int) round($height * (float) ($template['safe_bottom'] ?? 0.1));
        $bandTop = max(0, $height - $bottomSafe - $bandHeight);

        $image->drawRectangle($inset, $bandTop, function (RectangleFactory $rectangle) use ($boxWidth, $bandHeight, $config) {
            $rectangle->size($boxWidth, $bandHeight);
            $rectangle->background($this->rgba((string) $config['band_color'], (float) $config['band_opacity']));
        });

        // Centred in the band rather than offset from its top: font metrics differ per face, and measuring them
        // wrongly shows up as a caption pinned to one edge with a gap under it.
        $image->text($shaped, (int) round($width / 2), $bandTop + (int) round($bandHeight / 2), function (FontFactory $font) use ($size, $config, $lineHeight) {
            $font->filename((string) config('studio.font.path'));
            $font->size($size);
            $font->color((string) $config['text_color']);
            $font->align('center');
            $font->valign('middle');
            $font->lineHeight($lineHeight);
        });
    }

    private function placeLogo(ImageInterface $image, ImageInterface $logo): void
    {
        $maxWidth = (int) round($image->width() * (float) config('studio.watermark.max_width_ratio', 0.22));
        $logo->scaleDown(width: max(32, $maxWidth));
        $margin = (int) round($image->width() * (float) config('studio.watermark.margin_ratio', 0.04));

        $image->place($logo, 'top-left', $margin, $margin, (int) config('studio.watermark.opacity', 85));
    }

    /** Presentation forms + bidi + line breaking. Digits stay as the member typed them (no Hindi conversion). */
    private function shape(string $text, int $perLine): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        return (new Arabic)->utf8Glyphs($text, $perLine, false);
    }

    private function rgba(string $hex, float $opacity): string
    {
        $hex = ltrim($hex, '#');
        $alpha = max(0.0, min(1.0, $opacity));

        return sprintf(
            'rgba(%d, %d, %d, %.2f)',
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
            $alpha
        );
    }
}
