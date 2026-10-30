<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * While an admin is viewing the platform as a member («اعرض كهذا المستخدم»), the session carries
 * `impersonator_id`. Impersonation is read-only: every state-changing request is refused (money,
 * credentials, admin actions, but also lesson progress and assets, which must stay the learner's own),
 * except ending the impersonation and logging out.
 */
class ImpersonationGuard
{
    public const SESSION_KEY = 'impersonator_id';

    /** The only non-GET paths (without leading slash) allowed while impersonating. */
    private const ALLOWED_PATHS = ['admin/impersonation/stop', 'logout'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasSession() || ! $request->session()->has(self::SESSION_KEY)) {
            return $next($request);
        }
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }
        if (in_array(trim($request->path(), '/'), self::ALLOWED_PATHS, true)) {
            return $next($request);
        }

        abort(403, 'هذا الإجراء غير متاح أثناء العرض كمستخدم آخر.');
    }
}
