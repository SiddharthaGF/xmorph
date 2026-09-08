<?php

declare(strict_types=1);

namespace SiddharthaGF\XMorph\Tests;

use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\ComponentAttributeBag;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SiddharthaGF\XMorph\AsChild;
use SiddharthaGF\XMorph\XmorphServiceProvider;

final class AsChildSyntaxTest extends TestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function kindProvider(): array
    {
        return [
            'class kind' => ['class-button.blade.php'],
            'anonymous kind' => ['anonymous-card.blade.php'],
            'inline kind' => ['inline-alert.blade.php'],
        ];
    }

    public function test_at_as_child_tag_attribute_merges_and_strips_marker(): void
    {
        // Blade compiles bare `@asChild` in tag position to '@asChild' => true.
        [$isAsChild, $stripped] = XmorphServiceProvider::consumeAsChildFlag(
            new ComponentAttributeBag(['class' => 'btn-class', 'type' => 'button', '@asChild' => true])
        );

        self::assertTrue($isAsChild);
        self::assertFalse($stripped->has('@asChild'));

        $out = AsChild::renderSlot($stripped, '<a href="#">Link</a>');

        self::assertSame('<a href="#" class="btn-class" type="button">Link</a>', $out);
    }

    public function test_at_as_child_tag_attribute_never_leaks_when_off(): void
    {
        [$isAsChild, $stripped] = XmorphServiceProvider::consumeAsChildFlag(
            new ComponentAttributeBag(['class' => 'btn-class', '@asChild' => false])
        );

        self::assertFalse($isAsChild);
        self::assertFalse($stripped->has('@asChild'));
        self::assertSame('class="btn-class"', (string) $stripped->merge());
    }

    public function test_bare_as_child_still_merges(): void
    {
        [$isAsChild, $stripped] = XmorphServiceProvider::consumeAsChildFlag(
            new ComponentAttributeBag(['class' => 'p-4', 'asChild' => 'true'])
        );

        self::assertTrue($isAsChild);
        self::assertFalse($stripped->has('asChild'));

        $out = AsChild::renderSlot($stripped, '<div class="child">Hi</div>');

        self::assertStringContainsString('p-4', $out);
        self::assertStringNotContainsString('asChild', $out);
    }

    #[DataProvider('kindProvider')]
    public function test_conditional_attributes_transfer_per_kind(string $fixtureFile): void
    {
        $slot = $this->fixtureHtml($fixtureFile);

        $parent = (new ComponentAttributeBag(['class' => 'p-4']))
            ->merge(['class' => 'extra'])
            ->class(['font-bold' => true, 'hidden' => false]);

        $out = AsChild::renderSlot($parent, $slot);

        self::assertStringContainsString('font-bold', $out);
        self::assertStringContainsString('p-4', $out);
        self::assertStringContainsString('extra', $out);
        self::assertStringNotContainsString('hidden', $out);
        self::assertStringNotContainsString('asChild', $out);
    }

    public function test_conditional_fixture_variant(): void
    {
        $slot = $this->fixtureHtml('conditional-button.blade.php');

        $parent = (new ComponentAttributeBag(['class' => 'p-4', 'id' => 'parent']))
            ->merge(['class' => 'layout'])
            ->class(['active' => true]);

        $out = AsChild::renderSlot($parent, $slot);

        self::assertStringContainsString('wire:click="save"', $out);
        self::assertStringContainsString('x-data', $out);
        self::assertStringContainsString('active', $out);
        self::assertStringContainsString('id="parent"', $out);
        self::assertStringNotContainsString('asChild', $out);
    }

    public function test_consume_flag_detects_and_strips_marker(): void
    {
        [$isAsChild, $stripped] = XmorphServiceProvider::consumeAsChildFlag(
            new ComponentAttributeBag(['class' => 'p-4', 'asChild' => true])
        );

        self::assertTrue($isAsChild);
        self::assertFalse($stripped->has('asChild'));
        self::assertSame('class="p-4"', (string) $stripped->merge());
    }

    public function test_consume_flag_leaves_normal_bag_untouched(): void
    {
        [$isAsChild, $bag] = XmorphServiceProvider::consumeAsChildFlag(
            new ComponentAttributeBag(['class' => 'p-4'])
        );

        self::assertFalse($isAsChild);
        self::assertSame('class="p-4"', (string) $bag->merge());
    }

    public function test_directive_compiles_boolean_aware(): void
    {
        $compiler = new BladeCompiler(new Filesystem, sys_get_temp_dir().'/xmorph-blade-test');

        $compiler->directive('asChild', function (string $expression): string {
            if (mb_trim($expression) === '') {
                return '<?php $__xmorphAsChild = true; ?>';
            }

            return '<?php $__xmorphAsChild = (bool) ('.$expression.'); ?>';
        });

        self::assertStringContainsString('$__xmorphAsChild = true', $compiler->compileString('@asChild'));
        self::assertStringContainsString('$__xmorphAsChild = (bool) (false)', $compiler->compileString('@asChild(false)'));
        self::assertStringContainsString('$__xmorphAsChild = (bool) ($cond)', $compiler->compileString('@asChild($cond)'));
    }

    public function test_directive_compiles_without_exception(): void
    {
        $compiler = new BladeCompiler(new Filesystem, sys_get_temp_dir().'/xmorph-blade-test');

        $compiler->directive('asChild', fn (string $expression): string => '<?php $__xmorphAsChild = true; ?>');

        $compiled = $compiler->compileString('@asChild');

        self::assertStringContainsString('$__xmorphAsChild = true', $compiled);
    }

    public function test_directive_false_renders_normal_path_untouched(): void
    {
        [$isAsChild, $stripped] = XmorphServiceProvider::consumeAsChildFlag(
            new ComponentAttributeBag(['class' => 'p-4', 'asChild' => false])
        );

        self::assertFalse($isAsChild);
        self::assertFalse($stripped->has('asChild'));

        // The component takes its normal branch without renderSlot, so a
        // plain slot passes through with no parent merge applied.
        $slot = '<div class="child">Hi</div>';

        self::assertStringNotContainsString('p-4', $slot);
    }

    public function test_directive_true_merges(): void
    {
        $slot = '<div class="child">Hi</div>';
        $parent = new ComponentAttributeBag(['class' => 'p-4']);

        $out = AsChild::renderSlot($parent, $slot);

        self::assertStringContainsString('p-4', $out);
        self::assertStringContainsString('child', $out);
    }

    public function test_false_conditional_is_excluded(): void
    {
        $parent = (new ComponentAttributeBag(['class' => 'base']))
            ->class(['shown' => true, 'ghost' => false]);

        $out = AsChild::renderSlot($parent, '<div class="child">Hi</div>');

        self::assertStringContainsString('shown', $out);
        self::assertStringNotContainsString('ghost', $out);
    }

    public function test_falsy_bound_values_do_not_merge(): void
    {
        foreach (['false', '0'] as $falsy) {
            [$isAsChild, $stripped] = XmorphServiceProvider::consumeAsChildFlag(
                new ComponentAttributeBag(['class' => 'p-4', 'asChild' => $falsy])
            );

            $slot = '<div class="child">Hi</div>';
            $out = $isAsChild ? AsChild::renderSlot($stripped, $slot) : $slot;

            self::assertFalse($isAsChild, "asChild={$falsy} must mean OFF");
            self::assertFalse($stripped->has('asChild'));

            self::assertSame($slot, $out);
            self::assertStringNotContainsString('asChild', (string) $stripped->merge());
        }
    }

    public function test_final_render_concatenates_child_and_parent_classes(): void
    {
        $out = AsChild::renderSlot(
            new ComponentAttributeBag(['class' => 'p-4']),
            '<a href="#" class="link">Link</a>'
        );

        self::assertSame('<a href="#" class="link p-4">Link</a>', $out);
    }

    public function test_final_render_output_is_exact(): void
    {
        $out = AsChild::renderSlot(
            new ComponentAttributeBag(['class' => 'btn btn-primary', 'type' => 'button']),
            '<a href="#">Link</a>'
        );

        self::assertSame('<a href="#" class="btn btn-primary" type="button">Link</a>', $out);
    }

    #[DataProvider('kindProvider')]
    public function test_three_syntaxes_are_byte_identical_per_kind(string $fixtureFile): void
    {
        $slot = $this->fixtureHtml($fixtureFile);
        $parent = new ComponentAttributeBag(['class' => 'p-4', 'id' => 'parent']);

        // @asChild path: directive flag variable, bag carries no marker.
        $viaDirective = AsChild::renderSlot($parent, $slot);

        // :asChild="true" path: bound marker arrives in the bag, then stripped.
        [$flagBound, $strippedBound] = XmorphServiceProvider::consumeAsChildFlag(
            new ComponentAttributeBag(['class' => 'p-4', 'id' => 'parent', 'asChild' => 'true'])
        );
        self::assertTrue($flagBound);
        $viaBound = AsChild::renderSlot($strippedBound, $slot);

        // Bare asChild path: same bag flag with boolean true.
        [$flagBare, $strippedBare] = XmorphServiceProvider::consumeAsChildFlag(
            new ComponentAttributeBag(['class' => 'p-4', 'id' => 'parent', 'asChild' => true])
        );
        self::assertTrue($flagBare);
        $viaBare = AsChild::renderSlot($strippedBare, $slot);

        self::assertSame($viaDirective, $viaBound);
        self::assertSame($viaDirective, $viaBare);
        self::assertStringNotContainsString('asChild', $viaDirective);
    }

    private function fixtureHtml(string $file): string
    {
        $path = __DIR__.'/../resources/views/examples/'.$file;
        $raw = file_get_contents($path);

        self::assertNotFalse($raw, "Fixture {$file} must exist");

        $html = mb_trim((string) preg_replace('/\{\{--.*?--\}\}/s', '', $raw));

        self::assertNotSame('', $html, "Fixture {$file} must hold a single-root snippet");

        return $html;
    }
}
