# Club7 CDN — Maintenance Guide

## Log Rotation

Logs are automatically rotated when they exceed 10 MB.

| Log file | Contents | Retention |
|----------|----------|-----------|
| `logs/uploads.log` | Upload attempts, success/failure | 30 days (configurable) |
| `logs/access.log` | API access, errors | 30 days |
| `logs/errors.log` | Application errors and warnings | 30 days |
| `logs/security.log` | Security violations and abuse attempts | 30 days |
| `logs/cleanup.log` | Cleanup task results | 30 days |

### Manual Log Rotation

```bash
# Rotate a log file manually
mv logs/uploads.log "logs/uploads.log.$(date +%Y-%m-%d_%H-%M-%S)"
```

### Adjusting Retention

Set `LOG_RETENTION_DAYS=30` in `.env`. Rotated logs older than this are deleted automatically.

---

## Temp File Cleanup

Temp files in `uploads/temp/` older than 24 hours are deleted on every cleanup run.

### Manual Cleanup

```bash
curl -X POST https://cdn.tamilpromotion.in/api/cleanup.php \
 -H "X-Api-Key: sk_cdn_your_api_key"
```

### Automated Cleanup (Cron)

Add to your crontab for automatic daily cleanup:

```bash
# Run cleanup every day at 3 AM
0 3 * * * curl -s -X POST https://cdn.tamilpromotion.in/api/cleanup.php -H "X-Api-Key: sk_cdn_your_api_key" >> /dev/null 2>&1
```

Or run via PHP CLI:

```bash
# Requires PHP CLI on the server
php -r "
\$_SERVER['REQUEST_METHOD'] = 'POST';
\$_SERVER['HTTP_X_API_KEY'] = 'sk_cdn_your_api_key';
chdir('/path/to/cdn.tamilpromotion.in');
require 'api/cleanup.php';
"
```

---

## Storage Monitoring

### Check Storage Usage

```bash
curl https://cdn.tamilpromotion.in/api/storage-stats.php
```

### Storage Status Levels

| Status | Usage | Action |
|--------|-------|--------|
| `ok` | 0–79% | No action needed |
| `critical` | 80–99% | Plan cleanup or storage expansion |
| `full` | 100% | Uploads blocked — immediate cleanup required |

---

## Disk Usage by Category

```php
<?php
require_once 'src/Storage.php';
$storage = new Club7CDN\Storage();
$stats = $storage->getStats();

foreach ($stats['by_category'] as $category => $mb) {
 echo "$category: {$mb} MB\n";
}
?>
```

---

## Thumbnail Cache

Thumbnails are generated once per upload and stored in `thumbnails/{category}/`. They are not automatically regenerated unless the original image is re-uploaded.

To clear all thumbnails (rarely needed):

```bash
rm -rf thumbnails/*
```

Original images are never affected.

---

## Changing Storage Limits

Edit `.env`:

```env
MAX_STORAGE_GB=10 # increase to 10 GB
```

The system validates storage availability before each upload. Reducing the limit below current usage will not delete existing files — it will simply block new uploads until space is freed.

---

## API Key Rotation

1. Generate a new key: `openssl rand -hex 32`
2. Update `.env`: `API_KEY="sk_cdn_new_key_here"`
3. Update all applications using the old key
4. Remove the old key from all applications

---

## Monitoring Health

### Quick Health Check

```bash
# Returns storage stats and CDN configuration
curl https://cdn.tamilpromotion.in/api/storage-stats.php | jq '.stats.status'
```

### Recent Security Events

```bash
tail -n 50 logs/security.log
```

### Recent Upload Activity

```bash
tail -n 50 logs/uploads.log
```

---

## Backing Up

Because this is a disk-based system, backup is straightforward:

```bash
# Backup entire CDN
tar -czf cdn-backup-$(date +%Y%m%d).tar.gz \
 --exclude='logs/*.log*' \
 /path/to/cdn.tamilpromotion.in/
```

> **Note:** Exclude rotated logs from backup to reduce size. The `.env` file contains sensitive data — include it and secure the backup.

### Restoring

```bash
# Extract to new location
tar -xzf cdn-backup-20260115.tar.gz -C /path/to/new-location/
# Update CDN_URL in .env if domain changed
```

---

## Performance Tips

| Area | Recommendation |
|------|---------------|
| Thumbnails | Thumbnails are cached on disk — first request generates, subsequent requests serve from cache |
| Large uploads | Increase `upload_max_filesize` and `post_max_size` in `php.ini` |
| Many small files | Consider adding an `.htaccess` rule to enable `mod_expires` caching |
| High traffic | Consider placing a CDN (Cloudflare, Fastly) in front of this self-hosted CDN |
