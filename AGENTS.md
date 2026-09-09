# XMorph — agent notes

Library: Radix-style `asChild` for Blade/Livewire (`siddharthagf/xmorph`,
namespace `SiddharthaGF\XMorph\`). README.md is the user docs (English) —
keep it in sync with behavior changes.

## Commands

- `composer check` — the gate: `pint:test` + `phpstan` + `test`, stops at
  first failure. Run it before committing.
- `composer test` / `vendor/bin/phpunit --filter <TestClass|test_name>`
  for a focused run.
- `composer pint` autofixes style; `composer pint:test` only checks.
- `composer rector:dry` must stay green (exit 0). `rector.php` `withSkip`
  documents deliberate declines (static helpers, explicit throws) — don't
  "fix" those, and don't re-add skips without a reason comment.
- After editing `composer.json` deps: `composer update --lock` (lockfile
  is gitignored — library convention — never commit it).
- CI (`.github/workflows/ci.yml`) runs `composer check` on PHP 8.1–8.4
  with a fresh resolve per version (no lockfile), proving the 8.1 floor.
- CI sets `COMPOSER_NO_SECURITY_BLOCKING=1`: Laravel 10 is EOL so fresh
  resolves get advisory-blocked — don't remove it. Keep `laravel/pint`
  wide (`^1.0`): pinned `^1.31` requires PHP ^8.3 and breaks the matrix.
  Don't re-add `--parallel` without checking old pint has the flag.

## Architecture

- `src/Contracts/HtmlParser.php` — the port (single `merge()` method).
- `src/Parsers/RootElementParser.php` — bundled default adapter,
  instance-based, no statics on the public API.
- `src/AsChild.php` — static facade: `renderSlot()` (bag → normalize →
  parser), `useParser()` override. Parser priority: per-call arg >
  `useParser()` > container `HtmlParser::class` binding > default.
- `src/XmorphServiceProvider.php` — Blade `@asChild` directive, flag
  detection/stripping, `bindIf(HtmlParser::class, RootElementParser::class)`.
- Deliberately **no persistence anywhere** (no cache/session/storage).
  Consequence, locked by tests: a child-alone Livewire update loses the
  first-paint merge. Don't reintroduce storage without the owner.

## Parser invariants (earned the hard way)

- `tailAfterSingleRoot()` scanning is **byte-based**: PCRE offsets are
  bytes, so `strpos`/`substr`/`strlen` there — never `mb_*`. Mixing units
  silently mis-slices multibyte markup (past bug: silent multi-root merge).
- `pint.json` must NOT contain `mb_str_functions`: it rewrites byte
  functions to `mb_*` and reintroduces that bug. It was removed on purpose.
- Depth scan skips opaque spans (comments, `=`-prefixed quotes, script /
  style bodies). Bare quote pairs in prose must never form spans, or real
  closes get hidden — the `=` prefix is load-bearing.
- Fail closed: unsafe input → return slot untouched; multi-root → throw
  `MultipleRootElementsException`. Never corrupt HTML, never merge partial.

## PHPStan (level max + strict rules)

- No `@phpstan-ignore` / baselines — owner rejects suppressions; fix the
  underlying type issue instead.
- `iterable` params need value types, but narrowing a PSR `iterable`
  breaks contravariance: use `iterable<mixed, mixed>` (identical to bare,
  satisfies both checks).
- Unused privates are errors — delete helpers/tests together with the
  code they served.

## Tests

- Plain tests extend PHPUnit's `TestCase`; app-needing tests (Livewire
  rendering) extend `tests/TestCase.php` (Testbench).
- Plain tests that touch the container: `Container::setInstance(new
  Container)` in `setUp`, reset it plus `AsChild::useParser(null)` in
  `tearDown` — suite must pass in any order (`composer test:random`).
- `tests/Support/StubHtmlParser.php` is the seam double; `tests/Fixtures/`
  + `resources/views/examples/` are all live (kindProvider + direct refs).
- New behavior needs a regression test that fails without the fix
  (multibyte, quote-context, and perf-smoke tests exist as precedent).

## Style

- Pint enforces `ordered_class_elements` (test methods alphabetical),
  `ordered_imports`, `declare_strict_types`. Let `composer pint` fix
  ordering; don't fight it by hand.
- Match the existing docblock discipline: contracts and non-obvious
  invariants get comments, self-evident code doesn't.
