<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class PwaTest extends TestCase
{
    public function test_the_manifest_declares_what_installability_requires(): void
    {
        $manifest = $this->manifest();

        $this->assertSame('Suivre', $manifest['name']);
        $this->assertSame('Suivre', $manifest['short_name']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('/', $manifest['start_url']);
        $this->assertSame('/', $manifest['scope']);
    }

    public function test_the_manifest_colours_track_the_apps_own_background(): void
    {
        // Both track `--background` (light) in app.css. A default white splash
        // followed by the app's off-white reads as a flash on launch.
        $manifest = $this->manifest();

        $this->assertSame('#fbfbfa', $manifest['theme_color']);
        $this->assertSame('#fbfbfa', $manifest['background_color']);
    }

    public function test_the_manifest_offers_both_any_and_maskable_icons_at_192_and_512(): void
    {
        $manifest = $this->manifest();

        $declared = [];

        foreach ($manifest['icons'] as $icon) {
            $declared[] = $icon['purpose'] . ' ' . $icon['sizes'];

            $this->assertFileExists(
                public_path(ltrim((string) $icon['src'], '/')),
                "The manifest declares {$icon['src']}, which is not on disk.",
            );
        }

        foreach (['any 192x192', 'any 512x512', 'maskable 192x192', 'maskable 512x512'] as $required) {
            $this->assertContains($required, $declared);
        }
    }

    public function test_the_apple_touch_icon_is_present_for_ios_add_to_home_screen(): void
    {
        // iOS ignores the manifest icons entirely for add-to-home-screen.
        $this->assertFileExists(public_path('apple-touch-icon.png'));
    }

    public function test_the_shell_links_the_manifest_and_the_ios_metadata(): void
    {
        $response = $this->get(route('login'))->assertOk();

        $response->assertSee('<link rel="manifest" href="/manifest.webmanifest">', escape: false);
        $response->assertSee('name="apple-mobile-web-app-capable" content="yes"', escape: false);
        $response->assertSee('name="apple-mobile-web-app-title" content="Suivre"', escape: false);
        $response->assertSee('name="apple-mobile-web-app-status-bar-style" content="black-translucent"', escape: false);
    }

    public function test_the_shell_declares_a_theme_colour_for_each_scheme(): void
    {
        $response = $this->get(route('login'))->assertOk();

        $response->assertSee('media="(prefers-color-scheme: light)" content="#fbfbfa"', escape: false);
        $response->assertSee('media="(prefers-color-scheme: dark)" content="#131314"', escape: false);
    }

    public function test_the_viewport_opts_into_the_safe_area_insets(): void
    {
        // Without `viewport-fit=cover` the safe-area-inset-* variables the tab
        // bar pads itself with all resolve to zero, and the home indicator
        // overlaps the tabs.
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('viewport-fit=cover', escape: false);
    }

    public function test_the_service_worker_is_served_from_the_root_scope(): void
    {
        // A worker served from a subdirectory cannot control the whole app.
        $this->assertFileExists(public_path('sw.js'));
    }

    public function test_the_service_worker_never_caches_documents_or_data(): void
    {
        $worker = file_get_contents(public_path('sw.js'));

        $this->assertIsString($worker);

        // The first Inertia response embeds the user's own conditions, ratings
        // and flares in the HTML, so a cached document is cached health data.
        // These two guards are what keep the worker off everything but Vite's
        // immutable hashed output; asserting them here means a later edit that
        // widens the scope has to face this test.
        $this->assertStringContainsString("request.mode === 'navigate'", $worker);
        $this->assertStringContainsString('startsWith(ASSET_PREFIX)', $worker);
    }

    /**
     * @return array<string, mixed>
     */
    private function manifest(): array
    {
        $path = public_path('manifest.webmanifest');

        $this->assertFileExists($path);

        $contents = file_get_contents($path);

        $this->assertIsString($contents);

        $manifest = json_decode($contents, true);

        $this->assertIsArray($manifest, 'The manifest is not valid JSON.');

        return $manifest;
    }
}
