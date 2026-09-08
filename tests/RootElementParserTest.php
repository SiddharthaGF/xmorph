<?php

declare(strict_types=1);

namespace SiddharthaGF\XMorph\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SiddharthaGF\XMorph\MultipleRootElementsException;
use SiddharthaGF\XMorph\Parsers\RootElementParser;

final class RootElementParserTest extends TestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function fallbackProvider(): array
    {
        return [
            'plain text has no root tag' => ['Just text'],
            'empty string' => [''],
            'unbalanced double quote' => ['<div class="oops>content</div>'],
            'unbalanced comment markers' => ['<div><!-- <span>x</span></div>'],
            'unclosed root tag' => ['<div class="c1">Hi'],
            'unclosed leading comment' => ['<!-- unclosed <div>Hi</div>'],
            'unclosed second leading comment' => ['<!-- ok --><!-- unclosed <div>Hi</div>'],
            'comment-only markup' => ['<!-- just a comment -->'],
            'comment-only with surrounding whitespace' => ['  <!-- just a comment -->  '],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function singleRootProvider(): array
    {
        return [
            'void element' => [
                '<img src="a.png">',
                'class="p-4"',
                '<img src="a.png" class="p-4">',
            ],
            'void input without closing tag' => [
                '<input type="text">',
                'id="parent"',
                '<input type="text" id="parent">',
            ],
            'self-closing tag' => [
                '<input type="text" />',
                'class="p-4"',
                '<input type="text" class="p-4"/>',
            ],
            'multiline first tag' => [
                "<button\n    type=\"button\"\n    class=\"c1\"\n>Hi</button>",
                'id="parent"',
                "<button\n    type=\"button\"\n    class=\"c1\" id=\"parent\">Hi</button>",
            ],
            'alpine attributes preserved' => [
                '<button x-data="{ open: false }" @click="open = true">Save</button>',
                'class="p-4"',
                '<button x-data="{ open: false }" @click="open = true" class="p-4">Save</button>',
            ],
            'livewire wire:click preserved' => [
                '<button wire:click="save" class="btn">Save</button>',
                'id="parent"',
                '<button wire:click="save" class="btn" id="parent">Save</button>',
            ],
            'colon bind attribute preserved' => [
                '<div :title="heading">Hi</div>',
                'id="parent"',
                '<div :title="heading" id="parent">Hi</div>',
            ],
            'comment containing same tag is ignored' => [
                '<div><!-- </div> --><span>x</span></div>',
                'id="parent"',
                '<div id="parent"><!-- </div> --><span>x</span></div>',
            ],
            'leading whitespace preserved' => [
                '  <div class="c1">Hi</div>',
                'id="parent"',
                '  <div class="c1" id="parent">Hi</div>',
            ],
            'leading comment preserved verbatim' => [
                '<!-- hola --><a href="#">Link</a>',
                'class="p-4"',
                '<!-- hola --><a href="#" class="p-4">Link</a>',
            ],
            'multiple leading comments preserved verbatim' => [
                '<!-- one --><!-- two --><div>Hi</div>',
                'id="parent"',
                '<!-- one --><!-- two --><div id="parent">Hi</div>',
            ],
            'leading comments with surrounding whitespace preserved' => [
                '  <!-- one -->  <!-- two -->  <div>Hi</div>',
                'id="parent"',
                '  <!-- one -->  <!-- two -->  <div id="parent">Hi</div>',
            ],
            'lone comment plus single root' => [
                '<!-- note --><div>Hi</div>',
                'id="parent"',
                '<!-- note --><div id="parent">Hi</div>',
            ],
            'livewire block marker merges onto real root' => [
                '<!--[if BLOCK]--><div>Hi</div>',
                'id="parent"',
                '<!--[if BLOCK]--><div id="parent">Hi</div>',
            ],
            'livewire block marker pair merges onto real root' => [
                '<!--[if BLOCK]><![endif]--><div>Hi</div>',
                'id="parent"',
                '<!--[if BLOCK]><![endif]--><div id="parent">Hi</div>',
            ],
        ];
    }

    public function test_class_attribute_with_data_prefix_is_left_alone(): void
    {
        $out = (new RootElementParser)->merge(
            '<div data-class="d" class="c">Hi</div>',
            'class="p-4"'
        );

        self::assertSame('<div data-class="d" class="c p-4">Hi</div>', $out);
    }

    public function test_class_concat_child_first_and_child_wins_non_class(): void
    {
        $out = (new RootElementParser)->merge(
            '<button class="c1" id="child">Hi</button>',
            'class="p-4" id="parent"'
        );

        self::assertSame('<button class="c1 p-4" id="child">Hi</button>', $out);
    }

    public function test_close_tag_inside_attribute_value_still_merges(): void
    {
        $out = (new RootElementParser)->merge(
            '<div><span title="</div>">x</span></div>',
            'class="p-4"'
        );

        self::assertSame('<div class="p-4"><span title="</div>">x</span></div>', $out);
    }

    public function test_deep_nesting_completes_in_linear_time(): void
    {
        $slot = str_repeat('<div>', 3000).'x'.str_repeat('</div>', 3000);

        $start = microtime(true);

        $out = (new RootElementParser)->merge($slot, 'class="p-4"');

        self::assertLessThan(5.0, microtime(true) - $start);
        self::assertStringStartsWith('<div class="p-4">', $out);
    }

    public function test_empty_merged_string_returns_slot_untouched(): void
    {
        $slot = '<button class="c1">Hi</button>';

        self::assertSame($slot, (new RootElementParser)->merge($slot, ''));
        self::assertSame($slot, (new RootElementParser)->merge($slot, '   '));
    }

    public function test_inserted_attributes_are_alphabetical_for_determinism(): void
    {
        $out = (new RootElementParser)->merge(
            '<div>hi</div>',
            'id="parent" data-role="x" class="p-4"'
        );

        self::assertSame('<div class="p-4" data-role="x" id="parent">hi</div>', $out);
    }

    public function test_leading_comment_multi_root_still_throws(): void
    {
        $this->expectException(MultipleRootElementsException::class);

        (new RootElementParser)->merge('<!-- note --><button>A</button><button>B</button>', 'class="p-4"');
    }

    public function test_multi_root_throws(): void
    {
        $this->expectException(MultipleRootElementsException::class);

        (new RootElementParser)->merge('<button>A</button><button>B</button>', 'class="p-4"');
    }

    public function test_multibyte_sibling_still_throws(): void
    {
        $this->expectException(MultipleRootElementsException::class);

        (new RootElementParser)->merge('<div>éééééé</div><b>', 'class="p-4"');
    }

    public function test_multibyte_single_root_merges(): void
    {
        self::assertSame(
            '<div class="p-4">ééé</div>',
            (new RootElementParser)->merge('<div>ééé</div>', 'class="p-4"')
        );
    }

    public function test_nested_same_tag_does_not_throw(): void
    {
        $out = (new RootElementParser)->merge(
            '<div><div>inner</div></div>',
            'id="parent"'
        );

        self::assertSame('<div id="parent"><div>inner</div></div>', $out);
    }

    public function test_quote_in_parent_class_returns_untouched(): void
    {
        $slot = '<button class="c1">Hi</button>';

        self::assertSame($slot, (new RootElementParser)->merge($slot, 'class="a\"b"'));
    }

    public function test_quoted_text_hiding_close_still_throws(): void
    {
        $this->expectException(MultipleRootElementsException::class);

        (new RootElementParser)->merge('<div>a \'x </div> y\' z</div>', 'class="p-4"');
    }

    public function test_self_closing_nested_same_tag_merges(): void
    {
        self::assertSame(
            '<div class="p-4"><div/></div>',
            (new RootElementParser)->merge('<div><div/></div>', 'class="p-4"')
        );
    }

    public function test_self_closing_root_followed_by_sibling_throws(): void
    {
        $this->expectException(MultipleRootElementsException::class);

        (new RootElementParser)->merge('<input type="text" /><span>sibling</span>', 'class="p-4"');
    }

    #[DataProvider('singleRootProvider')]
    public function test_single_root_merge(string $slot, string $attrs, string $expected): void
    {
        self::assertSame($expected, (new RootElementParser)->merge($slot, $attrs));
    }

    public function test_tag_like_text_inside_script_still_merges(): void
    {
        $slot = '<div><script>if (a < b) { x("</div>"); }</script><p>t</p></div>';

        $out = (new RootElementParser)->merge($slot, 'class="p-4"');

        self::assertStringStartsWith('<div class="p-4"><script>', $out);
        self::assertStringEndsWith('</p></div>', $out);
    }

    #[DataProvider('fallbackProvider')]
    public function test_unparseable_slot_returns_untouched(string $slot): void
    {
        self::assertSame($slot, (new RootElementParser)->merge($slot, 'class="p-4"'));
    }

    public function test_void_root_followed_by_sibling_throws(): void
    {
        $this->expectException(MultipleRootElementsException::class);

        (new RootElementParser)->merge('<img src="a.png"><span>sibling</span>', 'class="p-4"');
    }
}
