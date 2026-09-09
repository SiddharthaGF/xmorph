<?php

declare(strict_types=1);

namespace SiddharthaGF\XMorph\Tests;

use Illuminate\Support\Facades\Blade;
use Illuminate\View\ComponentAttributeBag;
use Illuminate\View\Factory;
use Livewire\Livewire;
use SiddharthaGF\XMorph\AsChild;
use SiddharthaGF\XMorph\Tests\Fixtures\Counter;

/**
 * Livewire update path: documents that a first-paint asChild merge onto a
 * Livewire child root does NOT survive child-driven updates (the child
 * re-renders alone and nothing persists the merge).
 */
final class LivewireAsChildUpdateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $app = $this->app;

        self::assertNotNull($app);

        $view = $app->make(Factory::class);

        self::assertInstanceOf(Factory::class, $view);

        $view->addNamespace('xmorph', __DIR__.'/Fixtures/views');

        Livewire::component('xmorph-counter', Counter::class);
    }

    public function test_first_paint_merges_parent_attrs_onto_livewire_child_root(): void
    {
        $html = Blade::render(
            '<x-xmorph::button @asChild class="btn-class"><livewire:xmorph-counter /></x-xmorph::button>'
        );

        self::assertStringContainsString('wire:id', $html);
        self::assertStringContainsString('btn-class', $html);
        self::assertSame(1, substr_count($html, 'btn-class'));
        self::assertStringNotContainsString('asChild', $html);
    }

    public function test_update_loses_first_paint_merge(): void
    {
        $component = Livewire::test('xmorph-counter');

        // Negative control: the child view itself carries no parent attrs.
        self::assertStringNotContainsString('btn-class', $component->html());

        // First paint: the asChild parent merges onto the Livewire root.
        $firstPaint = AsChild::renderSlot(
            new ComponentAttributeBag(['class' => 'btn-class', 'id' => 'parent-btn']),
            $component->html()
        );

        self::assertStringContainsString('btn-class', $firstPaint);
        self::assertStringContainsString('id="parent-btn"', $firstPaint);

        // Update: the child re-renders alone and the merge is gone —
        // nothing persists it (documented limitation).
        $component->call('increment');

        self::assertStringNotContainsString('btn-class', $component->html());
        self::assertStringNotContainsString('parent-btn', $component->html());
    }
}
