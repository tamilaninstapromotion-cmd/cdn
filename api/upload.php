<?php
/**
 * Club7 CDN - Upload Endpoint
 * POST /api/upload.php
 *
 * Uploads a file to the CDN with full validation and security checks.
 * Stores relative paths (uploads/category/filename.ext) — never absolute URLs.
 */

namespace Club7CDN;

require_once __DIR__ . '/../src/Config.php';
require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/Logger.php';
require_once __DIR__ . '/../src/RateLimiter.php';
require_once __DIR__ . '/../src/ThumbnailGenerator.php';
require_once __DIR__ . '/../src/Storage.php';

use Club7CDN\Config;
use Club7CDN\Logger;
use Club7CDN\ThumbnailGenerator;
use Club7CDN\Storage;

define('CLUB7_CDN_API', true);

// ─── Helpers (defined inline for standalone use) ──────────────────────────────

function uploadErrorResponse(string $message, int $code = 400, array $extra = []): void
{
 http_response_code($code);
 header('Content-Type: application/json; charset=utf-8');
 echo json_encode(array_merge(['error' => $message], $extra), JSON_UNESCAPED_SLASHES);
 exit;
}

function uploadJsonResponse(array $data, int $code = 200): void
{
 http_response_code($code);
 header('Content-Type: application/json; charset=utf-8');
 echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
 exit;
}

// ─── Config & Logger ───────────────────────────────────────────────────────────
$config = Config::getInstance();
$logger = Logger::get('uploads');
$storage = new Storage();

// ─── Validate request method ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
 uploadErrorResponse('Method not allowed. Use POST.', 405);
}

// ─── Validate category ────────────────────────────────────────────────────────
$category = trim($_POST['category'] ?? $_REQUEST['category'] ?? '');
$category = preg_replace('/[^a-z0-9_-]/', '', strtolower($category));

if (empty($category) || !in_array($category, $config->allowedCategories(), true)) {
 $logger->security('Invalid upload category', ['category' => $category]);
 uploadErrorResponse(
 'Invalid category. Allowed: ' . implode(', ', $config->allowedCategories()),
 400
 );
}

// ─── Validate file presence ────────────────────────────────────────────────────
if (empty($_FILES['file']) || $_FILES['file']['error'] === UPLOAD_ERR_NO_FILE) {
 uploadErrorResponse('No file provided. Send file as multipart/form-data with field name "file".', 400);
}

$file = $_FILES['file'];

// ─── Check for upload errors ───────────────────────────────────────────────────
$phpUploadErrors = [
 UPLOAD_ERR_INI_SIZE => 'File exceeds server upload limit.',
 UPLOAD_ERR_FORM_SIZE => 'File exceeds form upload limit.',
 UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
 UPLOAD_ERR_NO_TMP_DIR => 'Server misconfiguration: missing temp directory.',
 UPLOAD_ERR_CANT_WRITE => 'Server failed to write file to disk.',
 UPLOAD_ERR_EXTENSION  => 'Upload blocked by PHP extension.',
];

if ($file['error'] !== UPLOAD_ERR_OK) {
 $reason = $phpUploadErrors[$file['error']] ?? 'Unknown upload error.';
 $logger->upload($category, $file['name'], $file['size'], false, $reason);
 uploadErrorResponse($reason, 400);
}

// ─── Validate file size ────────────────────────────────────────────────────────
$maxSize = $config->getMaxSize($category);
if ($file['size'] > $maxSize) {
 $logger->upload($category, $file['name'], $file['size'], false, 'File too large');
 uploadErrorResponse(
 sprintf(
 'File size (%s) exceeds maximum allowed (%s).',
 number_format($file['size']),
 number_format($maxSize)
 ),
 413
 );
}

// ─── Extract and validate extension ───────────────────────────────────────────
$originalName = $file['name'];
$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

if (empty($extension)) {
 $logger->upload($category, $originalName, $file['size'], false, 'Missing extension');
 uploadErrorResponse('File must have an extension.', 400);
}

// ─── Block dangerous extensions ───────────────────────────────────────────────
if (in_array($extension, $config->blockedExtensions(), true)) {
  $logger->security('Blocked extension upload attempt', [
 'category' => $category,
 'filename' => $originalName,
 'extension' => $extension,
 ]);
 uploadErrorResponse('This file type is not allowed.', 415);
}

