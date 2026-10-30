<?php

namespace App\Events;

use App\Domain\Academy\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/** Fired when a learner has completed every published lesson of a course and recorded a real result in it (blueprint §4.5 "شهادة المسار"). */
class CourseCompleted
{
    use Dispatchable;

    public function __construct(public readonly User $user, public readonly Course $course) {}
}
