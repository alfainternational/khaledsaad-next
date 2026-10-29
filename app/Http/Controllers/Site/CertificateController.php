<?php

namespace App\Http\Controllers\Site;

use App\Domain\Academy\Models\Certificate;
use App\Http\Controllers\Controller;

class CertificateController extends Controller
{
    public function show(string $code)
    {
        $certificate = Certificate::query()->with(['user', 'course'])->where('verification_code', $code)->first();

        return response()->view('site.certificates.show', compact('certificate'), $certificate ? 200 : 404)
            ->header('X-Robots-Tag', 'noindex, nofollow')->header('Cache-Control', 'no-store');
    }
}