// ─── Allow only known types ────────────────────────────────────────────────────
if (!in_array($extension, $config->allowedTypes(), true)) {
 $logger->upload($category, $originalName, $file['size'], false, 'Disallowed type');
 uploadErrorResponse(
 'File type not allowed. Allowed: ' . implode(', ', $config->allowedTypes()),
 415
 );
}

// ─── MIME type validation ─────────────────────────────────────────────────────
$finfo = new \finfo(FILEINFO_MIME_TYPE);
$detectedMime = $finfo->file($file['tmp_name']);

$allowedMimes = [
 'jpg' => ['image/jpeg'],
 'jpeg' => ['image/jpeg'],
 'png' => ['image/png'],
 'webp' => ['image/webp'],
 'pdf' => ['application/pdf'],
];

if (!isset($allowedMimes[$extension]) || !in_array($detectedMime, $allowedMimes[$extension], true)) {
 $logger->security('MIME type mismatch', [
 'category' => $category,
 'filename' => $originalName,
 'detected' => $detectedMime,
  'expected' => $allowedMimes[$extension] ?? [],
 ]);
 uploadErrorResponse('File type does not match its extension. Upload rejected.', 415);
}

// ─── File signature / magic bytes validation ──────────────────────────────────
if (!validateFileSignature($file['tmp_name'], $extension)) {
 $logger->security('File signature validation failed', [
 'category' => $category,
 'filename' => $originalName,
 'extension' => $extension,
 ]);
 uploadErrorResponse('File content does not match declared type. Upload rejected.', 415);
}

// ─── Storage limit check ──────────────────────────────────────────────────────
if (!$storage->canStore($file['size'])) {
 $stats = $storage->getStats();
 $logger->warn('Storage limit reached, upload blocked', [
 'category' => $category,
 'size' => $file['size'],
 'total_usage' => $stats['total_bytes'],
 'max' => $stats['max_bytes'],
 ]);
 uploadErrorResponse(
 'Storage limit reached. Cannot accept new uploads. Current usage: '
 . $stats['usage_percent'] . '%',
 507
 );
}

// ─── Destination directory ─────────────────────────────────────────────────────
$destDir = $config->uploadsPath() . $category . '/';
if (!is_dir($destDir) && !mkdir($destDir, 0755, true)) {
 $logger->error('Cannot create upload directory', ['path' => $destDir]);
 uploadErrorResponse('Server configuration error.', 500);
}

// ─── Generate random filename ─────────────────────────────────────────────────
$newFilename  = generateRandomFilename($extension);
$destPath = $destDir . $newFilename;
$relativePath = "uploads/{$category}/{$newFilename}";

// ─── Move uploaded file ───────────────────────────────────────────────────────
if (!move_uploaded_file($file['tmp_name'], $destPath)) {
 $logger->error('move_uploaded_file failed', [
 'tmp' => $file['tmp_name'],
 'dest' => $destPath,
 ]);
 uploadErrorResponse('Failed to save file. Please try again.', 500);
}

// ─── Set safe permissions ─────────────────────────────────────────────────────
chmod($destPath, 0644);

// ─── Generate thumbnails (if image) ─────────────────────────────────────────
$thumbnails = [];
if (in_array($extension, $config->allowedImageTypes(), true)) {
 $thumbGen = new ThumbnailGenerator();
 $thumbnails = $thumbGen->generate($destPath, $category);
 if (!empty($thumbGen->getErrors())) {
 $logger->warn('Thumbnail generation had errors', ['errors' => $thumbGen->getErrors()]);
 }
}

// ─── Build response ───────────────────────────────────────────────────────────
$cdnUrl = $config->cdnUrl();
$thumbUrls = [];
foreach ($thumbnails as $size => $thumbPath) {
 $thumbRelative = str_replace($config->rootPath(), '', $thumbPath);
 $thumbUrls[$size] = $cdnUrl . '/' . $thumbRelative;
}

$response = [
 'success' => true,
 'message' => 'File uploaded successfully.',
 'file' => [
 'original_name' => $originalName,
 'filename' => $newFilename,
 'relative_path' => $relativePath,
 'url' => $cdnUrl . '/' . $relativePath,
 'size' => $file['size'],
 'size_human' => number_format($file['size']) . ' bytes',
 'mime_type' => $detectedMime,
 'extension' => $extension,
 'category' => $category,
 'uploaded_at' => date('c'),
 ],
];

if (!empty($thumbUrls)) {
 $response['thumbnails'] = $thumbUrls;
}

$logger->upload($category, $newFilename, $file['size'], true);
uploadJsonResponse($response, 201);
