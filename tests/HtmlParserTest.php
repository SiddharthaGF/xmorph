<?php

declare(strict_types=1);

namespace SiddharthaGF\XMorph\Tests;

use Illuminate\Container\Container;
use Illuminate\View\ComponentAttributeBag;
use PHPUnit\Framework\TestCase;
use SiddharthaGF\XMorph\AsChild;
use SiddharthaGF\XMorph\Contracts\HtmlParser;
use SiddharthaGF\XMorph\Tests\Support\StubHtmlParser;

/**
 * Parser seam: per-call argument, static override, container binding,
 * then the bundled default — in that order.
 */
final class HtmlParserTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Container::setInstance(new Container);
        AsChild::useParser(null);
    }

    protected function tearDown(): void
    {
        AsChild::useParser(null);
        Container::setInstance(new Container);

        parent::tearDown();
    }

    public function test_container_binding_is_used_without_overrides(): void
    {
        $stub = new StubHtmlParser('STUBBED');

        Container::getInstance()->bind(HtmlParser::class, fn () => $stub);

        self::assertSame(
            'STUBBED',
            AsChild::renderSlot(new ComponentAttributeBag(['class' => 'btn-class']), '<button>Hi</button>')
        );
    }

    public function test_default_is_the_bundled_simple_parser(): void
    {
        self::assertSame(
            '<button class="c1 p-4">Hi</button>',
            AsChild::renderSlot(
                new ComponentAttributeBag(['class' => 'p-4']),
                '<button class="c1">Hi</button>'
            )
        );
    }

    public function test_per_call_parser_wins_over_everything(): void
    {
        $stub = new StubHtmlParser('STUBBED');

        Container::getInstance()->bind(HtmlParser::class, fn (): StubHtmlParser => new StubHtmlParser('CONTAINER'));
        AsChild::useParser(new StubHtmlParser('OVERRIDE'));

        $out = AsChild::renderSlot(
            new ComponentAttributeBag(['class' => 'btn-class']),
            '<button class="c1">Hi</button>',
            $stub
        );

        self::assertSame('STUBBED', $out);
        self::assertSame('<button class="c1">Hi</button>', $stub->lastSlotHtml);
        self::assertSame('class="btn-class"', $stub->lastAttributeString);
    }

    public function test_use_parser_override_replaces_default(): void
    {
        $stub = new StubHtmlParser('STUBBED');

        AsChild::useParser($stub);

        self::assertSame(
            'STUBBED',
            AsChild::renderSlot(new ComponentAttributeBag(['class' => 'btn-class']), '<button>Hi</button>')
        );

        AsChild::useParser(null);

        self::assertSame(
            '<button class="btn-class">Hi</button>',
            AsChild::renderSlot(new ComponentAttributeBag(['class' => 'btn-class']), '<button>Hi</button>')
        );
    }
}
