<?php

declare(strict_types=1);

namespace SiddharthaGF\XMorph\View\Components;

use Illuminate\View\Component;
use Illuminate\View\ComponentAttributeBag;
use InvalidArgumentException;

use function is_array;

/**
 * Internal asChild workhorse: author views wrap their fallback tag with
 * a single `<x-morph>` line instead of the `@php`/`@if` boilerplate.
 *
 * The attribute source is the spread `$attributes` bag by default, or the
 * explicit `:bag` when given; the asChild marker is always stripped. The
 * explicit `:flag` carries the `@asChild` directive variable for scopes
 * where it is visible.
 */
final class Morph extends Component
{
    public mixed $bag;

    /**
     * The `$bag` override stays `mixed` on purpose: a nullable
     * `ComponentAttributeBag` parameter would make the container inject an
     * empty bag instead of keeping the `null` default, hiding the spread
     * `$attributes`. Anything that is not a bag, an attribute array, or
     * null is rejected instead of merged blindly.
     *
     * @param  array<string, mixed>  $defaults
     * @param  mixed  $bag  A ComponentAttributeBag, an attribute array, or null; anything else is rejected.
     */
    public function __construct(public string $tag = 'div', public array $defaults = [], public bool $flag = false, mixed $bag = null)
    {
        if (is_array($bag)) {
            $bag = new ComponentAttributeBag($bag);
        }

        if ($bag !== null && ! $bag instanceof ComponentAttributeBag) {
            throw new InvalidArgumentException('Morph $bag must be a ComponentAttributeBag, an attribute array, or null.');
        }

        $this->bag = $bag;
    }

    /**
     * Inline Blade view: merge onto the slot root when flagged, else
     * render the fallback tag with defaults merged and the slot inside.
     *
     * Kept as a string so the package ships no view files; Laravel
     * compiles it like any component view, so `$attributes` and `$slot`
     * behave exactly as in the former author-side boilerplate.
     */
    public function render(): string
    {
        return <<<'BLADE'
        @php
        $morphBag = $bag ?? $attributes;
        [$morphIsAsChild, $morphAttributes] = \SiddharthaGF\XMorph\XmorphServiceProvider::consumeAsChildFlag($morphBag);
        $morphIsAsChild = $morphIsAsChild || $flag || ($__xmorphAsChild ?? false);
        @endphp
        @if ($morphIsAsChild)
        {!! \SiddharthaGF\XMorph\AsChild::renderSlot($morphAttributes, $slot->toHtml()) !!}
        @else
        <{{ $tag }} {{ $morphAttributes->merge($defaults) }}>{{ $slot }}</{{ $tag }}>
        @endif
        BLADE;
    }
}
