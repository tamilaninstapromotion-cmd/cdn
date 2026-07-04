<?php
/**
 * Club7 CDN - Configuration System
 * Centralized, environment-driven configuration
 * All paths are dynamically resolved for portability
 */

namespace Club7CDN;

final class Config
{
    private static ?self $instance = null;
    private array $config = [];

    private function __construct()
    {
        $this->config = $this->loadEnvironment();
        $this->config = array_merge($this->config, $this->loadDefaults());
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function loadEnvironment(): array
    {
        $envFile = dirname(__DIR__) . '/.env';
        $values = [];

        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0) continue;
                $pos = strpos($line, '=');
                if ($pos === false) continue;
                $key = trim(substr($line, 0, $pos));
                $value = trim(substr($line, $pos + 1));
                if (!empty($key)) {
                    $values[$key] = $value;
                }
            }
        }

        return $values;
    }

    private function loadDefaults(): array
    {
        return [
            'APP_NAME'           => $this->get('APP_NAME', 'Club7 CDN'),
            'CDN_URL'            => $this->get('CDN_URL', 'https://cdn.tamilanpromotion.in'),
            'API_KEY'            => $this->get('API_KEY', ''),
            'MAX_STORAGE_GB'     => (int) $this->get('MAX_STORAGE_GB', 5),
            'MAX_IMAGE_SIZE'     => (int) $this->get('MAX_IMAGE_SIZE', 10485760),
            'MAX_DOCUMENT_SIZE'  => (int) $this->get('MAX_DOCUMENT_SIZE', 10485760),
            'RATE_LIMIT_PER_MIN' => (int) $this->get('RATE_LIMIT_PER_MINUTE', 60),
            'ENABLE_THUMBNAILS'  => strtolower($this->get('ENABLE_THUMBNAILS', 'true')) === 'true',
            'THUMBNAIL_SIZES'    => $this->get('THUMBNAIL_SIZES', '150,400,800'),
            'LOG_LEVEL'          => $this->get('LOG_LEVEL', 'INFO'),
            'LOG_RETENTION_DAYS' => (int) $this->get('LOG_RETENTION_DAYS', 30),
            'CLEANUP_INTERVAL'   => (int) $this->get('CLEANUP_INTERVAL_HOURS', 24),
        ];
    }

    private function get(string $key, mixed $default = ''): mixed
    {
        return $this->config[$key] ?? $default;
    }

    public function all(): array
    {
        return $this->config;
    }

    public function getValue(string $key, mixed $default = ''): mixed
    {
        return $this->config[$key] ?? $default;
    }

    public function rootPath(): string
    {
        return rtrim(dirname(__DIR__), '/\\') . '/';
    }

    public function uploadsPath(): string
    {
        return $this->rootPath() . 'uploads/';
    }

    public function thumbnailsPath(): string
    {
        return $this->rootPath() . 'thumbnails/';
    }

    public function logsPath(): string
    {
        return $this->rootPath() . 'logs/';
    }

    public function configPath(): string
    {
        return $this->rootPath() . 'config/';
    }

    public function tempPath(): string
    {
        return $this->rootPath() . 'temp/';
    }

    public function allowedImageTypes(): array
    {
        return ['jpg', 'jpeg', 'png', 'webp'];
    }

    public function allowedDocTypes(): array
    {
        return ['pdf'];
    }

    public function allowedTypes(): array
    {
        return array_merge($this->allowedImageTypes(), $this->allowedDocTypes());
    }

    public function allowedCategories(): array
    {
        return ['avatars', 'events', 'gallery', 'sponsors', 'documents', 'forms'];
    }

    public function blockedExtensions(): array
    {
        return ['php', 'php3', 'php4', 'php5', 'phtml', 'exe', 'dll',
            'sh', 'bat', 'pl', 'py', 'jsp', 'asp', 'aspx', 'cgi'];
    }

    public function getMaxSize(string $category): int
    {
        return strpos($category, 'document') !== false || $category === 'forms'
            ? (int) $this->getValue('MAX_DOCUMENT_SIZE', 10485760)
            : (int) $this->getValue('MAX_IMAGE_SIZE', 10485760);
    }

    public function cdnUrl(): string
    {
        return rtrim($this->getValue('CDN_URL', ''), '/');
    }

    public function apiKey(): string
    {
        return (string) $this->getValue('API_KEY', '');
    }

    public function thumbSizes(): array
    {
        if (!$this->getValue('ENABLE_THUMBNAILS', true)) {
            return [];
        }
        $sizes = explode(',', (string) $this->getValue('THUMBNAIL_SIZES', '150,400,800'));
        return array_map('intval', array_filter($sizes, fn($s) => is_numeric($s) && (int)$s > 0));
    }

    public function isApiKeySet(): bool
    {
        return strlen($this->apiKey()) >= 8;
    }

    public function maxStorageBytes(): int
    {
        return (int) $this->getValue('MAX_STORAGE_GB', 5) * 1024 * 1024 * 1024;
    }

    private function __clone() {}
    public function __wakeup() {}
}
