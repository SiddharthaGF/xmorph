<?php

declare(strict_types=1);

namespace SiddharthaGF\XMorph\Tests\Support;

use SiddharthaGF\XMorph\Contracts\HtmlParser;

/**
 * Test double recording what the merge received and returning canned HTML.
 */
final class StubHtmlParser implements HtmlParser
{
    public string $lastAttributeString = '';

    public string $lastSlotHtml = '';

    public function __construct(private readonly string $output) {}

    public function merge(string $slotHtml, string $attributeString): string
    {
        $this->lastSlotHtml = $slotHtml;
        $this->lastAttributeString = $attributeString;

        return $this->output;
    }
}
