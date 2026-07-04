<?php
/**
 * Club7 CDN - Logger
 * Structured logging with rotation and retention
 */

namespace Club7CDN;

class Logger
{
    private string $logFile;
    private string $level;
    private static array $instances = [];

    private const LEVELS = [
        'DEBUG' => 0,
        'INFO'  => 1,
        'WARN'  => 2,
        'ERROR' => 3,
        'FATAL' => 4,
    ];

    public function __construct(string $filename, string $level = 'INFO')
    {
        $config = Config::getInstance();
        $this->logFile = $config->logsPath() . $filename;
        $this->level   = strtoupper($level);
        $this->ensureDirectory();
    }

    public static function get(string $name = 'app', string $level = 'INFO'): self
    {
        $key = "{$name}_{$level}";
        if (!isset(self::$instances[$key])) {
            self::$instances[$key] = new self("{$name}.log", $level);
        }
        return self::$instances[$key];
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log('DEBUG', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('INFO', $message, $context);
    }

    public function warn(string $message, array $context = []): void
    {
        $this->log('WARN', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log('ERROR', $message, $context);
    }

    public function fatal(string $message, array $context = []): void
    {
        $this->log('FATAL', $message, $context);
    }

    public function upload(string $category, string $filename, int $size, bool $success, string $reason = ''): void
    {
        $entry = [
            'type'     => 'upload',
            'category' => $category,
            'filename' => $filename,
            'size'     => $size,
            'success'  => $success,
            'reason'   => $reason,
            'ip'       => clientIp(),
            'agent'    => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        ];
        $this->log('INFO', 'File upload attempt', $entry);
    }

    public function security(string $event, array $context = []): void
    {
        $this->log('WARN', "[SECURITY] {$event}", array_merge(
            ['ip' => clientIp(), 'uri' => $_SERVER['REQUEST_URI'] ?? '', 'host' => $_SERVER['HTTP_HOST'] ?? ''],
            $context
        ));
    }

    private function log(string $level, string $message, array $context = []): void
    {
        if (!$this->shouldLog($level)) {
            return;
        }

        $this->ensureDirectory();

        $entry = sprintf(
            "[%s] %s %s %s %s\n",
            date('Y-m-d H:i:s'),
            str_pad($level, 5),
            attrEncode($message),
            attrEncode(json_encode($context)),
            clientIp()
        );

        file_put_contents($this->logFile, $entry, FILE_APPEND | LOCK_EX);
        $this->maybeRotate();
    }

    private function shouldLog(string $level): bool
    {
        $current   = self::LEVELS[$level] ?? 0;
        $threshold = self::LEVELS[$this->level] ?? 1;
        return $current >= $threshold;
    }

    private function ensureDirectory(): void
    {
        $dir = dirname($this->logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    private function maybeRotate(): void
    {
        if (!file_exists($this->logFile)) {
            return;
        }
        if (filesize($this->logFile) > 10 * 1024 * 1024) {
            $timestamp = date('Y-m-d_H-i-s');
            rename($this->logFile, "{$this->logFile}.{$timestamp}");
            $this->pruneOld();
        }
    }

    private function pruneOld(): void
    {
        $config    = Config::getInstance();
        $retention = (int) $config->getValue('LOG_RETENTION_DAYS', 30);
        $maxAge    = time() - ($retention * 86400);
        $baseDir   = dirname($this->logFile);
        $escaped   = preg_quote($this->logFile, '#');

        foreach (glob("{$baseDir}/*") as $file) {
            if (!is_file($file)) continue;
            if (preg_match("#{$escaped}\.[^/]+$#", $file) && filemtime($file) < $maxAge) {
                @unlink($file);
            }
        }
    }
}
