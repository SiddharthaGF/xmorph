<?php

declare(strict_types=1);

namespace SiddharthaGF\XMorph\Tests\Functional;

use Illuminate\View\Factory;
use Livewire\Livewire;
use SiddharthaGF\XMorph\Tests\Fixtures\ToggleHost;
use SiddharthaGF\XMorph\Tests\TestCase;

/**
 * Dynamic E2E port: a self-contained Livewire host rendered through full
 * Blade compilation, holding the static canonical call-site, a dynamic
 * `:asChild="$useChild"` toggle, and a normal-path control side by side.
 *
 * Every update goes through the same host test instance that rendered the
 * parent, so merge preservation and toggle switches are asserted on real
 * re-renders rather than synthetic re-applications.
 */
final class DynamicAsChildTest extends TestCase
{
    private const NORMAL_SNIPPET = '<button type="button" class="btn-plain"><a href="#">Plain</a></button>';

    protected function setUp(): void
    {
        parent::setUp();

        $app = $this->app;

        self::assertNotNull($app);

        $view = $app->make(Factory::class);

        self::assertInstanceOf(Factory::class, $view);

        $view->addNamespace('xmorph', dirname(__DIR__).'/Fixtures/views');

        Livewire::component('xmorph-toggle-host', ToggleHost::class);
    }

    public function test_increment_preserves_merge_byte_identically(): void
    {
        $host = Livewire::test('xmorph-toggle-host');
        $before = $this->stripLivewireMeta($host->html());

        $host->call('increment');
        $after = $this->stripLivewireMeta($host->html());

        self::assertStringContainsString('<span id="count">1</span>', $after);
        self::assertSame(1, substr_count($after, 'btn-class'));
        self::assertSame(1, substr_count($after, 'btn-dynamic'));
        self::assertStringContainsString(self::NORMAL_SNIPPET, $after);

        // Only the counter value changes; the merge is byte-identical.
        self::assertSame(
            $this->withCountPlaceholder($before),
            $this->withCountPlaceholder($after)
        );
    }

    public function test_initial_render_merges_canonical_and_dynamic_but_wraps_normal(): void
    {
        $html = Livewire::test('xmorph-toggle-host')->html();

        self::assertStringNotContainsString('asChild', $html);

        self::assertStringContainsString('<a href="#" class="btn-class">', $html);
        self::assertStringContainsString('<a href="#" class="btn-dynamic">', $html);
        self::assertStringContainsString(self::NORMAL_SNIPPET, $html);

        self::assertSame(1, substr_count($html, 'btn-class'));
        self::assertSame(1, substr_count($html, 'btn-dynamic'));
        self::assertStringContainsString('<span id="count">0</span>', $html);
    }

    public function test_toggle_switches_dynamic_between_merged_and_wrapper(): void
    {
        $host = Livewire::test('xmorph-toggle-host');

        $host->call('toggleChild');
        $wrapped = $host->html();

        self::assertStringContainsString('<button type="button" class="btn-dynamic">', $wrapped);
        self::assertStringNotContainsString('<a href="#" class="btn-dynamic">', $wrapped);
        self::assertStringNotContainsString('asChild', $wrapped);

        // Static canonical and normal paths are unaffected by the toggle.
        self::assertStringContainsString('<a href="#" class="btn-class">', $wrapped);
        self::assertStringContainsString(self::NORMAL_SNIPPET, $wrapped);

        $host->call('toggleChild');
        $merged = $host->html();

        self::assertStringContainsString('<a href="#" class="btn-dynamic">', $merged);
        self::assertStringNotContainsString('<button type="button" class="btn-dynamic">', $merged);
        self::assertStringContainsString(self::NORMAL_SNIPPET, $merged);

        // Round trip restores the exact initial merge (modulo transport attrs).
        $fresh = $this->stripLivewireMeta(Livewire::test('xmorph-toggle-host')->html());

        self::assertSame($fresh, $this->stripLivewireMeta($merged));
    }

    /**
     * Strip Livewire transport attributes (snapshots, effects, ids) so
     * renders can be compared structurally across updates and instances.
     */
    private function stripLivewireMeta(string $html): string
    {
        $stripped = preg_replace('/\s*wire:(?:snapshot|effects|id)="[^"]*"/', '', $html);

        self::assertIsString($stripped);

        return $stripped;
    }

    private function withCountPlaceholder(string $html): string
    {
        return (string) preg_replace('/id="count">\d+</', 'id="count">#<', $html);
    }
}
