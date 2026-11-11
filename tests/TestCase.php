<?php

namespace Tests;

use App\Support\Web\UrlGuard;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * UrlGuard's DNS stub is static, so a test that sets it and does not clear it changes how every test after it in
     * the process behaves. AdminMagazineTest set one inside a test method and never reset it, which meant the next
     * method in that class passed because of a side effect of the previous one — and any class that cleared the stub
     * in its own tearDown handed the one after it a guard that would do real DNS.
     *
     * Clearing it here makes the order of the suite stop mattering, which is the only state a suite can be trusted in.
     */
    protected function tearDown(): void
    {
        UrlGuard::resolveUsing(null);

        parent::tearDown();
    }
}
