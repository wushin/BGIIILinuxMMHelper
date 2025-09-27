<?php
namespace App\Services;

/**
 * Image transformations (DDS → PNG for browser display).
 */
class ImageTransformer
{
    /**
     * Convert a DDS file to PNG and return a data URI.
     * Returns null if Imagick or DDS delegate is unavailable.
     */
    public function ddsToPngDataUri(string $absPath): ?string
    {
        try {
            if (!class_exists('\Imagick')) {
                return null;
            }
            $img = new \Imagick();
            $img->readImage($absPath);   // requires a DDS delegate (e.g., FreeImage)
            $img->setImageFormat('png');
            $blob = $img->getImageBlob();
            if (!$blob) {
                return null;
            }
            return 'data:image/png;base64,' . base64_encode($blob);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
?>
