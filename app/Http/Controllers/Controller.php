<?php

namespace App\Http\Controllers;

use App\Domain\Workspace\Models\Workspace;
use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Resolve a workspace the current user is a member of (pivot role loaded).
     * A workspace the user does not belong to is an authorization failure (403), not a missing resource (404).
     */
    protected function memberWorkspace(Request $request, int|string $workspaceId): Workspace
    {
        $workspace = $request->user()->workspaces()->whereKey($workspaceId)->first();
        abort_unless($workspace, 403);

        return $workspace;
    }
}
