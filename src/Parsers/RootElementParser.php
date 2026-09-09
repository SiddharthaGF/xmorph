<?php

declare(strict_types=1);

namespace SiddharthaGF\XMorph\Parsers;

use SiddharthaGF\XMorph\Contracts\HtmlParser;
use SiddharthaGF\XMorph\MultipleRootElementsException;

use function in_array;
use function is_array;

final class RootElementParser implements HtmlParser
{
    /**
     * Void (self-contained) HTML elements that never take a closing tag.
     *
     * @var string[]
     */
    private const VOID_ELEMENTS = [
        'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input',
        'link', 'meta', 'param', 'source', 'track', 'wbr',
    ];

    /**
     * Merge a pre-resolved parent attribute string onto the single root
     * element of the given slot HTML.
     *
     * Leading HTML comments are skipped to locate the first real tag and
     * are re-emitted verbatim in the output.
     *
     * - Returns $slotHtml unchanged when no root tag can be matched safely
     *   (empty attribute string, unbalanced quotes, unparseable markup,
     *   unclosed leading comment, comment-only markup).
     * - Throws MultipleRootElementsException when more than one root node
     *   is detected; there is no silent first-only merge.
     * - Parent attributes already present on the child root are skipped
     *   (child wins), except `class`, whose values are concatenated with
     *   the child value first. Inserted attributes are sorted
     *   alphabetically so re-renders are byte-identical.
     */
    public function merge(string $slotHtml, string $mergedAttrString): string
    {
        $mergedAttrString = trim($mergedAttrString);

        if ($mergedAttrString === '') {
            return $slotHtml;
        }

        $prefix = '';
        $cursor = $slotHtml;

        // Single pass over the leading comments (a loop re-scans from the
        // start each time, which is quadratic on comment runs).
        if (str_contains($cursor, '<!--')) {
            if (! str_contains($cursor, '-->')) {
                return $slotHtml;
            }

            if (preg_match('/\A((?:\s*<!--.*?-->\s*)+)/s', $cursor, $commentMatch) === 1) {
                $prefix = $commentMatch[1];
                $cursor = substr($cursor, strlen($commentMatch[1]));
            }
        }

        if (preg_match('/\A\s*<!--/s', $cursor) === 1) {
            return $slotHtml;
        }

        if (preg_match('/\A(\s*)<([a-zA-Z][a-zA-Z0-9\-:._]*)([^<>]*?)(\/?)>/', $cursor, $match) !== 1) {
            return $slotHtml;
        }

        $leading = $match[1];
        $tagName = $match[2];
        $attrChunk = $match[3];
        $selfClosing = $match[4] === '/';
        $rest = substr($cursor, strlen($match[0]));

        if (! self::hasBalancedQuotes($attrChunk)) {
            return $slotHtml;
        }

        $tail = self::tailAfterSingleRoot($tagName, $selfClosing, $rest);

        if ($tail === null) {
            return $slotHtml;
        }

        if (trim($tail) !== '') {
            throw new MultipleRootElementsException();
        }

        $mergedTag = self::mergeIntoFirstTag($tagName, $attrChunk, $selfClosing, $mergedAttrString);

        if ($mergedTag === null) {
            return $slotHtml;
        }

        return $prefix.$leading.$mergedTag.$rest;
    }

