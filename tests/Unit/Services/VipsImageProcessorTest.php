<?php

namespace Tests\Unit\Services;

use App\Services\VipsImageProcessor;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class VipsImageProcessorTest extends TestCase
{
    /** 測試校正 EXIF 方向並移除 EXIF 與 GPS。 */
    #[Test]
    public function it_should_rotate_and_strip_metadata_when_jpeg_has_exif(): void
    {
        $file = UploadedFile::fake()->image('photo.jpg', 80, 40);
        $jpeg = file_get_contents($file->getRealPath());
        $tiff = 'II' . pack('vV', 42, 8) . pack('v', 2)
            . pack('vvVv', 0x0112, 3, 1, 6) . "\0\0"
            . pack('vvVV', 0x8825, 4, 1, 38) . pack('V', 0)
            . pack('v', 1) . pack('vvV', 1, 2, 2) . "N\0\0\0" . pack('V', 0);
        $exif = "Exif\0\0" . $tiff;
        $content = substr($jpeg, 0, 2) . "\xff\xe1" . pack('n', strlen($exif) + 2)
            . $exif . substr($jpeg, 2);

        $result = app(VipsImageProcessor::class)->generate($content);

        foreach ($result as $encoded) {
            $dimensions = getimagesizefromstring($encoded);
            $this->assertSame(40, $dimensions[0]);
            $this->assertSame(80, $dimensions[1]);
            $this->assertSame(IMAGETYPE_WEBP, $dimensions[2]);
            $this->assertStringNotContainsString('EXIF', $encoded);
            $this->assertStringNotContainsString('XMP ', $encoded);
        }
    }

    /** 測試依設定等比例產生不同尺寸。 */
    #[Test]
    public function it_should_resize_without_cropping_when_image_is_large(): void
    {
        $file = UploadedFile::fake()->image('photo.jpg', 1200, 2400);
        $content = file_get_contents($file->getRealPath());
        $result = app(VipsImageProcessor::class)->generate($content);

        $this->assertSame([800, 1600], array_slice(getimagesizefromstring($result['preview']), 0, 2));
        $this->assertSame([200, 400], array_slice(getimagesizefromstring($result['thumb']), 0, 2));
    }

    /** 測試透明像素在 WebP 保留透明度。 */
    #[Test]
    public function it_should_preserve_transparency_when_png_is_transparent(): void
    {
        $image = imagecreatetruecolor(20, 20);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 255, 0, 0, 127));
        ob_start();
        imagepng($image);
        $content = ob_get_clean();

        $result = app(VipsImageProcessor::class)->generate($content);
        $decoded = imagecreatefromstring($result['preview']);
        $this->assertSame(127, imagecolorsforindex($decoded, imagecolorat($decoded, 0, 0))['alpha']);
    }

    /** 測試損壞資料不被送往圖片解碼器。 */
    #[Test]
    public function it_should_reject_invalid_image_data(): void
    {
        $this->expectException(HttpException::class);
        app(VipsImageProcessor::class)->generate('invalid image');
    }

    /** 測試 JPEG 標頭有效但內容截斷時回傳 422。 */
    #[Test]
    public function it_should_return_422_when_jpeg_body_is_truncated(): void
    {
        $file = UploadedFile::fake()->image('photo.jpg', 800, 600);
        $content = substr(file_get_contents($file->getRealPath()), 0, 650);
        $this->assertNotFalse(getimagesizefromstring($content));

        try {
            app(VipsImageProcessor::class)->generate($content);
            $this->fail('損壞圖片應拒絕處理');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
            $this->assertSame('圖片內容損壞或不完整，請重新選擇圖片', $exception->getMessage());
        }
    }

    /** 測試超過像素上限時拒絕處理。 */
    #[Test]
    public function it_should_reject_image_when_pixel_limit_is_exceeded(): void
    {
        config(['images.max_pixels' => 100]);
        $file = UploadedFile::fake()->image('photo.jpg', 20, 20);
        $content = file_get_contents($file->getRealPath());
        $this->expectException(HttpException::class);
        app(VipsImageProcessor::class)->generate($content);
    }
}
