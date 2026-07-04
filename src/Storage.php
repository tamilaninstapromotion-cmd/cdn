<?php
/**
 * Club7 CDN - Storage Monitor
 * Tracks disk usage and enforces storage limits
 */

namespace Club7CDN;

class Storage
{
    private Config $config;

    public function __construct()
    {
        $this->config = Config::getInstance();
    }

    /**
     * Get total bytes used across all upload categories
     */
    public function getUsage(): int
    {
        return $this->scanDirectory($this->config->uploadsPath());
    }

    /**
     * Get storage usage in bytes per category
     */
    public function getUsageByCategory(): array
    {
        $usage = [];
        foreach ($this->config->allowedCategories() as $category) {
            $path  = $this->config->uploadsPath() . $category . '/';
            $usage[$category] = is_dir($path) ? $this->scanDirectory($path) : 0;
        }
        return $usage;
    }

    /**
     * Check if adding $additionalBytes would exceed the limit
     */
    public function canStore(int $additionalBytes = 0): bool
    {
        return ($this->getUsage() + $additionalBytes) <= $this->config->maxStorageBytes();
    }

    /**
     * Percentage used
     */
    public function getUsagePercent(): float
    {
        $max = $this->config->maxStorageBytes();
        if ($max <= 0) return 0.0;
        return round(($this->getUsage() / $max) * 100, 2);
    }

    /**
     * Remaining bytes
     */
    public function getRemaining(): int
    {
        return max(0, $this->config->maxStorageBytes() - $this->getUsage());
    }

    /**
     * Full storage stats response
     */
    public function getStats(): array
    {
        $totalBytes = $this->getUsage();
        $maxBytes    = $this->config->maxStorageBytes();

        return [
            'status'        => $this->getUsagePercent() >= 100 ? 'full' : ($this->getUsagePercent() >= 80 ? 'critical' : 'ok'),
            'total_bytes'   => $totalBytes,
            'total_gb'      => round($totalBytes / 1024 / 1024 / 1024, 3),
            'max_gb'        => (int) $this->config->getValue('MAX_STORAGE_GB', 5),
            'max_bytes'     => $maxBytes,
            'remaining_gb'  => round($this->getRemaining() / 1024 / 1024 / 1024, 3),
            'usage_percent' => $this->getUsagePercent(),
            'by_category'   => array_map(fn($bytes) => round($bytes / 1024 / 1024, 3), $this->getUsageByCategory()),
        ];
    }

    /**
     * Recursively scan a directory for file sizes
     */
    private function scanDirectory(string $dir): int
    {
        if (!is_dir($dir)) {
            return 0;
        }
        $size = 0;
        $dh   = @opendir($dir);
        if (!$dh) {
            return 0;
        }
        while (($file = readdir($dh)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $fullPath = $dir . $file;
            if (is_dir($fullPath)) {
                $size += $this->scanDirectory($fullPath . '/');
            } elseif (is_file($fullPath)) {
                $size += filesize($fullPath);
            }
        }
        closedir($dh);
        return $size;
    }
}
