<?php

declare(strict_types=1);

namespace SiddharthaGF\XMorph\Tests\Fixtures;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use RuntimeException;

/**
 * Functional host: renders the canonical asChild call-site around a nested
 * Livewire child through full Blade compilation.
 */
final class AsChildHost extends Component
{
    public function render(): View
    {
        $rendered = view('xmorph::as-child-host');

        if (! $rendered instanceof View) {
            throw new RuntimeException('as-child-host view missing.');
        }

        return $rendered;
    }
}
