<?php

namespace Tests\Unit;

use App\Support\WebpImage;
use GdImage;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Upload re-encoding (A6), entirely in memory with GD: no files, no application, no database.
 */
class WebpImageTest extends TestCase
{
    private function canvas(int $width, int $height): GdImage
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 30, 120, 200));

        return $image;
    }

    private function bytes(GdImage $image, string $format): string
    {
        ob_start();
        match ($format) {
            'jpeg' => imagejpeg($image),
            'png' => imagepng($image),
            'webp' => imagewebp($image),
            'gif' => imagegif($image),
        };

        return (string) ob_get_clean();
    }

    private function assertWebp(string $bytes): void
    {
        $this->assertSame('RIFF', substr($bytes, 0, 4));
        $this->assertSame('WEBP', substr($bytes, 8, 4));
    }

    public function test_jpeg_png_and_webp_are_re_encoded_to_webp_with_their_size(): void
    {
        foreach (['jpeg', 'png', 'webp'] as $format) {
            $result = WebpImage::encode($this->bytes($this->canvas(320, 200), $format));

            $this->assertWebp($result['bytes']);
            $this->assertSame([320, 200], [$result['width'], $result['height']], $format);
            $this->assertSame(IMAGETYPE_WEBP, getimagesizefromstring($result['bytes'])[2]);
        }
    }

    public function test_transparency_is_kept(): void
    {
        $image = imagecreatetruecolor(10, 10);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));

        $decoded = imagecreatefromstring(WebpImage::encode($this->bytes($image, 'png'))['bytes']);
        $this->assertSame(127, (imagecolorat($decoded, 5, 5) >> 24) & 0x7F);
    }

    public function test_large_images_are_scaled_down_to_the_output_limit(): void
    {
        $result = WebpImage::encode($this->bytes($this->canvas(3000, 300), 'png'));

        $this->assertSame(WebpImage::OUTPUT_MAX_SIDE, $result['width']);
        $this->assertSame(256, $result['height']);
    }

    public function test_files_that_are_not_jpeg_png_or_webp_are_refused(): void
    {
        $refused = [
            'gif' => $this->bytes($this->canvas(10, 10), 'gif'),
            'php' => "<?php echo 'hi';",
            'svg' => '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
            'truncated png' => substr($this->bytes($this->canvas(50, 50), 'png'), 0, 40),
            'empty' => '',
        ];

        foreach ($refused as $label => $bytes) {
            try {
                WebpImage::encode($bytes);
                $this->fail("{$label} should be refused");
            } catch (RuntimeException $exception) {
                $this->assertNotSame('', $exception->getMessage(), $label);
            }
        }
    }

    public function test_oversized_files_and_dimensions_are_refused_before_decoding(): void
    {
        $this->expectExceptionMessage('5 MB');
        WebpImage::encode(str_repeat('a', WebpImage::MAX_BYTES + 1));
    }

    public function test_too_many_pixels_are_refused(): void
    {
        $this->expectExceptionMessage('dimensions');
        WebpImage::encode($this->bytes($this->canvas(WebpImage::MAX_SIDE + 1, 2), 'png'));
    }
}
