# Club7 CDN — API Documentation

**Version:** 1.0.0 | **Base URL:** `https://cdn.tamilanpromotion.in/api/`

---

## Authentication

All endpoints require an API key passed via header:

```
X-Api-Key: sk_cdn_your_secure_api_key_here
```

Or as a Bearer token:

```
Authorization: Bearer sk_cdn_your_secure_api_key_here
```

> **Important:** Never expose your API key in client-side code. Always call these endpoints from your server-side backend.

---

## Endpoints

### `POST /api/upload.php`

Upload a file to the CDN.

**Request:** `multipart/form-data`

| Field | Type | Required | Description |
|---------|--------|----------|--------------------------------------|
| `file` | File | Yes | The file to upload  |
| `category` | string | Yes | One of: `avatars`, `events`, `gallery`, `sponsors`, `documents`, `forms` |

**Example (cURL):**
```bash
curl -X POST https://cdn.tamilanpromotion.in/api/upload.php \
 -H "X-Api-Key: sk_cdn_your_api_key" \
 -F "file=@/path/to/image.jpg" \
 -F "category=events"
```

**Success Response (201):**
```json
{
 "success": true,
 "message": "File uploaded successfully.",
 "file": {
 "original_name": "banner.jpg",
 "filename": "a1b2c3d4e5f678901234567890123456.webp",
 "relative_path": "uploads/events/a1b2c3d4e5f678901234567890123456.webp",
 "url": "https://cdn.tamilanpromotion.in/uploads/events/a1b2c3d4e5f678901234567890123456.webp",
 "size": 204857,
 "size_human": "204,857 bytes",
 "mime_type": "image/jpeg",
 "extension": "webp",
 "category": "events",
 "uploaded_at": "2026-01-15T10:30:00+05:30"
 },
 "thumbnails": {
 "150": "https://cdn.tamilanpromotion.in/thumbnails/events/a1b2..._150w.webp",
 "400": "https://cdn.tamilanpromotion.in/thumbnails/events/a1b2..._400w.webp",
 "800": "https://cdn.tamilanpromotion.in/thumbnails/events/a1b2..._800w.webp"
 }
}
```

**Error Responses:**

| Status | `error` field |
|--------|--------------|
| `400` | No file provided / invalid category / file too large |
| `401` | Invalid or missing API key |
| `413` | File exceeds size limit |
| `415` | Unsupported file type / MIME mismatch |
| `429` | Rate limit exceeded |
| `507` | Storage limit reached |

---

### `POST /api/delete.php`

Delete a file from the CDN.

**Request:** `application/json` or `application/x-www-form-urlencoded`

| Field | Type | Required | Description |
|----------------|--------|----------|------------------------------------------|
| `relative_path` | string | Yes* | Full relative path (e.g. `uploads/events/abc123.webp`) |
| `category` | string | Yes* | Upload category |
| `filename` | string | Yes* | File name to delete |

*\*Either `relative_path` alone, or both `category` + `filename`.*

**Example (cURL):**
```bash
curl -X POST https://cdn.tamilanpromotion.in/api/delete.php \
 -H "X-Api-Key: sk_cdn_your_api_key" \
 -H "Content-Type: application/json" \
 -d '{"relative_path": "uploads/events/a1b2c3d4e5f678901234567890123456.webp"}'
```

**Success Response (200):**
```json
{
 "success": true,
 "message": "File deleted successfully",
 "deleted_file": "uploads/events/a1b2c3d4e5f678901234567890123456.webp"
}
```

**Error Responses:**

| Status | Condition |
|--------|----------------------------------|
| `400` | Missing fields / path traversal attempt |
| `401` | Invalid API key |
| `403` | Attempting to delete protected file |
| `404`  | File not found |
| `500` | Deletion failed |

---

### `GET /api/file-info.php`

Get metadata about an uploaded file.

**Request:** `GET` with query parameter

| Parameter | Type | Required | Description |
|-----------|--------|----------|------------------------------------------|
| `path` | string | Yes | Relative path (e.g. `uploads/events/abc.webp`) |

