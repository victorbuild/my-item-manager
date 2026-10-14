<?php

namespace App\Services;

use Illuminate\Http\Response;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class VipsImageProcessor
{
    /**
     * 產生已校正方向、移除私人 metadata 的 WebP 預覽與縮圖，不修改原圖。
     *
     * @return array{preview: string, thumb: string}
     */
    public function generate(string $content): array
    {
        $dimensions = @getimagesizefromstring($content);
        $supportedTypes = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF, IMAGETYPE_BMP];
        if ($dimensions === false || ! in_array($dimensions[2], $supportedTypes, true)) {
            throw new HttpException(Response::HTTP_UNPROCESSABLE_ENTITY, '不支援或無法讀取的圖片');
        }
        if ((int) config('images.max_pixels', 60000000) < $dimensions[0] * $dimensions[1]) {
            throw new HttpException(Response::HTTP_UNPROCESSABLE_ENTITY, '圖片像素超過處理上限');
        }

        $directory = sys_get_temp_dir() . '/item-images-' . Str::uuid();
        if (! mkdir($directory, 0700)) {
            throw new RuntimeException('無法建立圖片處理暫存目錄');
        }
        $source = $directory . '/source';
        try {
            if (file_put_contents($source, $content) === false) {
                throw new RuntimeException('無法寫入圖片處理暫存檔');
            }
            $result = [];
            foreach (['preview', 'thumb'] as $variant) {
                $width = (int) config("images.{$variant}.width");
                $height = (int) config("images.{$variant}.height");
                $quality = (int) config("images.{$variant}.quality");
                if ($width < 1 || $height < 1 || $quality < 1 || $quality > 100) {
                    throw new RuntimeException('圖片處理設定無效');
                }
                $output = "{$directory}/{$variant}.webp";
                $process = new Process([
                    '/usr/bin/vipsthumbnail', $source . '[fail-on=error]',
                    '--size', "{$width}x{$height}>",
                    '--export-profile', 'srgb',
                    '--output', "{$output}[Q={$quality},keep=icc,profile=srgb]",
                    '--vips-concurrency', '1',
                    '--vips-cache-max-memory', '33554432',
                    '--vips-disc-threshold', '64m',
                ]);
                $process->setTimeout(30);
                try {
                    $process->mustRun();
                } catch (ProcessFailedException $exception) {
                    $decoderError = $process->getErrorOutput();
                    $invalidInputPattern = '/premature end|not enough (?:image )?data'
                        . '|corrupt (?:jpeg|png|webp|gif)|invalid (?:jpeg|png|webp|gif)'
                        . '|bad (?:huffman|jpeg)|libpng.*(?:crc error|read error)/i';
                    if (preg_match($invalidInputPattern, $decoderError) === 1) {
                        throw new HttpException(
                            Response::HTTP_UNPROCESSABLE_ENTITY,
                            '圖片內容損壞或不完整，請重新選擇圖片',
                            $exception
                        );
                    }
                    throw $exception;
                }
                $encoded = file_get_contents($output);
                if ($encoded === false || $encoded === '') {
                    throw new RuntimeException('無法讀取轉檔圖片');
                }
                $result[$variant] = $encoded;
            }

            return $result;
        } finally {
            foreach ([$source, "{$directory}/preview.webp", "{$directory}/thumb.webp"] as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
            rmdir($directory);
        }
    }
}
