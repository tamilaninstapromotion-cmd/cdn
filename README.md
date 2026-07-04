# Club7 CDN

**A production-grade standalone CDN and file storage service for Club7 and future projects.**

---

## Overview

Club7 CDN is a lightweight, security-first file storage and delivery platform built in vanilla PHP. It stores images (JPG, PNG, WebP) and documents (PDF) on disk, validates every upload through five independent security checks, serves files directly via URL, and automatically generates cached thumbnails.

- **No database required** — everything is stored on disk
- **Zero dependencies** — uses only PHP built-in extensions
- **Fully portable** — works in any directory or on any domain without code changes
- **Portable** — move to a different folder, server, or domain and it just works
- **5 GB storage quota** — configurable, enforced at the storage layer
- **Automatic thumbnails** — generates 150px, 400px, and 800px variants for images

---

## Features

| Feature | Details |
|---------|---------|
| File validation | Extension, MIME, and magic bytes checks |
| Security | API key, rate limiting, blocked types, path traversal protection |
| Thumbnails | 150, 400, 800 px via GD |
| Storage quota | Configurable GB limit with per-upload enforcement |
| Logging | Upload, access, error, cleanup with rotation and retention |
| Categories | avatars, events, gallery, sponsors, documents, forms |
| Direct serving | Files accessible via direct URL |
| Configurable | All settings via `.env` |

---

## File Structure

```
club7-cdn/
├── .env # Configuration (not committed)
├── .htaccess # Apache rules & security headers
├── index.html # Landing page (cdn.tamilanpromotion.in)
├── index.php # CLI setup & diagnostics
├── env.example # Template for .env
│
├── src/ # Core library (never web-accessible)
│ ├── Config.php # Centralized, environment-driven config
│ ├── helpers.php # Security & utility functions
│ ├── Logger.php # Structured logging with rotation
│ ├── RateLimiter.php # IP & key-based rate limiting
│ ├── Storage.php # Disk usage tracking & quota enforcement
│ └── ThumbnailGenerator.php # Resized image variants via GD
│
├── api/ # API endpoints (web-accessible)
│ ├── index.php # API router & auth middleware
│ ├── upload.php # File upload (POST)
│ ├── delete.php # File deletion (POST)
│ ├── file-info.php # File metadata (GET)
│ ├── storage-stats.php # Storage statistics (GET)
│ └── cleanup.php # Temp & log cleanup (POST)
│
├── uploads/ # Stored files — public by design
│ ├── avatars/
│ ├── events/
│ ├── gallery/
│ ├── sponsors/
│ ├── documents/
│ ├── forms/
│ └── temp/ # Temporary upload staging
│
├── thumbnails/ # Cached resized images
│ ├── avatars/
│ ├── events/
│ └── ...
│
├── logs/ # Application logs
│ ├── uploads.log
│ ├── errors.log
│ ├── access.log
│ └── cleanup.log
│
├── docs/ # Documentation
│ ├── cdn-api.md # API reference
│ ├── security.md # Security architecture
│ ├── deployment.md # Deployment guide
│ ├── maintenance.md # Maintenance procedures
│ └── checklist.md # Production readiness checklist
│
├── config/ # Reserved for future use
└── temp/ # Reserved for future use
```

---

## Quick Start

1. **Configure** — copy `env.example` to `.env`, set `API_KEY` and `CDN_URL`
2. **Run setup** — `php index.php` to check directories and storage
3. **Deploy** — point your web server document root to this folder

That's it. No Composer, no database, no build step.

---

## Upload from Your Application

```php
<?php
$ch = curl_init('https://cdn.tamilanpromotion.in/api/upload.php');
curl_setopt_array($ch, [
 CURLOPT_POST => true,
 CURLOPT_HTTPHEADER => [
 'X-Api-Key: sk_cdn_your_api_key',
 ],
 CURLOPT_POSTFIELDS => [
 'file' => new CurlFile('/path/to/banner.jpg'),
 'category' => 'events',
 ],
 CURLOPT_RETURNTRANSFER => true,
]);
$response = json_decode(curl_exec($ch), true);
curl_close($ch);

// Save the relative path in your database
$relativePath = $response['file']['relative_path'];
// → "uploads/events/a1b2c3d4e5f678901234567890123456.webp"
```

> **Store relative paths, never absolute URLs.** Use `$cdnUrl . '/' . $relativePath` to display.

---

## Configuration

All settings are in `.env`. Key variables:

| Variable | Default | Description |
|----------|---------|-------------|
| `APP_NAME` | `Club7 CDN` | Application name |
| `CDN_URL` | `https://cdn.tamilanpromotion.in` | Base URL for file links |
| `API_KEY` | *(required)* | Shared secret for API authentication |
| `MAX_STORAGE_GB` | `5` | Storage quota in gigabytes |
| `MAX_IMAGE_SIZE` | `10485760` (10 MB) | Max file size for images |
| `MAX_DOCUMENT_SIZE` | `10485760` (10 MB) | Max file size for documents |
| `RATE_LIMIT_PER_MINUTE` | `60` | Max requests per minute per IP |
| `ENABLE_THUMBNAILS` | `true` | Enable/disable thumbnail generation |
| `THUMBNAIL_SIZES` | `150,400,800` | Thumbnail sizes to generate |
| `LOG_LEVEL` | `INFO` | Minimum log level (DEBUG, INFO, WARN, ERROR, FATAL) |
| `LOG_RETENTION_DAYS` | `30` | Days before rotated logs are deleted |
| `CLEANUP_INTERVAL_HOURS` | `24` | Temp file cleanup interval |

---

## Portability

Because all paths are resolved dynamically:

```php
dirname(__DIR__) // project root — works anywhere
$config->uploadsPath() // uploads/ — relative to root
$config->rootPath() // absolute project root
```

No absolute filesystem paths are hardcoded. Move the project to any folder, any server, or any domain — it works immediately.

---

## Allowed File Types

**Images:** `jpg`, `jpeg`, `png`, `webp`
**Documents:** `pdf`

## Blocked File Types

`php`, `php3`, `php4`, `php5`, `phtml`, `exe`, `dll`, `sh`, `bat`, `pl`, `py`, `jsp`, `asp`, `aspx`, `cgi`

---

## Allowed Upload Categories

`avatars` `events` `gallery` `sponsors` `documents` `forms`

---

## Public URL Format

```
https://cdn.tamilanpromotion.in/uploads/{category}/{filename}
```

Examples:

- `https://cdn.tamilanpromotion.in/uploads/events/banner.webp`
- `https://cdn.tamilanpromotion.in/uploads/gallery/photo1.webp`
- `https://cdn.tamilanpromotion.in/uploads/documents/guide.pdf`

---

## License

Private — Club7 Platform. Not for redistribution.