**Example (cURL):**
```bash
curl "https://cdn.tamilanpromotion.in/api/file-info.php?path=uploads/events/a1b2c3.webp" \
 -H "X-Api-Key: sk_cdn_your_api_key"
```

**Success Response (200):**
```json
{
 "success": true,
 "file": {
 "filename": "a1b2c3d4e5f678901234567890123456",
 "extension": "webp",
 "relative_path": "uploads/events/a1b2c3d4e5f678901234567890123456.webp",
 "url": "https://cdn.tamilanpromotion.in/uploads/events/a1b2c3d4e5f678901234567890123456.webp",
 "size": 204857,
 "size_human": "200.06 KB",
 "mime_type": "image/webp",
 "is_image": true,
 "dimensions": { "width": 1920, "height": 1080 },
 "modified": "2026-01-15T10:30:00+05:30",
 "created": "2026-01-15T10:30:00+05:30",
 "thumbnails": {
 "150": "https://cdn.tamilanpromotion.in/thumbnails/events/a1b2..._150w.webp",
 "400": "https://cdn.tamila...thumbnails/events/a1b2..._400w.webp",
 "800": "https://cdn.tamila...thumbnails/events/a1b2..._800w.webp"
 }
 }
}
```

---

### `GET /api/storage-stats.php`

Get current storage usage statistics.

**Request:** `GET` — no authentication required for monitoring

**Example (cURL):**
```bash
curl "https://cdn.tamilanpromotion.in/api/storage-stats.php"
```

**Success Response (200):**
```json
{
 "success": true,
 "stats": {
 "status": "ok",
 "storage": {
 "used": { "bytes": 1073741824, "gb": 1.0, "percentage": 20.0 },
 "total": { "bytes": 5368709120, "gb": 5 },
 "remaining": { "bytes": 4294967296, "gb": 4.0 }
 },
 "by_category": {
 "avatars": { "bytes": 5242880, "mb": 5.0, "percentage": 0.1 },
 "events": { "bytes": 524288000, "mb": 500.0, "percentage": 9.77 },
 "gallery": { "bytes": 524288000, "mb": 500.0, "percentage": 9.77 },
 "sponsors": { "bytes": 0, "mb": 0.0, "percentage": 0.0 },
 "documents": { "bytes": 10485760, "mb": 10.0, "percentage": 0.2 },
 "forms": { "bytes": 0, "mb": 0.0, "percentage": 0.0 }
 },
 "limitations": {
 "max_file_size": { "images": 10485760, "documents": 10485760 },
 "categories": ["avatars", "events", "gallery", "sponsors", "documents", "forms"],
 "allowed_types": ["jpg", "jpeg", "png", "webp", "pdf"]
 }
 },
 "timestamp": "2026-01-15T10:30:00+05:30"
}
```

**Status values:** `ok` (0–79%), `critical` (80–99%), `full` (100%)

---

### `POST /api/cleanup.php`

Clean up old temp files and expired log archives.

**Request:** `POST` — requires API key

**Example (cURL):**
```bash
curl -X POST https://cdn.tamilanpromotion.in/api/cleanup.php \
 -H "X-Api-Key: sk_cdn_your_api_key"
```

**Success Response (200):**
```json
{
 "success": true,
 "message": "Cleanup completed successfully.",
 "cleaned": {
 "temp_files": 3,
 "temp_bytes_freed": 15728640,
 "temp_errors": 0,
 "old_log_files": 2,
 "old_log_bytes_freed": 5242880
 },
 "timestamp": "2026-01-15T10:30:00+05:30"
}
```

---

## Response Format

All responses use JSON with `Content-Type: application/json`.

**Success envelope:**
```json
{ "success": true, "data": ... }
```

**Error envelope:**
```json
{ "error": "Human-readable message", "details": { ... } }
```

---

## File URL Strategy

> **Always store relative paths in your database, never absolute URLs.**

The CDN URL in `.env` (`CDN_URL`) can change (e.g., switching domains or CDN providers). By storing only relative paths:

```
uploads/events/banner.webp
```

...and constructing the full URL at runtime:

```php
$url = $cdnUrl . '/' . $relativePath;
```

...your application survives domain migrations with zero database changes.
