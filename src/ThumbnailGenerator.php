<?php
/**
 * Club7 CDN - Thumbnail Generator
 * Creates cached resized versions of uploaded images
 */

namespace Club7CDN;

class ThumbnailGenerator
{
    private Config $config;
    private array $errors = [];

    public function __construct()
    {
        $this->config = Config::getInstance();
    }

    public function generate(string $sourcePath, string $category): array
    {
        if (!$this->config->getValue('ENABLE_THUMBNAILS', true)) {
            return [];
        }

        $this->errors = [];
        $sizes = $this->config->thumbSizes();
        if (empty($sizes)) {
            return [];
        }

        $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
        if (!in_array($extension, $this->config->allowedImageTypes(), true)) {
            return [];
        }

        $categoryDir = $this->config->thumbnailsPath() . $category . '/';
        if (!is_dir($categoryDir) && !mkdir($categoryDir, 0755, true)) {
            $this->errors[] = "Cannot create thumbnail directory: {$categoryDir}";
            return [];
        }

        $basename  = pathinfo($sourcePath, PATHINFO_FILENAME);
        $generated = [];

        foreach ($sizes as $size) {
            $thumbPath = $categoryDir . "{$basename}_{$size}w.{$extension}";
            if ($this->createThumbnail($sourcePath, $thumbPath, $size, $extension)) {
                $generated[$size] = $thumbPath;
            } else {
                $this->errors[] = "Failed to create {$size}w thumbnail for {$basename}";
            }
        }

        return $generated;
    }

    private function createThumbnail(string $source, string $destination, int $targetSize, string $extension): bool
    {
        try {
            $srcImage = $this->loadImage($source, $extension);
            if ($srcImage === false) {
                return false;
            }

            $origWidth  = imagesx($srcImage);
            $origHeight = imagesy($srcImage);

            $ratio   = min($targetSize / $origWidth, $targetSize / $origHeight);
            $newW    = max(1, (int) round($origWidth * $ratio));
            $newH    = max(1, (int) round($origHeight * $ratio));

            $thumb = imagecreatetruecolor($newW, $newH);
            if ($thumb === false) {
                imagedestroy($srcImage);
                return false;
            }

            imagecopyresampled($thumb, $srcImage, 0, 0, 0, 0, $newW, $newH, $origWidth, $origHeight);

            $saved = $this->saveImage($thumb, $destination, $extension);
            imagedestroy($srcImage);
            imagedestroy($thumb);

            return $saved;
        } catch (\Throwable $e) {
            $this->errors[] = "Thumbnail error: {$e->getMessage()}";
            return false;
        }
    }

    private function loadImage(string $path, string $extension)
    {
        return match ($extension) {
            'jpg', 'jpeg' => @imagecreatefromjpeg($path),
            'png'         => @imagecreatefrompng($path),
            'webp'        => @imagecreatefromwebp($path),
            default       => false,
        };
    }

    private function saveImage($image, string $path, string $extension): bool
    {
        return match ($extension) {
            'jpg', 'jpeg' => @imagejpeg($image, $path, 85),
            'png'         => @imagepng($image, $path, 8),
            'webp'        => @imagewebp($image, $path, 85),
            default       => false,
        };
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function cleanup(string $sourcePath, string $category): void
    {
        $basename = pathinfo($sourcePath, PATHINFO_FILENAME);
        $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
        $thumbDir = $this->config->thumbnailsPath() . $category . '/';

        foreach ($this->config->thumbSizes() as $size) {
            $thumbFile = $thumbDir . "{$basename}_{$size}w.{$extension}";
            if (file_exists($thumbFile)) {
                @unlink($thumbFile);
            }
        }
    }
}
