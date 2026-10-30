<?php

namespace App\Events;

use App\Domain\Workspace\Models\Workspace;
use Illuminate\Foundation\Events\Dispatchable;

/** Fired after a confirmed payment activates (or extends) a workspace's paid plan. */
class SubscriptionActivated
{
    use Dispatchable;

    public function __construct(public readonly Workspace $workspace, public readonly string $planSlug) {}
}
