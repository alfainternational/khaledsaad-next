<?php

namespace App\Listeners;

use App\Domain\Academy\Services\CertificateIssuer;
use App\Events\SubscriptionActivated;

/** A learner who earned a course before upgrading receives the certificate the moment the professional plan is active. */
class IssuePendingCertificates
{
    public function __construct(private readonly CertificateIssuer $issuer) {}

    public function handle(SubscriptionActivated $event): void
    {
        foreach ($event->workspace->members()->get() as $member) {
            $this->issuer->issuePending($member);
        }
    }
}
