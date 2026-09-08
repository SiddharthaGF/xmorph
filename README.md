# XMorph

Radix-style `asChild` for Blade/Livewire components: the parent goes
transparent and its attributes merge onto the slot's root element.

```blade
{{-- The parent... --}}
<x-card asChild class="p-4">
    <button class="c1">Hi</button>
</x-card>

{{-- ...renders this (no <x-card> wrapping anything) --}}
<button class="c1 p-4">Hi</button>
```

Without the flag, everything renders as before. Opt-in per component.

## Requirements

- PHP >= 8.1
- Laravel / `illuminate/view` ^10.0
- Livewire v3 is optional: first paint merges the same, with or without it.

## Install

```bash
composer require siddharthagf/xmorph
```

The provider is auto-discovered, no config or publishing step.

## Usage

Put `asChild` on the parent and a **single-root** child in the slot:

```blade
<x-card asChild class="p-4" id="parent">
    <button class="c1" id="child">Hi</button>
</x-card>
{{-- <button class="c1 p-4" id="child">Hi</button> --}}
```

Merge rules:

- `class` concatenates: child value first, then the parent's.
- Any other conflict keeps the child value (`id="child"` above).
- The `asChild` marker never reaches HTML, on or off.
- The parent's conditionals (`->merge()`, `->class([...])`) resolve
  before merging, same as the normal Blade path.

### The three ways to flag it

They do exactly the same; use the first by default:

| Syntax | Example | Note |
|---|---|---|
| Bare (recommended) | `<x-card asChild ...>` | Always on |
| Bound | `<x-card :asChild="$cond" ...>` | Accepts a boolean (see below) |
| Directive | `@asChild` inside the slot | Also detected in the tag (see below) |

The directive is also detected written inside the tag
(`<x-card @asChild ...>`), because Blade never executes directives there.

> In class-component views the directive variable is not visible:
> use the bare or bound syntax there.

### Turning it off with a boolean

All three syntaxes accept a value; when falsy, the slot renders
untouched (the marker is still stripped):

```blade
<x-button :asChild="$isLink">
```

Falsy means: `false`, `null`, `0` / `0.0`, `'0'`, `''`, `'false'` / `'no'`
(case-insensitive, trimmed). Anything else enables the merge.

### Leading comments

Leading HTML comments are skipped to find the first real tag and are
re-emitted verbatim:

```blade
<x-button asChild class="p-4">
    <!-- label -->
    <a href="#">Link</a>
</x-button>
{{-- merges onto the <a>, the comment is preserved --}}
```

An unclosed comment, or a comments-only slot, comes back untouched.
The single-root check counts real tags only.

### Single-root contract

- A single-root slot merges onto it.
- A multi-root slot throws `SiddharthaGF\XMorph\MultipleRootElementsException`:
  never a silent first-only merge.
- Markup the parser cannot read safely (unbalanced quotes, unparsable
  tags) comes back untouched instead of breaking HTML.

Example fixtures live under `resources/views/examples/`.

## Custom parser

The bundled simple parser is used by default
(`Parsers\RootElementParser`, no DOM, no dependencies). To use another
one, implement `SiddharthaGF\XMorph\Contracts\HtmlParser`:

```php
use SiddharthaGF\XMorph\AsChild;
use SiddharthaGF\XMorph\Contracts\HtmlParser;

final class MyParser implements HtmlParser
{
    public function merge(string $slotHtml, string $attributeString): string
    {
        // ...
    }
}

// Per call:
AsChild::renderSlot($attributes, $slotHtml, new MyParser);

// Pinned for tests/container-less hosts (null restores):
AsChild::useParser(new MyParser);

// In Laravel, bind it and it is picked up:
// $app->singleton(HtmlParser::class, MyParser::class);
```

Contract: merge onto the single root, throw
`MultipleRootElementsException` on multi-root, and return unsafe markup
untouched — never corrupt.

## Livewire children

When the child is a Livewire component, the merge applies on first paint
like with any other child. No cache involved: the package stores nothing
anywhere.

Known limitation: when the child updates alone (no parent in the render
tree), the first-paint merge is lost — Livewire re-renders the child
from scratch and there is nowhere to recover the parent attributes
from. Updates through the host (parent re-renders) merge again with no
issue.

## API reference

Behavior is documented above; these are the seams:

- `AsChild::renderSlot($attributes, $slotHtml, ?HtmlParser $parser = null)`
  — the merge (see Custom parser).
- `AsChild::useParser(?HtmlParser)` — pins the parser outside the container.
- `XmorphServiceProvider::consumeAsChildFlag($attributes): array` —
  `[bool $isAsChild, ComponentAttributeBag $stripped]`; the marker is
  always stripped, on or off.

## Development

```bash
composer test    # suite: unit, syntax matrix, functional
composer check   # pint --test + phpstan + phpunit (stops at first failure)
```

Individual gates: `composer pint` (fix style), `composer pint:test`
(check only), `composer phpstan` (static analysis, strict rules),
`composer rector` / `composer rector:dry`, `composer test:random`.
