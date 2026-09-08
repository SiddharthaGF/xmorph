<?php

declare(strict_types=1);

namespace SiddharthaGF\XMorph\Tests\Functional;

use Illuminate\View\Factory;
use Livewire\Livewire;
use Livewire\LivewireManager;
use SiddharthaGF\XMorph\Tests\Fixtures\AsChildHost;
use SiddharthaGF\XMorph\Tests\Fixtures\Counter;
use SiddharthaGF\XMorph\Tests\TestCase;

/**
 * Canonical E2E port: `<x-button @asChild><livewire:counter /></x-button>`
 * rendered through full Blade compilation inside a real Livewire host.
 *
 * Documented limitation: a child-alone update (driven from the child
 * snapshot, without the parent in the render tree) loses the first-paint
 * merge — nothing persists it. Host-driven updates re-render the parent,
 * so the merge applies again there.
 */
final class NestedAsChildTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $app = $this->app;

        self::assertNotNull($app);

        $view = $app->make(Factory::class);

        self::assertInstanceOf(Factory::class, $view);

        $view->addNamespace('xmorph', dirname(__DIR__).'/Fixtures/views');

        Livewire::component('xmorph-counter', Counter::class);
        Livewire::component('xmorph-as-child-host', AsChildHost::class);
    }

    public function test_child_driven_update_without_parent_loses_first_paint_merge(): void
    {
        $firstPaint = Livewire::test('xmorph-as-child-host')->html();

        // First paint merges the parent class onto the Livewire child root.
        self::assertSame(1, mb_substr_count($firstPaint, 'btn-class'));

        [$childId, $snapshot] = $this->extractChildSnapshot($firstPaint);

        $updated = $this->updateChild($snapshot, 'increment');

        // The child root keeps its identity and the counter advances, but
        // the parent merge is gone: the parent is not in this render tree
        // and nothing persists the merge (documented limitation).
        self::assertStringContainsString('wire:id="'.$childId.'"', $updated['html']);
        self::assertStringContainsString('<span id="count">1</span>', $updated['html']);
        self::assertStringNotContainsString('btn-class', $updated['html']);
        self::assertStringNotContainsString('<button', $updated['html']);
        self::assertStringNotContainsString('asChild', $updated['html']);
    }

    public function test_initial_host_render_merges_parent_class_onto_livewire_child_root(): void
    {
        $html = Livewire::test('xmorph-as-child-host')->html();

        // No wrapper element: the button parent disappears into the child root.
        self::assertStringNotContainsString('<button', $html);
        self::assertStringNotContainsString('asChild', $html);

        // The parent class lands on the Livewire child root exactly once.
        self::assertSame(1, mb_substr_count($html, 'btn-class'));

        [$childId] = $this->extractChildSnapshot($html);

        self::assertMatchesRegularExpression(
            '/<div[^>]*wire:id="'.preg_quote($childId, '/').'"[^>]*class="[^"]*btn-class[^"]*"[^>]*>/',
            $html
        );
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function extractChildSnapshot(string $hostHtml): array
    {
        preg_match_all('/wire:snapshot="([^"]*)"/', $hostHtml, $matches);

        self::assertNotEmpty($matches[1], 'Host HTML must embed Livewire snapshots');

        foreach ($matches[1] as $raw) {
            $decoded = json_decode(html_entity_decode($raw), true);

            if (! is_array($decoded)) {
                continue;
            }

            $memo = $decoded['memo'] ?? null;

            if (! is_array($memo)) {
                continue;
            }

            if (($memo['name'] ?? null) === 'xmorph-counter') {
                $id = $memo['id'] ?? null;

                self::assertIsString($id);

                /** @var array<string, mixed> $decoded */
                return [$id, $decoded];
            }
        }

        self::fail('Host HTML must embed the nested counter snapshot');
    }

    /**
     * Drive a real Livewire update request for the given child snapshot,
     * exactly like the browser does for a nested child-alone update.
     *
     * @param  array<string, mixed>  $snapshot
     * @return array{html: string, snapshot: array<string, mixed>}
     */
    private function updateChild(array $snapshot, string $method): array
    {
        $app = $this->app;

        self::assertNotNull($app);

        $livewire = $app->make(LivewireManager::class);

        self::assertInstanceOf(LivewireManager::class, $livewire);

        $uri = $livewire->getUpdateUri();

        self::assertIsString($uri);

        $response = $this->withoutMiddleware()->post(
            $uri,
            ['components' => [[
                'snapshot' => json_encode($snapshot),
                'calls' => [['method' => $method, 'params' => [], 'path' => '']],
                'updates' => [],
            ]]],
            ['X-Livewire' => 'true']
        );

        $response->assertOk();

        $html = $response->json('components.0.effects.html');
        $rawSnapshot = $response->json('components.0.snapshot');

        self::assertIsString($html, 'Update response must carry re-rendered HTML');
        self::assertIsString($rawSnapshot, 'Update response must carry the next snapshot');

        $decoded = json_decode($rawSnapshot, true);

        self::assertIsArray($decoded);

        /** @var array<string, mixed> $decoded */
        return ['html' => $html, 'snapshot' => $decoded];
    }
}
