<?php
/**
 * Club7 CDN - File Info Endpoint
 * GET /api/file-info.php?path=uploads/category/filename.ext
 *
 * Returns metadata about an uploaded file without exposing server internals.
 */

namespace Club7CDN;

require_once __DIR__ . '/../src/Config.php';
require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/Logger.php';

use Club7CDN\Config;

define('CLUB7_CDN_API', true);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Method not allowed. Use GET.']);
    exit;
}

$path = trim($_GET['path'] ?? '', '/');

if (empty($path)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Missing required query parameter: path']);
    exit;
}

$relativePath = $path;

if (str_contains($relativePath, '../') || str_contains($relativePath, '..\\')) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid path. Directory traversal not allowed.']);
    exit;
}

if (!str_starts_with($relativePath, 'uploads/')) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Path must start with "uploads/".']);
    exit;
}

$config = Config::getInstance();
$root = $config->rootPath();
$fullPath = $root . $relativePath;

if (!file_exists($fullPath) || !is_file($fullPath)) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'File not found.']);
    exit;
}

$extension = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));
$cdnUrl = $config->cdnUrl();

$info = [
    'filename' => pathinfo($relativePath, PATHINFO_FILENAME),
    'extension' => $extension,
    'relative_path' => $relativePath,
    'url' => $cdnUrl . '/' . $relativePath,
    'size' => filesize($fullPath),
    'size_human' => formatBytes(filesize($fullPath)),
    'mime_type' => getMimeType($extension),
    'is_image' => in_array($extension, $config->allowedImageTypes(), true),
    'modified' => date('c', filemtime($fullPath)),
    'created' => date('c', filectime($fullPath)),
];

if ($info['is_image']) {
    $category = explode('/', trim($relativePath, '/'))[1] ?? '';
    $base = pathinfo($relativePath, PATHINFO_FILENAME);
    $thumbDir = $root . 'thumbnails/' . $category . '/';

    $info['thumbnails'] = [];
    foreach ($config->thumbSizes() as $size) {
        $thumbFile = $thumbDir . "{$base}_{$size}w.{$extension}";
        if (file_exists($thumbFile)) {
            $thumbRelative = "thumbnails/{$category}/{$base}_{$size}w.{$extension}";
            $info['thumbnails'][$size] = $cdnUrl . '/' . $thumbRelative;
        }
    }

    if (function_exists('getimagesize')) {
        $dims = @getimagesize($fullPath);
        if ($dims) {
            $info['dimensions'] = ['width' => $dims[0], 'height' => $dims[1]];
        }
    }
}

header('Content-Type: application/json');
echo json_encode(['success' => true, 'file' => $info], JSON_UNESCAPED_SLASHES);

function formatBytes(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}
