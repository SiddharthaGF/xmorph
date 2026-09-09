<?php

declare(strict_types=1);

namespace SiddharthaGF\XMorph\Tests\Functional;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Illuminate\View\ComponentAttributeBag;
use SiddharthaGF\XMorph\Tests\TestCase;

/**
 * The internal `<x-morph>` component: one line in author views replaces
 * the former `@php`/`@if` boilerplate with byte-identical behavior.
 *
 * Rendered through full Blade compilation, so the spread `$attributes`
 * forwarding, the `:bag`/`:flag` escape hatches, and the `tag`/`defaults`
 * fallback path are all asserted on real compiled output.
 */
final class MorphComponentTest extends TestCase
{
    public function test_alias_defaults_to_morph(): void
    {
        self::assertSame('morph', config('xmorph.alias'));
    }

    public function test_as_child_merges_and_strips_marker(): void
    {
        $out = $this->renderMorph(
            '<x-morph asChild class="btn btn-primary" type="button" tag="button"><a href="#">Link</a></x-morph>'
        );

        self::assertSame('<a href="#" class="btn btn-primary" type="button">Link</a>', $out);
    }

    public function test_explicit_bag_prop_supplies_attributes(): void
    {
        $out = $this->renderMorph(
            '<x-morph :bag="$bag" tag="button" :defaults="[\'type\' => \'button\']">{{ $inner }}</x-morph>',
            [
                'bag' => new ComponentAttributeBag(['class' => 'btn-class']),
                'inner' => new HtmlString('<a href="#">Link</a>'),
            ]
        );

        self::assertSame('<button type="button" class="btn-class"><a href="#">Link</a></button>', $out);
    }

    public function test_explicit_flag_enables_merge_without_bag_marker(): void
    {
        // The `:flag` seam carries the `@asChild` directive variable for
        // scopes where it is visible; without it this renders wrapped.
        $out = $this->renderMorph(
            '<x-morph :flag="true" class="btn-class" tag="button"><a href="#">Link</a></x-morph>'
        );

        self::assertSame('<a href="#" class="btn-class">Link</a>', $out);
    }

    public function test_fallback_renders_tag_with_merged_defaults(): void
    {
        $out = $this->renderMorph(
            '<x-morph class="btn-class" tag="button" :defaults="[\'type\' => \'button\']"><a href="#">Link</a></x-morph>'
        );

        self::assertSame('<button type="button" class="btn-class"><a href="#">Link</a></button>', $out);
    }

    public function test_falsy_bound_flag_renders_fallback_and_strips_marker(): void
    {
        $out = $this->renderMorph(
            '<x-morph :asChild="false" class="btn-class" tag="button" :defaults="[\'type\' => \'button\']"><a href="#">Link</a></x-morph>'
        );

        self::assertSame('<button type="button" class="btn-class"><a href="#">Link</a></button>', $out);
        self::assertStringNotContainsString('asChild', $out);
    }

    public function test_user_value_overrides_default(): void
    {
        $out = $this->renderMorph(
            '<x-morph class="btn-class" type="submit" tag="button" :defaults="[\'type\' => \'button\']"><a href="#">Link</a></x-morph>'
        );

        self::assertSame('<button type="submit" class="btn-class"><a href="#">Link</a></button>', $out);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function renderMorph(string $template, array $data = []): string
    {
        // Blade::render keeps the inline view's trailing newline; author
        // markup never depends on it, so trim for exact assertions.
        return trim(Blade::render($template, $data));
    }
}
