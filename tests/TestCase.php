<?php

namespace Tests;

use App\Support\FrameProbe;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The suite must never depend on the public internet. CI runners have
        // working egress while sandboxes often do not, so an un-faked request
        // makes a test pass in one place and fail in the other. Any HTTP call a
        // test forgot to fake now fails loudly instead of silently reaching a
        // real host. Tests that exercise HTTP declare their own Http::fake().
        Http::preventStrayRequests();

        // Http::fake() does not cover DNS. Pin resolution so the frame probe's
        // private-network guard behaves identically on every machine: public
        // names resolve to a public address, and a name meant to test the guard
        // resolves into private space.
        FrameProbe::resolveUsing(function (string $host): array {
            if (str_contains($host, 'rebind') || str_contains($host, 'internal-test')) {
                return ['10.0.0.9'];
            }

            return ['93.184.216.34'];
        });
    }

    protected function tearDown(): void
    {
        FrameProbe::resolveUsing(null);

        parent::tearDown();
    }
}
