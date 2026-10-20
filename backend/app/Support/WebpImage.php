<?php

namespace App\Support;

use RuntimeException;

/**
 * Decodes an uploaded JPEG / PNG / WebP with PHP's bundled GD extension and re-encodes it
 * as WebP (A6). Re-encoding drops metadata and anything that is not pixel data. The real
 * type is read from the file content (getimagesize), never from the name or the browser.
 *
 * Not handled: EXIF orientation (the exif extension is not enabled), animated images
 * (only the first frame is kept).
 */
final class WebpImage
{
    public const MAX_BYTES = 5 * 1024 * 1024;

    /** Refuse decoding anything larger (protects memory against "decompression bombs"). */
    public const MAX_SIDE = 8000;

    public const MAX_PIXELS = 40_000_000;

    /** Stored images are scaled down to this longest side. */
    public const OUTPUT_MAX_SIDE = 2560;

    public const QUALITY = 82;

    /**
     * @return array{bytes: string, width: int, height: int}
     *
     * @throws RuntimeException with a user-facing English message
     */
    public static function encode(string $bytes, int $maxSide = self::OUTPUT_MAX_SIDE): array
    {
        if (! function_exists('imagewebp') || ! function_exists('imagecreatefromwebp')) {
            throw new RuntimeException('Image processing is not available on this server.');
        }

        if ($bytes === '' || strlen($bytes) > self::MAX_BYTES) {
            throw new RuntimeException('The image may not be larger than 5 MB.');
        }

        $info = @getimagesizefromstring($bytes);
        $type = is_array($info) ? $info[2] : null;
        if (! in_array($type, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            throw new RuntimeException('The file is not a valid JPG, PNG or WebP image.');
        }

        [$width, $height] = $info;
        if ($width < 1 || $height < 1 || $width > self::MAX_SIDE || $height > self::MAX_SIDE || $width * $height > self::MAX_PIXELS) {
            throw new RuntimeException('The image dimensions are too large (at most 8000 × 8000 pixels).');
        }

        // The type was checked above; GD decodes the same bytes.
        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            throw new RuntimeException('The file is not a valid JPG, PNG or WebP image.');
        }

        if (! imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }

        $longest = max($width, $height);
        if ($longest > $maxSide) {
            $ratio = $maxSide / $longest;
            $scaled = imagescale($image, max(1, (int) round($width * $ratio)), max(1, (int) round($height * $ratio)), IMG_BICUBIC);
            if ($scaled === false) {
                throw new RuntimeException('The image could not be processed.');
            }
            $image = $scaled;
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);

        ob_start();
        $written = imagewebp($image, null, self::QUALITY);
        $bytes = (string) ob_get_clean();

        if (! $written || $bytes === '') {
            throw new RuntimeException('The image could not be processed.');
        }

        return ['bytes' => $bytes, 'width' => imagesx($image), 'height' => imagesy($image)];
    }
}
