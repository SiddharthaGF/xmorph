<?php

declare(strict_types=1);

namespace SiddharthaGF\XMorph;

use RuntimeException;

final class MultipleRootElementsException extends RuntimeException
{
    public function __construct(string $message = 'asChild requires a single root element, but the slot rendered multiple roots.')
    {
        parent::__construct($message);
    }
}
