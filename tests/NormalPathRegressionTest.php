<?php

declare(strict_types=1);

namespace SiddharthaGF\XMorph\Tests;

use Illuminate\View\ComponentAttributeBag;
use PHPUnit\Framework\TestCase;
use SiddharthaGF\XMorph\AsChild;
use SiddharthaGF\XMorph\XmorphServiceProvider;

/**
 * Zero-cost opt-in: components without the asChild flag must render
 * exactly as before (byte-identical normal path).
 */
final class NormalPathRegressionTest extends TestCase
{
    public function test_bag_without_flag_is_not_detected(): void
    {
        $bag = new ComponentAttributeBag(['class' => 'p-4', 'id' => 'parent']);

        [$isAsChild, $returned] = XmorphServiceProvider::consumeAsChildFlag($bag);

        self::assertFalse($isAsChild);
        self::assertSame('class="p-4" id="parent"', (string) $returned->merge());
    }

    public function test_normal_component_output_stays_byte_identical(): void
    {
        $slot = '<button class="c1">Hi</button>';

        // Normal path never calls renderSlot; the slot string passes through unchanged.
        self::assertSame($slot, AsChild::renderSlot(new ComponentAttributeBag, $slot));
    }

    public function test_stripped_bag_keeps_all_non_marker_attributes(): void
    {
        [$isAsChild, $stripped] = XmorphServiceProvider::consumeAsChildFlag(
            new ComponentAttributeBag(['class' => 'p-4', 'id' => 'parent', 'asChild' => true])
        );

        self::assertTrue($isAsChild);
        self::assertSame('class="p-4" id="parent"', (string) $stripped->merge());
    }
}