    /**
     * Extract the inner value of a raw `name="value"` pair, or null for
     * boolean attributes.
     */
    private static function attributeValue(string $rawPair): ?string
    {
        if (! str_contains($rawPair, '=')) {
            return null;
        }

        $equalsPos = strpos($rawPair, '=');

        if ($equalsPos === false) {
            return null;
        }

        $value = trim(substr($rawPair, $equalsPos + 1));

        if (strlen($value) >= 2) {
            $first = $value[0];
            $last = $value[strlen($value) - 1];

            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        return str_replace('\\"', '"', $value);
    }

    private static function firstAttributeValue(string $attributeString, string $name): ?string
    {
        foreach (self::parseAttributePairs($attributeString) as [$pairName, $raw]) {
            if (strtolower($pairName) === strtolower($name)) {
                return self::attributeValue($raw);
            }
        }

        return null;
    }

    private static function hasBalancedQuotes(string $value): bool
    {
        // Single-byte needles: byte and character counts agree here.
        return substr_count($value, '"') % 2 === 0 && substr_count($value, "'") % 2 === 0;
    }

    /**
     * Byte-offset containment check against the opaque spans.
     *
     * @param  array<int, array{0: int, 1: int}>  $spans
     */
    private static function isInsideSpans(int $position, array $spans): bool
    {
        foreach ($spans as [$start, $end]) {
            if ($position >= $start && $position < $end) {
                return true;
            }
        }

        return false;
    }

    /**
     * Splice parent attributes into the first tag, honoring child-wins
     * (except class concatenation) and alphabetical insertion order.
     *
     * Null when the merge cannot be emitted safely (a raw quote in the
     * combined class would break out of the attribute): the caller falls
     * back to the untouched slot.
     */
    private static function mergeIntoFirstTag(string $tagName, string $attrChunk, bool $selfClosing, string $mergedAttrString): ?string
    {
        $childNames = [];

        foreach (self::parseAttributePairs($attrChunk) as [$name]) {
            $childNames[strtolower($name)] = true;
        }

        $toInsert = [];
        $parentClass = null;

        foreach (self::parseAttributePairs($mergedAttrString) as [$name, $raw]) {
            $lower = strtolower($name);

            if ($lower === 'class' && $parentClass === null) {
                $parentClass = self::attributeValue($raw);
            }

            if (isset($childNames[$lower])) {
                continue;
            }

            $toInsert[] = ['name' => $lower, 'raw' => $raw];
        }

        $base = rtrim($attrChunk);

        if ($parentClass !== null && isset($childNames['class'])) {
            $childClass = self::firstAttributeValue($attrChunk, 'class');

            if ($childClass !== null) {
                $combined = trim($childClass.' '.$parentClass);

                if (str_contains($combined, '"')) {
                    return null;
                }

                // The lookbehind keeps `data-class` (and friends) from
                // matching: only a standalone class attribute qualifies.
                $replaced = preg_replace(
                    '/(?<![-:\w.])class\s*=\s*("(?:[^"\\\\]|\\\\.)*"|\'[^\']*\'|[^\s"\'`=<>\/]+)/i',
                    'class="'.$combined.'"',
                    $base,
                    1,
                    $count
                );

                if ($replaced !== null && $count === 1) {
                    $base = $replaced;
                }
            }
        }

        if (count($toInsert) > 1) {
            usort($toInsert, fn ($a, $b) => $a['name'] <=> $b['name']);
        }

        $insert = implode(' ', array_column($toInsert, 'raw'));

        $newTag = '<'.$tagName.$base;

        if ($insert !== '') {
            $newTag .= ' '.$insert;
        }

        $newTag .= $selfClosing ? '/>' : '>';

        return $newTag;
    }

    /**
     * Byte spans whose content must not be scanned for tags: comments,
     * quoted attribute values, and script/style bodies.
     *
     * Comments and quotes share one pass. Each sub-pattern is guarded by
     * a cheap presence check first, so unterminated runs cannot degrade
     * into quadratic rescanning.
     *
     * @return array<int, array{0: int, 1: int}>
     */
    private static function opaqueSpans(string $tagName, string $rest): array
    {
        $spans = [];

        // Quoted pairs self-terminate, so this pass is always linear.
        if (preg_match_all('/=\s*(?:"(?:[^"\\\\]|\\\\.)*"|\'[^\']*\')/s', $rest, $matches, PREG_OFFSET_CAPTURE) > 0) {
            foreach ($matches[0] as [$text, $position]) {
                $spans[] = [$position, $position + strlen($text)];
            }
        }

        // Unterminated comment runs would rescan quadratically, so the
        // comment pass only runs when a close exists.
        if (str_contains($rest, '<!--') && str_contains($rest, '-->')
            && preg_match_all('/<!--.*?-->/s', $rest, $matches, PREG_OFFSET_CAPTURE) > 0) {
            foreach ($matches[0] as [$text, $position]) {
                $spans[] = [$position, $position + strlen($text)];
            }
        }

        $lowerTag = strtolower($tagName);

        if ($lowerTag !== 'script' && $lowerTag !== 'style'
            && (stripos($rest, '</script') !== false || stripos($rest, '</style') !== false)
            && preg_match_all('/<(script|style)\b[^<>]*>.*?<\/\1\s*>/is', $rest, $matches, PREG_OFFSET_CAPTURE) > 0) {
            foreach ($matches[0] as [$text, $position]) {
                $spans[] = [$position, $position + strlen($text)];
            }
        }

        return $spans;
    }

    /**
     * Split an attribute string into [name, raw] pairs, preserving order.
     *
     * Names cover HTML, Blade (`:`, `::`), Alpine (`@`, `.`), and
     * Livewire (`wire:`, `.`) spellings; values cover double-quoted
     * (with backslash escapes, as emitted by ComponentAttributeBag),
     * single-quoted, unquoted, and boolean forms.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    private static function parseAttributePairs(string $attributeString): array
    {
        $pairs = [];

        if (preg_match_all(
            '/[:@a-zA-Z_][:@\w.\-]*(?:\s*=\s*(?:"(?:[^"\\\\]|\\\\.)*"|\'[^\']*\'|[^\s"\'`=<>\/]+))?/',
            $attributeString,
            $matches
        ) > 0) {
            foreach ($matches[0] as $raw) {
                $parts = preg_split('/\s*=\s*/', trim($raw), 2);
                $name = is_array($parts) ? ($parts[0] ?? '') : '';

                if ($name !== '') {
                    $pairs[] = [$name, trim($raw)];
                }
            }
        }

        return $pairs;
    }

    /**
     * Locate the tail following the single root element.
     *
     * Returns null when the markup cannot be proven to hold exactly one
     * root (unparseable → caller falls back to the untouched slot).
     * Throws MultipleRootElementsException on void/self-closing roots
     * followed by further content; sibling content after a paired root is
     * reported via the non-empty tail so the caller can throw.
     *
     * All scanning below is byte-based: PREG_OFFSET_CAPTURE yields byte
     * offsets, so every position here must stay in bytes (mixing in
     * mb_* character offsets silently mis-slices multibyte markup).
     */
    private static function tailAfterSingleRoot(string $tagName, bool $selfClosing, string $rest): ?string
    {
        if ($selfClosing || in_array(strtolower($tagName), self::VOID_ELEMENTS, true)) {
            if (trim($rest) !== '') {
                throw new MultipleRootElementsException();
            }

            return $rest;
        }

        // Unclosed opener after the last close: bail out. Cheaper and
        // more precise than comparing open/close counts (a `-->` inside
        // an attribute value is not a close).
        $open = strrpos($rest, '<!--');

        if ($open !== false) {
            $close = strrpos($rest, '-->');

            if ($close === false || $close < $open) {
                return null;
            }
        }

        $skipSpans = self::opaqueSpans($tagName, $rest);
        $pattern = '/<(\/?)'.preg_quote($tagName, '/').'(?=[\s>\/])/i';

        if (preg_match_all($pattern, $rest, $all, PREG_OFFSET_CAPTURE) === false) {
            return null;
        }

        $depth = 1;

        foreach ($all[0] as $index => [$text, $tagStart]) {
            if (self::isInsideSpans($tagStart, $skipSpans)) {
                continue;
            }

            $tagEnd = strpos($rest, '>', $tagStart);

            if ($tagEnd === false) {
                return null;
            }

            if ($all[1][$index][0] === '/') {
                $depth--;

                if ($depth === 0) {
                    return substr($rest, $tagEnd + 1);
                }

                continue;
            }

            // Self-closing nested tag of the same name: no depth change.
            // Only the byte before this tag's own `>` counts, never the
            // tail of the whole rest.
            if (! isset($rest[$tagEnd - 1]) || $rest[$tagEnd - 1] !== '/') {
                $depth++;
            }
        }

        return null;
    }
}
