<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PwaTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_layout_exposes_installable_pwa_assets(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('rel="manifest" href="/manifest.webmanifest?v=3"', false)
            ->assertSee('rel="icon" type="image/svg+xml" href="/favicon.svg?v=3"', false)
            ->assertSee('id="pwa-install"', false)
            ->assertSee('Install app');

        foreach ([
            'manifest.webmanifest',
            'service-worker.js',
            'offline.html',
            'pwa.js',
            'pwa.css',
            'favicon.svg',
            'pwa/icon-192.png',
            'pwa/icon-512.png',
            'pwa/icon-maskable-512.png',
        ] as $asset) {
            $this->assertFileExists(public_path($asset));
        }

        $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('./', $manifest['start_url']);
        $this->assertSame('./', $manifest['scope']);
        $this->assertSame('pwa/icon-192.png', $manifest['icons'][0]['src']);
        $this->assertCount(3, $manifest['icons']);
    }
}
