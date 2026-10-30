<?php

namespace App\Listeners;

use App\Domain\Academy\Services\CertificateIssuer;
use App\Events\CourseCompleted;

/** Issues the course certificate when the learner's plan allows it (blueprint §4.13: certificates belong to the professional plan). */
class IssueCertificate
{
    public function __construct(private readonly CertificateIssuer $issuer) {}

    public function handle(CourseCompleted $event): void
    {
        $this->issuer->issue($event->user, $event->course);
    }
}
