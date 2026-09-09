<?php

declare(strict_types=1);

namespace SiddharthaGF\XMorph;

use Illuminate\Container\Container;
use Illuminate\View\ComponentAttributeBag;
use SiddharthaGF\XMorph\Contracts\HtmlParser;
use SiddharthaGF\XMorph\Parsers\RootElementParser;
use Throwable;

use function is_array;

final class AsChild
{
    /**
     * Explicit parser override (tests and non-container hosts).
     *
     * Null restores container resolution, then the bundled default.
     */
    private static ?HtmlParser $parser = null;

    /**
     * Merge the parent attribute bag onto the slot's single root element,
     * returning the mutated slot HTML with no wrapper.
     *
     * The bag is resolved through ComponentAttributeBag::merge() first so
     * conditional classes/styles and appendable values behave exactly like
     * the normal Blade path (callers may pre-apply `->class([...])` or
     * `->merge([...])` conditionals before delegating here). Ordering is
     * then normalized alphabetically so identical inputs re-render to
     * byte-identical strings for Livewire morphing.
     *
     * The parser is the per-call `$parser` when given, else the
     * `useParser()` override, else the container `HtmlParser::class`
     * binding, else the bundled `RootElementParser`.
     */
    public static function renderSlot(ComponentAttributeBag $attributes, string $slotHtml, ?HtmlParser $parser = null): string
    {
        $resolved = (string) $attributes->merge();
        $normalized = self::normalizeAttributeOrder($resolved);

        $parser ??= self::$parser ?? self::containerParser() ?? new RootElementParser;

        return $parser->merge($slotHtml, $normalized);
    }

    /**
     * Override the parser used for merges.
     */
    public static function useParser(?HtmlParser $parser): void
    {
        self::$parser = $parser;
    }

    /**
     * Read the lowercase attribute name of a raw `name="value"` pair.
     */
    private static function attributeName(string $pair): string
    {
        $parts = preg_split('/\s*=\s*/', trim($pair), 2);

        if (! is_array($parts)) {
            return strtolower(trim($pair));
        }

        return strtolower($parts[0] ?? '');
    }

    /**
     * Resolve the container-bound parser, if one is registered.
     */
    private static function containerParser(): ?HtmlParser
    {
        try {
            $app = Container::getInstance();

            if (! $app->bound(HtmlParser::class)) {
                return null;
            }

            $resolved = $app->make(HtmlParser::class);

            return $resolved instanceof HtmlParser ? $resolved : null;
        } catch (Throwable) {
            // No container available (plain unit tests, non-Laravel hosts).
            return null;
        }
    }

    /**
     * Sort an HTML-ready attribute string alphabetically by attribute
     * name (stable), preserving each pair verbatim.
     */
    private static function normalizeAttributeOrder(string $attributeString): string
    {
        $attributeString = trim($attributeString);

        // Fast path: no whitespace means a single pair, already ordered.
        if ($attributeString === '' || strcspn($attributeString, " \t\n\r\f\v") === strlen($attributeString)) {
            return $attributeString;
        }

        $pairs = [];

        if (preg_match_all(
            '/[:@a-zA-Z_][:@\w.\-]*(?:\s*=\s*(?:"(?:[^"\\\\]|\\\\.)*"|\'[^\']*\'|[^\s"\'`=<>\/]+))?/',
            $attributeString,
            $matches
        ) > 0) {
            $pairs = $matches[0];
        }

        usort($pairs, fn (string $a, string $b): int => self::attributeName($a) <=> self::attributeName($b));

        return implode(' ', $pairs);
    }
}
