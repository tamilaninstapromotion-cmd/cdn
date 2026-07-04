<?php
/**
 * Club7 CDN - Storage Stats Endpoint
 * GET /api/storage-stats.php
 *
 * Returns current storage usage statistics across all categories.
 * Public endpoint for monitoring CDN capacity.
 */

namespace Club7CDN;

require_once __DIR__ . '/../src/Config.php';
require_once __DIR__ . '/../src/Storage.php';
require_once __DIR__ . '/../src/Logger.php';

use Club7CDN\Config;
use Club7CDN\Storage;
use Club7CDN\Logger;

define('CLUB7_CDN_API', true);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Method not allowed. Use GET.']);
    exit;
}

$config = Config::getInstance();
$storage = new Storage();
$stats = $storage->getStats();
$usageByCategory = $storage->getUsageByCategory();

$response = [
    'status' => $stats['status'],
    'storage' => [
        'used' => [
            'bytes' => $stats['total_bytes'],
            'gb' => $stats['total_gb'],
            'percentage' => $stats['usage_percent'],
        ],
        'total' => [
            'bytes' => $stats['max_bytes'],
            'gb' => $stats['max_gb'],
        ],
        'remaining' => [
            'bytes' => $stats['total_bytes'] > 0 ? $stats['max_bytes'] - $stats['total_bytes'] : $stats['max_bytes'],
            'gb' => $stats['remaining_gb'],
        ],
    ],
    'by_category' => [],
    'limitations' => [
        'max_file_size' => [
            'images' => $config->getValue('MAX_IMAGE_SIZE'),
            'documents' => $config->getValue('MAX_DOCUMENT_SIZE'),
        ],
        'categories' => $config->allowedCategories(),
        'allowed_types' => $config->allowedTypes(),
    ],
];

foreach ($usageByCategory as $category => $bytes) {
    $response['by_category'][$category] = [
        'bytes' => $bytes,
        'mb' => round($bytes / 1024 / 1024, 2),
        'percentage' => $stats['max_bytes'] > 0 ? round(($bytes / $stats['max_bytes']) * 100, 2) : 0,
    ];
}

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'stats' => $response,
    'timestamp' => date('c'),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

$logger = Logger::get('access');
$logger->info('Storage stats accessed');
