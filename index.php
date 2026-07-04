<?php
/**
 * Club7 CDN - Entry Point
 * - CLI: runs setup check and diagnostics
 * - Web: serves the landing page (index.html)
 */

// Detect if running in CLI or web
$isCli = (php_sapi_name() === 'cli');

if (!$isCli) {
	// Web request - serve the landing page
	$htmlFile = __DIR__ . '/index.html';
	if (file_exists($htmlFile)) {
		header('Content-Type: text/html; charset=utf-8');
		readfile($htmlFile);
		exit;
	}
	// Fallback if index.html is missing
	http_response_code(404);
	echo '<!DOCTYPE html><html><head><title>404</title></head><body><h1>404 - Landing page not found</h1></body></html>';
	exit;
}

// === CLI MODE ===
require_once __DIR__ . '/src/Config.php';
require_once __DIR__ . '/src/helpers.php';

use Club7CDN\Config;

header('Content-Type: text/plain; charset=utf-8');

function checkDir($path, $name, $required = true): bool
{
	if (!is_dir($path)) {
		if ($required) {
			echo "❌ {$name} directory not found: {$path}\n";
			return false;
		}
		echo "⚠️ {$name} directory not found: {$path} (not required)\n";
		return false;
	}
	echo "✅ {$name} directory exists: {$path}\n";
	return true;
}

echo "=== Club7 CDN - Initial Setup Check ===\n\n";

// Check root directory
$config = Config::getInstance();
echo "Root directory: " . $config->rootPath() . "\n\n";

// Check required directories
$dirs = [
	'uploads' => true,
	'thumbnails' => true,
	'logs' => true,
	'avatars' => false,
	'events' => false,
	'gallery' => false,
	'sponsors' => false,
	'documents' => false,
	'forms' => false,
	'temp' => false,
];

echo "Checking directories:\n";
$allGood = true;
foreach ($dirs as $dir => $required) {
	$fullPath = $config->uploadsPath() . $dir . '/';
	if (!checkDir($fullPath, "uploads/{$dir}", $required)) {
		if ($required) $allGood = false;
		if (!is_dir($fullPath) && $required) {
			if (mkdir($fullPath, 0755, true)) {
				echo "  → Created directory: {$fullPath}\n";
			}
		}
	}
}

// Check environment file
$envFile = __DIR__ . '/.env';
if (!file_exists($envFile)) {
	echo "❌ Environment file not found: {$envFile}\n";
	if (copy(__DIR__ . '/env.example', $envFile)) {
		echo "  → Created from env.example\n";
	}
} else {
	echo "✅ Environment file exists: {$envFile}\n";
}

// Check .htaccess
if (!file_exists(__DIR__ . '/.htaccess')) {
	echo "❌ .htaccess file not found\n";
} else {
	echo "✅ .htaccess file exists\n";
}

// Check storage capacity
$storage = new Club7CDN\Storage();
$stats = $storage->getStats();
echo "\nStorage Status:\n";
echo "  Usage: {$stats['usage_percent']}%\n";
echo "  Total: {$stats['total_gb']} GB / {$stats['max_gb']} GB\n";
echo "  Status: {$stats['status']}\n";

// Check thumbnail sizes
$sizes = $config->thumbSizes();
if (!empty($sizes)) {
	echo "\nThumbnail sizes configured: " . implode(', ', $sizes) . "\n";
}

echo "\n=== API Endpoints ===\n";
echo "POST /api/upload.php  - Upload file\n";
echo "POST /api/delete.php  - Delete file\n";
echo "GET  /api/file-info.php  - Get file info\n";
echo "GET  /api/storage-stats.php - Storage statistics\n";
echo "POST /api/cleanup.php  - Clean temp files\n";

if ($allGood) {
	echo "\n✅ System is ready!\n";
} else {
	echo "\n❌ Some issues found. Check output above.\n";
}
