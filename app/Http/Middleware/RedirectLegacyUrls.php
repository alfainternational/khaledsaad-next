<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class RedirectLegacyUrls
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return $next($request);
        }

        try {
            $redirect = DB::table('redirects')
                ->where('from_path', '/'.ltrim($request->path(), '/'))
                ->where('is_active', true)
                ->first();
        } catch (QueryException) {
            return $next($request);
        }

        if (! $redirect) {
            return $next($request);
        }

        if ((int) $redirect->status_code === 410) {
            return response('', 410);
        }

        $target = (string) $redirect->to_path;
        abort_unless(
            str_starts_with($target, '/')
            && ! str_starts_with($target, '//')
            && ! preg_match('/[\\\\\s\x00-\x1f]/', $target)
            && parse_url($target, PHP_URL_HOST) === null,
            500
        );
        abort_if($target === '/'.ltrim($request->path(), '/'), 500);

        return redirect($target, in_array((int) $redirect->status_code, [301, 302, 307, 308], true) ? (int) $redirect->status_code : 301);
    }
}
