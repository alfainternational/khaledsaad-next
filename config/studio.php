<?php

/*
|--------------------------------------------------------------------------
| استوديو الأصول البصرية (plan 2026-11-09 البند 4، النسخة بتكلفة صفر §د)
|--------------------------------------------------------------------------
|
| This studio GENERATES NOTHING. It composes: it takes an image the member already owns and produces the platform
| sizes, the logo and the headline. That is deliberate — §د of the plan found that most of what a "product studio"
| does needs no model at all, and the part that does (photoreal generation) is the one place where free quality is
| genuinely worse. Composing is deterministic, costs nothing and is full quality, so it ships and generation does not.
|
| Because no model is called, there is no queue here either: the mandatory-queue rule in the plan is about a 10–30
| second generation call, and resizing a capped image takes a second or two, which a web request absorbs honestly.
|
| The limits below are the real defence. An image is decoded into memory as width × height × 4 bytes, so a 50MP PNG
| that downloads in a second needs 200MB of RAM to open — `max_megapixels`, not `max_bytes`, is what stops that.
|
*/

return [

    'limits' => [
        'max_bytes' => (int) env('STUDIO_MAX_BYTES', 8 * 1024 * 1024),
        // 12MP ≈ 4240×2830: larger than any template needs, far below what would exhaust a shared-hosting process.
        'max_megapixels' => (float) env('STUDIO_MAX_MEGAPIXELS', 12),
        'max_templates' => (int) env('STUDIO_MAX_TEMPLATES', 6),
        'mimes' => ['image/jpeg', 'image/png', 'image/webp'],
        'quality' => (int) env('STUDIO_JPEG_QUALITY', 88),
    ],

    /*
    | Arabic on a canvas is not "draw the string": GD places glyphs in logical order with no joining, which renders
    | Arabic as disconnected, reversed letters. The text layer therefore needs BOTH a font with Arabic coverage and
    | a shaper. Without a font file present the headline is refused with a reason — never drawn wrong and shipped.
    */
    'font' => [
        // `?:` not a default argument: an empty STUDIO_FONT_PATH= in .env is an empty string, not an absent value.
        'path' => env('STUDIO_FONT_PATH') ?: resource_path('fonts/arabic-regular.ttf'),
        'bold_path' => env('STUDIO_FONT_BOLD_PATH') ?: null,
    ],

    /*
    | One entry per place a member actually posts. `cover` crops to fill (never distorts), and the safe area keeps the
    | headline clear of each platform's own overlays — a caption centred in a story sits under the profile chrome.
    */
    'templates' => [
        'instagram_post' => ['label' => 'إنستغرام — منشور مربع', 'width' => 1080, 'height' => 1080, 'safe_top' => 0.10, 'safe_bottom' => 0.10],
        'instagram_story' => ['label' => 'إنستغرام — ستوري', 'width' => 1080, 'height' => 1920, 'safe_top' => 0.18, 'safe_bottom' => 0.20],
        'whatsapp_status' => ['label' => 'واتساب — حالة', 'width' => 1080, 'height' => 1920, 'safe_top' => 0.12, 'safe_bottom' => 0.14],
        'x_post' => ['label' => 'إكس — منشور', 'width' => 1200, 'height' => 675, 'safe_top' => 0.08, 'safe_bottom' => 0.08],
        'linkedin_post' => ['label' => 'لينكدإن — منشور', 'width' => 1200, 'height' => 627, 'safe_top' => 0.08, 'safe_bottom' => 0.08],
        'snapchat_ad' => ['label' => 'سناب — إعلان', 'width' => 1080, 'height' => 1920, 'safe_top' => 0.16, 'safe_bottom' => 0.18],
    ],

    /*
    | The headline band. Text is drawn on a solid band rather than straight onto the photo because contrast over an
    | unknown image cannot be guaranteed, and unreadable white-on-white is a worse asset than no headline at all.
    */
    'headline' => [
        'band_opacity' => 0.62,
        'band_color' => '#101828',
        'text_color' => '#ffffff',
        'max_chars' => 90,
        'min_font' => 22,
        'max_font' => 72,
        // Arabic faces carry tall ascenders and deep descenders; 1.35 let Tajawal's lines touch.
        'line_height' => 1.5,
        // The band never takes more than this share of the frame; the type shrinks to fit before it does.
        'max_band_ratio' => 0.38,
    ],

    'watermark' => [
        'max_width_ratio' => 0.22,
        'margin_ratio' => 0.04,
        'opacity' => 85,
    ],

];
