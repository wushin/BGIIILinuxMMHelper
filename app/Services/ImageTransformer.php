<?php
namespace App\Services;

/**
 * Image transformations (DDS → PNG for browser display).
 */
class ImageTransformer
{
    public function ddsToPngDataUri(string $absPath): ?string
    {
        try {
            if (!class_exists('\Imagick')) return null;
            $img = new \Imagick();
            $img->readImage($absPath);   // requires Imagick DDS delegate
            $img->setImageFormat('png');
            $blob = $img->getImageBlob();
            if (!$blob) return null;
            return 'data:image/png;base64,' . base64_encode($blob);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
?>
