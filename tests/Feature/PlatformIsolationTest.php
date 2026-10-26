<?php

namespace Tests\Feature;

use Tests\TestCase;

class PlatformIsolationTest extends TestCase
{
    public function test_platform_uses_its_own_identity_and_hosts(): void
    {
        $this->assertSame('khaledsaad-next', config('platform.id'));
        $this->assertSame('next.khaledsaad.net', config('platform.public_host'));
        $this->assertSame('khaledsaad.net', config('platform.primary_host'));
        $this->assertSame('ar', config('platform.locale'));
    }
}
