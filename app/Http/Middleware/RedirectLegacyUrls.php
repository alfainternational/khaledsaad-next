<?php

namespace App\Http\Middleware;

use App\Domain\Content\Services\LegacyRedirects;
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

        // Legacy slugs are Arabic; stored decoded, so decode the incoming path before matching.
        $path = '/'.ltrim(rawurldecode($request->path()), '/');

        // Current pricing and account pages take precedence over mappings imported from the old site.
        if (in_array($path, ['/pricing', '/profile'], true)) {
            return $next($request);
        }

        try {
            $redirect = DB::table('redirects')
                ->where('from_path', $path)
                ->where('is_active', true)
                ->first();
        } catch (QueryException) {
            return $next($request);
        }

        if (! $redirect) {
            return $next($request);
        }

        if ((int) $redirect->status_code === 410) {
            // Deliberately removed content gets the designed 410 page (blueprint §4.15), not an empty body.
            return response()->view('errors.410', [], 410)->header('X-Robots-Tag', 'noindex');
        }

        $intended = (string) $redirect->to_path;
        abort_unless(self::isSiteRelative($intended), 500);
        abort_if($intended === $path, 500);

        // A stored 301 may point at an article the editor has not published yet. Sending an indexed URL permanently
        // to a 404 is the worst outcome available, so LegacyRedirects decides the destination now (see its docblock).
        [$target, $status] = LegacyRedirects::resolve($intended, (int) $redirect->status_code);
        abort_unless(self::isSiteRelative($target), 500);

        // The fallback can land on the very path that was asked for (a redirect from a section index into one of its
        // own unready articles). That is not a loop worth a 500 — it is the page the member wanted. Serve it.
        if ($target === $path) {
            return $next($request);
        }

        $response = redirect($target, in_array($status, [301, 302, 307, 308], true) ? $status : 301);

        // A fallback destination is good until the editor publishes, which could be this afternoon. A 302 without
        // Cache-Control is still cacheable by heuristic in some proxies, and that is the whole failure we are here
        // to avoid, so say it outright.
        return $target === $intended ? $response : $response->header('Cache-Control', 'no-store, private');
    }

    /** No host, no scheme, no protocol-relative "//", nothing a header-splitting payload could hide in. */
    private static function isSiteRelative(string $target): bool
    {
        return str_starts_with($target, '/')
            && ! str_starts_with($target, '//')
            && ! preg_match('/[\\\\\s\x00-\x1f]/', $target)
            && parse_url($target, PHP_URL_HOST) === null;
    }
}
