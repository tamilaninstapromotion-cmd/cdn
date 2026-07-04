<?php
/**
 * Club7 CDN - Delete Endpoint
 * POST /api/delete.php
 *
 * Deletes a file from the CDN with authentication.
 * Requires either filename+category or full relative path.
 */

namespace Club7CDN;

require_once __DIR__ . '/../src/Config.php';
require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/Logger.php';

use Club7CDN\Config;

define('CLUB7_CDN_API', true);

// ─── Get auth key from request ──────────────────────────────────────────────────
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (str_starts_with($apiKey, 'Bearer ')) {
    $apiKey = substr($apiKey, 7);
}

// ─── Validate API key ─────────────────────────────────────────────────────────
$config = Config::getInstance();
$validKey = $config->apiKey();
$apiKeySet = $config->isApiKeySet();

if ($apiKeySet && !empty($validKey) && $validKey !== $apiKey) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized. Invalid API key.']);
    exit;
}

// ─── Parse request ───────────────────────────────────────────────────────────
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$data = [];

if (str_contains($contentType, 'application/json')) {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true) ?: [];
} else {
    // Parse form data (multipart/form-urlencoded)
    parse_str(file_get_contents('php://input'), $data);
}

// ─── Validate required fields ─────────────────────────────────────────────────
if (empty($data['relative_path'])) {
    if (empty($data['category']) || empty($data['filename'])) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'Either "relative_path" OR both "category" and "filename" are required'
        ]);
        exit;
    }
}

// ─── Build file path ─────────────────────────────────────────────────────────
$root = $config->rootPath();

if (!empty($data['relative_path'])) {
    $relativePath = trim($data['relative_path'], '/');
    $fullPath = $root . $relativePath;
} else {
    $category = preg_replace('/[^a-z0-9_-]/', '', strtolower($data['category']));
    $filename = sanitizeFilename($data['filename']);
    $fullPath = $root . 'uploads/' . $category . '/' . $filename;
    $relativePath = 'uploads/' . $category . '/' . $filename;
}

// ─── Security checks ─────────────────────────────────────────────────────────
// Prevent path traversal
if (str_contains($relativePath, '../') || str_contains($relativePath, '..\\')) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid path detected']);
    exit;
}

// Prevent access to sensitive files
$protected = ['.env', 'config/', 'logs/', 'src/', 'api/', 'index.php'];
foreach ($protected as $p) {
    if (str_starts_with($relativePath, $p)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Access denied to this file']);
        exit;
    }
}

// ─── Check if file exists ─────────────────────────────────────────────────────
if (!file_exists($fullPath) || !is_file($fullPath)) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'File not found']);
    exit;
}

// ─── Delete the file ──────────────────────────────────────────────────────────
if (!unlink($fullPath)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Failed to delete file']);
    exit;
}

// ─── Also delete any thumbnails ───────────────────────────────────────────────
$ext = pathinfo($relativePath, PATHINFO_EXTENSION);
$base = pathinfo($relativePath, PATHINFO_FILENAME);
$categoryDir = pathinfo($relativePath, PATHINFO_DIRNAME);

$thumbDir = $root . 'thumbnails/' . trim($categoryDir, 'uploads/') . '/';

if (is_dir($thumbDir)) {
    $thumbPatterns = [
        $base . '_150w.' . $ext,
        $base . '_400w.' . $ext,
        $base . '_800w.' . $ext,
    ];

    foreach ($thumbPatterns as $thumb) {
        $thumbPath = $thumbDir . $thumb;
        if (file_exists($thumbPath)) {
            @unlink($thumbPath);
        }
    }
}

// ─── Log the deletion ───────────────────────────────────────────────────────
$logger = Logger::get('uploads');
$logger->info('File deleted', [
    'relative_path' => $relativePath,
    'category' => pathinfo($relativePath, PATHINFO_DIRNAME) ?: 'unknown',
    'filename' => pathinfo($relativePath, PATHINFO_FILENAME),
]);

// ─── Success response ─────────────────────────────────────────────────────────
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'message' => 'File deleted successfully',
    'deleted_file' => $relativePath,
]);
