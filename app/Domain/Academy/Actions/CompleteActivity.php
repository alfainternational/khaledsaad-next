<?php

namespace App\Domain\Academy\Actions;

use App\Domain\Academy\Models\Activity;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CompleteActivity
{
    public function handle(User $user, Activity $activity, array $deliverable = []): void
    {
        DB::table('activity_completions')->updateOrInsert(
            ['user_id' => $user->id, 'activity_id' => $activity->id],
            ['lesson_id' => null, 'completed_at' => now(), 'deliverable' => json_encode($deliverable, JSON_UNESCAPED_UNICODE)]
        );
    }
}
