<?php

declare(strict_types=1);

namespace SiddharthaGF\XMorph\Tests\Fixtures;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use RuntimeException;

final class Counter extends Component
{
    public int $count = 0;

    public function increment(): void
    {
        $this->count++;
    }

    public function render(): View
    {
        $rendered = view('xmorph::counter');

        if (! $rendered instanceof View) {
            throw new RuntimeException('counter view missing.');
        }

        return $rendered;
    }
}
