<?php

namespace Tests\Unit;

use App\Support\InspirationEmbed;
use PHPUnit\Framework\TestCase;

class InspirationEmbedTest extends TestCase
{
    public function test_instagram_reel_becomes_an_embed_player(): void
    {
        $embed = InspirationEmbed::resolve('instagram', 'https://www.instagram.com/reel/CxampleReel1/?igsh=abc');

        $this->assertSame('https://www.instagram.com/reel/CxampleReel1/embed', $embed['embed_url']);
        $this->assertSame('iframe', $embed['kind']);
    }

    public function test_youtube_watch_link_becomes_an_embed_player(): void
    {
        $embed = InspirationEmbed::resolve('youtube', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ');

        $this->assertSame('https://www.youtube.com/embed/dQw4w9WgXcQ', $embed['embed_url']);
    }

    public function test_tiktok_video_link_becomes_an_embed_player(): void
    {
        $embed = InspirationEmbed::resolve('tiktok', 'https://www.tiktok.com/@primez/video/1234567890123456789');

        $this->assertSame('https://www.tiktok.com/embed/v2/1234567890123456789', $embed['embed_url']);
    }
}
