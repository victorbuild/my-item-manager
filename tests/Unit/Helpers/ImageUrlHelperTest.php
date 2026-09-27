<?php

namespace Tests\Unit\Helpers;

use App\Helpers\ImageUrlHelper;
use App\Models\ItemImage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ImageUrlHelperTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Carbon::setTestNow('2026-09-27 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function it_should_only_generate_requested_variants(): void
    {
        $urls = ImageUrlHelper::generateSignedUrls(
            $this->createImage(),
            60,
            ['thumb', 'preview']
        );

        $this->assertSame(['thumb_url', 'preview_url'], array_keys($urls));
    }

    #[Test]
    public function it_should_reuse_signed_url_during_cache_lifetime(): void
    {
        $image = $this->createImage();
        $firstUrls = ImageUrlHelper::generateSignedUrls($image, 60, ['thumb']);

        Carbon::setTestNow('2026-09-27 10:10:00');
        $secondUrls = ImageUrlHelper::generateSignedUrls($image, 60, ['thumb']);

        $this->assertSame($firstUrls['thumb_url'], $secondUrls['thumb_url']);
    }

    private function createImage(): ItemImage
    {
        return new ItemImage([
            'uuid' => '01999f3e-e800-7000-8000-000000000001',
            'image_path' => 'test-image',
            'original_extension' => 'jpg',
        ]);
    }
}
