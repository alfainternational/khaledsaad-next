<?php

namespace App\Http\Controllers\Site;

use App\Domain\Insights\Services\EventRecorder;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AssetController extends Controller
{
    /** Print-optimised page for "تنزيل PDF" (browser print → save as PDF; reliable Arabic shaping). */
    public function print(Request $request, EventRecorder $events)
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:160'], 'markdown' => ['required', 'string', 'max:60000']]);
        $events->record($request, 'asset.downloaded', ['meta' => ['format' => 'pdf']]);

        return response()->view('site.assets.print', $data)->header('X-Robots-Tag', 'noindex');
    }
}
