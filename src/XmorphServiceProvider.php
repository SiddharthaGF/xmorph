<?php

declare(strict_types=1);

namespace SiddharthaGF\XMorph;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\ComponentAttributeBag;
use SiddharthaGF\XMorph\Contracts\HtmlParser;
use SiddharthaGF\XMorph\Parsers\RootElementParser;

use function in_array;
use function is_string;

final class XmorphServiceProvider extends ServiceProvider
{
    /**
     * Opt-in marker attribute shared by the `:asChild="true"` and bare
     * `asChild` syntaxes.
     */
    public const AS_CHILD_FLAG = 'asChild';

    /**
     * Blade compiles a bare `@asChild` inside a component tag to an
     * `@asChild => true` attribute (directives never execute in tag
     * position), so that spelling is detected and stripped here too.
     */
    public const AS_CHILD_TAG_FLAG = '@asChild';

    /**
     * Detect the asChild opt-in flag on a component attribute bag and
     * return [bool $isAsChild, ComponentAttributeBag $attributes].
     *
     * Covers `:asChild="..."`, bare `asChild`, and bare `@asChild` in
     * tag position: ComponentTagCompiler maps all three to attributes,
     * which arrive at runtime in the ComponentAttributeBag. Detection
     * evaluates the raw value as falsy-aware (`false`, `null`, `0`,
     * `'0'`, `''`, `'false'`/`'no'` case-insensitive mean OFF; everything
     * else means ON) and stripping uses Bag::except() so no marker ever
     * reaches HTML, even when OFF. The `@asChild` syntax inside the slot
     * instead emits `$__xmorphAsChild` via the Blade directive registered
     * in boot(); call sites OR that variable with this bag flag.
     *
     * @return array{0: bool, 1: ComponentAttributeBag}
     */
    public static function consumeAsChildFlag(ComponentAttributeBag $attributes): array
    {
        /** @var list<string> $flags */
        $flags = [self::AS_CHILD_FLAG, self::AS_CHILD_TAG_FLAG];

        $present = [];

        foreach ($flags as $flag) {
            if ($attributes->has($flag)) {
                $present[] = $flag;
            }
        }

        if ($present === []) {
            return [false, $attributes];
        }

        $isAsChild = false;

        foreach ($present as $flag) {
            $isAsChild = $isAsChild || self::isTruthyFlagValue($attributes->get($flag));
        }

        return [$isAsChild, $attributes->except($present)];
    }

    public function boot(): void
    {
        // Default slot parser (stateless): users may rebind HtmlParser to
        // their own implementation; bindIf keeps an existing binding.
        $this->app->bindIf(HtmlParser::class, RootElementParser::class);

        // Opt-in flag for Radix-style asChild. Compiled via
        // BladeCompiler::compileStatement() -> callCustomDirective().
        //
        // The `:asChild="true"` and bare `asChild` syntaxes take a
        // different path: ComponentTagCompiler::getAttributesFromAttributeString()
        // (via parseBindAttributes()) maps both to a bound `asChild`
        // attribute, emitted through componentString() into
        // $component->withAttributes(...). Unit 2 detects that flag at
        // runtime with ComponentAttributeBag::has() and strips it with
        // ComponentAttributeBag::except() so no marker reaches HTML.
        Blade::directive('asChild', function (string $expression): string {
            if (trim($expression) === '') {
                return '<?php $__xmorphAsChild = true; ?>';
            }

            return '<?php $__xmorphAsChild = (bool) ('.$expression.'); ?>';
        });
    }

    /**
     * Evaluate a raw bag flag value as falsy-aware.
     *
     * @param  mixed  $value
     */
    private static function isTruthyFlagValue($value): bool
    {
        if ($value === false || $value === null) {
            return false;
        }

        if ($value === 0 || $value === 0.0) {
            return false;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            if (in_array($normalized, ['', '0', 'false', 'no'], true)) {
                return false;
            }
        }

        return true;
    }
}
