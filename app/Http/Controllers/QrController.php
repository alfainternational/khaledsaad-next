<?php

namespace App\Http\Controllers;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * رمز QR لرابط من المنصة (spec 2026-11-09 §5، الموجة 1، البند من #16).
 *
 * Two deliberate decisions:
 *
 *  1. **Signed, not open.** The route only renders URLs the platform itself generated. An open QR endpoint turns our
 *     domain into a free laundering service: a QR served from khaledsaad.net encodes someone else's link and lends
 *     our domain's trust to it. Encoding is harmless; the hosting is not.
 *  2. **Our own implementation on a BSD library.** The spec flags QRBTF as AGPL — a licence that would oblige this
 *     whole platform to open its source merely for serving it over the network. Not one line comes from it.
 *
 * SVG on purpose: it scales to a billboard without pixelation, needs no image extension, and prints sharply, which
 * is the entire point of a QR on a poster or a receipt.
 */
class QrController extends Controller
{
    private const MIN = 120;

    private const MAX = 1200;

    public function show(Request $request): Response
    {
        $data = trim((string) $request->query('u', ''));
        abort_if($data === '' || mb_strlen($data) > 1200, 404);
        // Encoding is not fetching, so the URL guard does not apply — but it must still be a web link, not a
        // `javascript:` payload that a scanner might hand to a browser.
        abort_unless(preg_match('#^https?://#i', $data) === 1, 404);

        $size = (int) $request->query('size', 420);
        $size = max(self::MIN, min(self::MAX, $size));

        $renderer = new ImageRenderer(
            new RendererStyle($size, 2, null, null, Fill::uniformColor(
                new \BaconQrCode\Renderer\Color\Rgb(255, 255, 255),
                new \BaconQrCode\Renderer\Color\Rgb(16, 24, 40),
            )),
            new SvgImageBackEnd,
        );

        $svg = (new Writer($renderer))->writeString($data);

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml; charset=utf-8',
            // The same link always renders the same code, so this is safe to cache for a long time.
            'Cache-Control' => 'public, max-age=604800',
            'Content-Disposition' => 'inline; filename="athar-qr.svg"',
            'X-Robots-Tag' => 'noindex',
        ]);
    }
}
