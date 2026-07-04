<?php
/**
 * Club7 CDN - Rate Limiter
 * Prevents abuse with IP-based and API key-based rate limiting
 */

namespace Club7CDN;

class RateLimiter
{
    private array $ipHits = [];
    private array $apiKeyHits = [];
    private Config $config;

    public function __construct()
    {
        $this->config = Config::getInstance();
        $this->cleanupStaleEntries();
    }

    public function checkLimit(string $apiKey = ''): bool
    {
        $this->cleanupStaleEntries();
        $now = time();
        $maxPerMinute = $this->config->getValue('RATE_LIMIT_PER_MIN', 60);

        // Check IP-based limit
        $ipKey = $this->getIpKey();
        if (!isset($this->ipHits[$ipKey])) {
            $this->ipHits[$ipKey] = [];
        }
        $this->ipHits[$ipKey] = array_filter($this->ipHits[$ipKey], fn($t) => $t > $now - 60);
        $this->ipHits[$ipKey][] = $now;

        if (count($this->ipHits[$ipKey]) > $maxPerMinute) {
            $this->security('IP rate limit exceeded', ['ip' => clientIp()]);
            return false;
        }

        // Check API key-based limit (if provided)
        if ($apiKey) {
            if (!isset($this->apiKeyHits[$apiKey])) {
                $this->apiKeyHits[$apiKey] = [];
            }
            $this->apiKeyHits[$apiKey] = array_filter($this->apiKeyHits[$apiKey], fn($t) => $t > $now - 60);
            $this->apiKeyHits[$apiKey][] = $now;

            if (count($this->apiKeyHits[$apiKey]) > $maxPerMinute * 2) {
                $this->security("API key rate limit exceeded: {$apiKey}", ['ip' => clientIp()]);
                return false;
            }
        }

        return true;
    }

    private function getIpKey(): string
    {
        return clientIp();
    }

    private function cleanupStaleEntries(): void
    {
        $now = time();
        $threshold = $now - 120;

        // Clean IP entries older than 2 minutes
        foreach ($this->ipHits as $ip => $timestamps) {
            $this->ipHits[$ip] = array_filter($timestamps, fn($t) => $t > $threshold);
            if (empty($this->ipHits[$ip])) {
                unset($this->ipHits[$ip]);
            }
        }

        // Clean API key entries older than 2 minutes
        foreach ($this->apiKeyHits as $apiKey => $timestamps) {
            $this->apiKeyHits[$apiKey] = array_filter($timestamps, fn($t) => $t > $threshold);
            if (empty($this->apiKeyHits[$apiKey])) {
                unset($this->apiKeyHits[$apiKey]);
            }
        }
    }

    private function security(string $event, array $context = []): void
    {
        $logger = Logger::get('security');
        $logger->warn($event, $context);
    }
}
