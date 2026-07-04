<?php
/**
 * Club7 CDN - Cleanup Endpoint
 * POST /api/cleanup.php
 *
 * Cleans up old temp files and orphaned thumbnails.
 * Removes files older than 24 hours from the temp directory.
 */

namespace Club7CDN;

require_once __DIR__ . '/../src/Config.php';
require_once __DIR__ . '/../src/Logger.php';

use Club7CDN\Config;
use Club7CDN\Logger;

define('CLUB7_CDN_API', true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
 http_response_code(405);
 header('Content-Type: application/json');
 echo json_encode(['error' => 'Method not allowed. Use POST.']);
 exit;
}

// Require API key for cleanup operations
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
if (str_starts_with($apiKey, 'Bearer ')) {
 $apiKey = substr($apiKey, 7);
}

$config = Config::getInstance();
$validKey = $config->apiKey();

if ($config->isApiKeySet() && $validKey !== $apiKey) {
 http_response_code(401);
 header('Content-Type: application/json');
 echo json_encode(['error' => 'Unauthorized. Valid API key required.']);
 exit;
}

$logger = Logger::get('cleanup');
$results = [
 'temp_files' => 0,
 'temp_bytes' => 0,
 'temp_errors' => 0,
 'old_logs' => 0,
 'old_log_bytes' => 0,
];

// Clean temp directory
$tempPath = $config->tempPath();
$tempAge = 24 * 60 * 60; // 24 hours
$now = time();

if (is_dir($tempPath)) {
 $dh = @opendir($tempPath);
 if ($dh) {
 while (($file = readdir($dh)) !== false) {
 if ($file === '.' || $file === '..') continue;
 $fullPath = $tempPath . $file;
 if (!is_file($fullPath)) continue;
 if (($now - filemtime($fullPath)) > $tempAge) {
 $size = filesize($fullPath);
 if (@unlink($fullPath)) {
 $results['temp_files']++;
 $results['temp_bytes'] += $size;
 } else {
 $results['temp_errors']++;
 }
 }
 }
 closedir($dh);
 }
}

// Clean old log files
$logsPath = $config->logsPath();
$retention = (int) $config->getValue('LOG_RETENTION_DAYS', 30) * 86400;

if (is_dir($logsPath)) {
 $lh = @opendir($logsPath);
 if ($lh) {
 while (($file = readdir($lh)) !== false) {
 if ($file === '.' || $file === '..') continue;
 if (!is_file($logsPath . $file)) continue;
 // Only clean rotated .log.timestamp files
 if (preg_match('/\.log\.\d{4}-\d{2}-\d{2}/', $file)) {
 if (($now - filemtime($logsPath . $file)) > $retention) {
 $size = filesize($logsPath . $file);
 if (@unlink($logsPath . $file)) {
 $results['old_logs']++;
 $results['old_log_bytes'] += $size;
 }
 }
 }
 }
 closedir($lh);
 }
}

// Log cleanup
$logger->info('Cleanup completed', $results);

// Response
header('Content-Type: application/json');
echo json_encode([
 'success' => true,
 'message' => 'Cleanup completed successfully.',
 'cleaned' => [
 'temp_files' => $results['temp_files'],
 'temp_bytes_freed' => $results['temp_bytes'],
 'temp_errors' => $results['temp_errors'],
 'old_log_files' => $results['old_logs'],
 'old_log_bytes_freed' => $results['old_log_bytes'],
 ],
 'timestamp' => date('c'),
], JSON_UNESCAPED_SLASHES);
