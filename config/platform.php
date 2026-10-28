<?php

return [
    'id' => env('PLATFORM_ID', 'khaledsaad-next'),
    'name' => env('PLATFORM_NAME', 'أثر'),
    'tagline' => env('PLATFORM_TAGLINE', 'تعلّم. طبّق. قِس الأثر.'),
    'public_host' => env('PLATFORM_PUBLIC_HOST', 'next.khaledsaad.net'),
    'primary_host' => env('PLATFORM_PRIMARY_HOST', 'khaledsaad.net'),
    'locale' => env('PLATFORM_LOCALE', 'ar'),
    'support_email' => env('PLATFORM_SUPPORT_EMAIL', 'hello@khaledsaad.net'),
    'backup_marker' => env('PLATFORM_BACKUP_MARKER'),
    // Minimum distinct learners before a behaviour pattern becomes a quality signal (blueprint §6.1).
    'quality_min_sample' => (int) env('PLATFORM_QUALITY_MIN_SAMPLE', 5),
];
