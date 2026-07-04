<?php
/**
 * Club7 CDN - API Router
 * All API requests route through this entry point
 * Handles CORS, authentication, and routing
 */

namespace Club7CDN;

require_once __DIR__ . '/../src/Config.php';
require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/Logger.php';
require_once __DIR__ . '/../src/RateLimiter.php';

use Club7CDN\Config;
use Club7CDN\Logger;
use Club7CDN\RateLimiter;

// This file IS the API entry point — allow direct access

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Api-Key');
header('Access-Control-Max-Age: 86400');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
 http_response_code(204);
 exit;
}

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Rate limiting
$limiter = new RateLimiter();
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (str_starts_with($apiKey, 'Bearer ')) {
 $apiKey = substr($apiKey, 7);
}

if (!$limiter->checkLimit($apiKey)) {
 $log = Logger::get('access');
 $log->warn('Rate limit exceeded', ['ip' => clientIp()]);
 http_response_code(429);
 header('Retry-After: 60');
 header('Content-Type: application/json');
 echo json_encode(['error' => 'Rate limit exceeded. Please try again later.']);
 exit;
}

// API key authentication
$config = Config::getInstance();
$validKey = $config->apiKey();
$apiKeySet = $config->isApiKeySet();

if ($apiKeySet && !empty($validKey) && $validKey !== $apiKey) {
 $log = Logger::get('access');
 $log->security('Invalid API key attempt', ['ip' => clientIp()]);
 http_response_code(401);
 header('Content-Type: application/json');
 echo json_encode(['error' => 'Unauthorized. Invalid API key.']);
 exit;
}

// Route the request
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$path = str_replace(dirname($scriptName), '', $requestUri);
$path = trim(preg_replace('/\?.*$/', '', $path), '/');

$routes = [
 'api/upload' => __DIR__ . '/upload.php',
 'api/delete' => __DIR__ . '/delete.php',
 'api/file-info' => __DIR__ . '/file-info.php',
 'api/storage-stats' => __DIR__ . '/storage-stats.php',
 'api/cleanup' => __DIR__ . '/cleanup.php',
];

if (isset($routes[$path]) && file_exists($routes[$path])) {
 require_once $routes[$path];
 exit;
}

// Catch-all: unknown endpoint
$log = Logger::get('access');
$log->warn('Unknown API endpoint', ['path' => $path, 'ip' => clientIp()]);

http_response_code(404);
header('Content-Type: application/json');
echo json_encode([
 'error' => 'Endpoint not found',
 'message' => 'Available endpoints: upload, delete, file-info, storage-stats, cleanup',
 'version' => '1.0.0',
]);
