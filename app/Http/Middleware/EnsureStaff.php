<?php

namespace App\Http\Middleware;

use App\Support\Viewer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline gate of the whole /admin area (alias: staff): only platform staff roles get past it.
 * It runs after `auth`, so a guest is redirected to /login before reaching it. The finer role checks
 * (editor / operations / finance / admin) stay inside each controller.
 */
class EnsureStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(in_array($request->user()?->platform_role, Viewer::STAFF_ROLES, true), 403);

        return $next($request);
    }
}
