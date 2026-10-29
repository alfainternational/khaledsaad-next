<?php

namespace App\Http\Controllers\Site;

use App\Domain\Content\Services\SearchService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/** البحث — /search (blueprint §4.12). Rate-limited by throttle:search at the route. */
class SearchController extends Controller
{
    public function __invoke(Request $request, SearchService $search)
    {
        $raw = (string) $request->query('q', '');
        $result = $search->search($raw);
        $suggestions = $result['total'] === 0 ? $search->suggestions() : null;

        return view('site.search', [
            'term' => $result['term'],
            'groups' => $result['groups'],
            'total' => $result['total'],
            'labels' => SearchService::GROUPS,
            'suggestions' => $suggestions,
        ]);
    }
}
