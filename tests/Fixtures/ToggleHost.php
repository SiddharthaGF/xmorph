<?php

declare(strict_types=1);

namespace SiddharthaGF\XMorph\Tests\Fixtures;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use RuntimeException;

/**
 * Functional host: self-contained counter with a dynamic asChild toggle and
 * a normal-path control, all rendered through full Blade compilation.
 */
final class ToggleHost extends Component
{
    public int $count = 0;

    public bool $useChild = true;

    public function increment(): void
    {
        $this->count++;
    }

    public function render(): View
    {
        $rendered = view('xmorph::toggle-host');

        if (! $rendered instanceof View) {
            throw new RuntimeException('toggle-host view missing.');
        }

        return $rendered;
    }

    public function toggleChild(): void
    {
        $this->useChild = ! $this->useChild;
    }
}
