<?php

declare(strict_types=1);

namespace SiddharthaGF\XMorph\Tests;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;
use SiddharthaGF\XMorph\XmorphServiceProvider;

/**
 * Testbench base for tests that need a full Laravel app (Livewire
 * rendering, Blade components).
 *
 * Plain unit tests keep extending PHPUnit's TestCase directly; only the
 * Livewire rendering tests live here.
 */
abstract class TestCase extends BaseTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();

        // Keep plain unit tests deterministic regardless of suite order:
        // drop the app instance so container lookups see an empty
        // container instead of a stale test app.
        Container::setInstance(new Container);
    }

    /**
     * @param  Application  $app
     */
    protected function getEnvironmentSetUp($app): void
    {
        $config = $app->make(Repository::class);

        self::assertInstanceOf(Repository::class, $config);

        $config->set('cache.default', 'array');
        $config->set('app.key', 'base64:'.base64_encode('xmorph-test-key-0123456789abcdef'));
    }

    protected function getPackageProviders($app): array
    {
        return [
            LivewireServiceProvider::class,
            XmorphServiceProvider::class,
        ];
    }
}
