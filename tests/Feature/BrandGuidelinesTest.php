<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandGuidelinesTest extends TestCase
{
    use RefreshDatabase;

    private const FILES = [
        'index.html',
        'prime-z-brand-guidelines.jpg',
        'prime-z-brand-guidelines-1200.webp',
        'prime-z-brand-guidelines-2179.webp',
        'prime-z-logo.png',
        'prime-z-logo-white.png',
        'prime-z-footer-ribbon.png',
        'prime-z-footer-ribbon-supplement.png',
    ];

    public function test_the_guidelines_page_and_every_image_it_offers_are_published(): void
    {
        $dir = public_path('brand/guidelines');
        foreach (self::FILES as $file) {
            $this->assertFileExists($dir.'/'.$file);
        }

        $html = (string) file_get_contents($dir.'/index.html');
        foreach (['prime-z-brand-guidelines.jpg', 'prime-z-logo.png', 'prime-z-logo-white.png', 'prime-z-footer-ribbon.png', 'prime-z-footer-ribbon-supplement.png'] as $image) {
            $this->assertStringContainsString('href="'.$image.'" download="'.$image.'"', $html, $image.' has no download button.');
        }
    }

    public function test_the_login_page_links_to_the_guidelines_and_rollet_still_works(): void
    {
        $login = (string) file_get_contents(resource_path('js/Pages/Auth/Login.tsx'));
        $this->assertStringContainsString("/brand/guidelines/index.html", $login);
        $this->assertStringNotContainsString("'/rollet'", $login);

        $this->get('/login')->assertOk();
        $this->get('/rollet')->assertOk();
    }
}
