<?php

declare(strict_types=1);

namespace SiddharthaGF\XMorph\Contracts;

/**
 * Seam for the slot HTML parser.
 *
 * Implement it to plug in any HTML parser (DOM-based, third-party, …):
 * pass it per call to `AsChild::renderSlot()`, pin it with
 * `AsChild::useParser()`, or bind it as `HtmlParser::class` in the
 * container. Without an override, `AsChild` uses the bundled
 * `RootElementParser`: a small bounded matcher, no DOM, no dependencies.
 *
 * Contract (same as the default): merge `$attributeString` onto the
 * single root element of `$slotHtml` and return the mutated HTML. On a
 * multi-root slot throw `MultipleRootElementsException`; on markup you
 * cannot handle safely, return `$slotHtml` untouched — never corrupt HTML.
 */
interface HtmlParser
{
    public function merge(string $slotHtml, string $attributeString): string;
}
