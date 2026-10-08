<?php

namespace Tests;

use App\Domain\Support\PublicNetwork;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // No real DNS in tests: every host looks public unless a test says otherwise.
        PublicNetwork::resolveUsing(fn (string $host): array => ['93.184.216.34']);
    }

    protected function tearDown(): void
    {
        PublicNetwork::resolveUsing(null);

        parent::tearDown();
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
