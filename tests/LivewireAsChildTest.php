<?php

declare(strict_types=1);

namespace SiddharthaGF\XMorph\Tests;

use Illuminate\View\ComponentAttributeBag;
use PHPUnit\Framework\TestCase;
use SiddharthaGF\XMorph\AsChild;

/**
 * Livewire-view case: asChild output must be deterministic across
 * re-renders so Livewire morphing sees byte-identical HTML for
 * identical input (including wire: attributes and reordered bags).
 */
final class LivewireAsChildTest extends TestCase
{
    public function test_conditional_livewire_view_re_render_is_byte_identical(): void
    {
        $slot = trim((string) preg_replace(
            '/\{\{--.*?--\}\}/s',
            '',
            (string) file_get_contents(__DIR__.'/../resources/views/examples/conditional-button.blade.php')
        ));

        $render = static function () use ($slot): string {
            $parent = (new ComponentAttributeBag(['class' => 'p-4', 'wire:key' => 'save-1']))
                ->merge(['class' => 'layout'])
                ->class(['active' => true]);

            return AsChild::renderSlot($parent, $slot);
        };

        self::assertSame($render(), $render());
    }

    public function test_livewire_view_re_render_is_byte_identical(): void
    {
        $slot = '<div wire:click="save" class="card" x-data="{ open: false }">Card body</div>';
        $parent = new ComponentAttributeBag(['class' => 'p-4', 'id' => 'parent', 'wire:key' => 'card-1']);

        $first = AsChild::renderSlot($parent, $slot);
        $second = AsChild::renderSlot($parent, $slot);

        self::assertSame($first, $second);
        self::assertStringContainsString('wire:click="save"', $first);
        self::assertStringContainsString('wire:key="card-1"', $first);
        self::assertStringNotContainsString('asChild', $first);
    }

    public function test_reordered_input_bag_still_re_renders_byte_identical(): void
    {
        $slot = '<button class="c1" id="child">Hi</button>';

        $ordered = new ComponentAttributeBag(['class' => 'p-4', 'id' => 'parent', 'data-role' => 'x']);
        $reordered = new ComponentAttributeBag(['data-role' => 'x', 'id' => 'parent', 'class' => 'p-4']);

        self::assertSame(
            AsChild::renderSlot($ordered, $slot),
            AsChild::renderSlot($reordered, $slot)
        );
    }
}
