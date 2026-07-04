# Club7 CDN — Production Readiness Checklist

## Pre-Deployment

- [ ] `.env` file created from `env.example`
- [ ] `API_KEY` set to a cryptographically secure value (min 32 hex characters)
- [ ] `CDN_URL` set to the correct production domain
- [ ] `MAX_STORAGE_GB` set to the intended storage budget
- [ ] `MAX_IMAGE_SIZE` and `MAX_DOCUMENT_SIZE` configured appropriately
- [ ] `RATE_LIMIT_PER_MINUTE` set based on expected traffic
- [ ] `ENABLE_THUMBNAILS` and `THUMBNAIL_SIZES` set as needed

---

## Directory & Permissions

- [ ] All `uploads/` subdirectories created (`avatars`, `events`, `gallery`, `sponsors`, `documents`, `forms`, `temp`)
- [ ] `thumbnails/` directory created and writable
- [ ] `logs/` directory created and writable
- [ ] `config/`, `docs/`, `temp/` directories present
- [ ] `.env` has `chmod 644` (readable by web server, not world-readable)
- [ ] `uploads/` has `chmod 755` (readable/executable, writable by web server)
- [ ] `logs/` has `chmod 755`

---

## Web Server

- [ ] Domain/subdomain points to the project folder as document root
- [ ] SSL certificate installed and active (HTTPS required)
- [ ] `mod_rewrite` enabled
- [ ] `.htaccess` is being processed (`Options -Indexes` blocks directory listing)
- [ ] `AllowOverride All` set for the directory in Apache config
- [ ] `upload_max_filesize` ≥ 10 MB in `php.ini`
- [ ] `post_max_size` ≥ 10 MB in `php.ini`
- [ ] PHP 8.0+ is the active version

---

## PHP Extensions

- [ ] GD extension installed (required for thumbnails)
- [ ] `fileinfo` extension installed (required for MIME detection)
- [ ] `allow_url_fopen` enabled (required for remote file checks)
- [ ] Verify: `php -m | grep -E "gd|fileinfo"`

---

## Security

- [ ] Directory listing is blocked (opening `uploads/` returns 403)
- [ ] `.env` is not accessible via HTTP (returns 403)
- [ ] `config/` is not accessible via HTTP (returns 403)
- [ ] `logs/` is not accessible via HTTP (returns 403)
- [ ] `src/` is not accessible via HTTP (returns 403)
- [ ] `docs/` is not accessible via HTTP (returns 403)
- [ ] `README.md` is not accessible via HTTP (returns 403)
- [ ] PHP files in `uploads/` are blocked (`.htaccess` deny rules)
- [ ] PHP files in `thumbnails/` are blocked
- [ ] PHP files in `temp/` are blocked
- [ ] API endpoints return 401 when API key is missing or invalid
- [ ] Blocked extensions (`php`, `exe`, `sh`, etc.) are rejected on upload
- [ ] Rate limiting returns 429 after threshold is exceeded

---

## Functionality

- [ ] Landing page loads at domain root (`https://cdn.tamilpromotion.in`)
- [ ] Landing page does not show directory listing or PHP errors
- [ ] Upload endpoint accepts valid images and documents
- [ ] Upload endpoint rejects files with wrong MIME type
- [ ] Upload endpoint rejects files with wrong magic bytes
- [ ] Upload endpoint rejects blocked extensions
- [ ] Upload endpoint returns 507 when storage is full
- [ ] Upload endpoint returns thumbnail URLs when enabled
- [ ] Delete endpoint removes files from disk
- [ ] Delete endpoint also removes associated thumbnails
- [ ] Delete endpoint returns 403 for protected paths
- [ ] `file-info.php` returns correct metadata for existing files
- [ ] `file-info.php` returns 404 for non-existent files
- [ ] `storage-stats.php` returns accurate usage data
- [ ] `cleanup.php` removes temp files older than 24 hours

---

## Logging

- [ ] `logs/uploads.log` records upload attempts
- [ ] `logs/security.log` records security violations
- [ ] `logs/access.log` records API access
- [ ] `logs/cleanup.log` records cleanup runs
- [ ] Rotated logs are created when files exceed 10 MB
- [ ] Old rotated logs are deleted after retention period

---

## API Key & Access Control

- [ ] API key is not stored in any file that could be accidentally committed
- [ ] API key is not logged anywhere
- [ ] Applications using the CDN have the correct API key configured
- [ ] API key rotation plan is documented

---

## Backup & Recovery

- [ ] Backup procedure documented
- [ ] `.env` is included in backups (or secrets are documented for recreation)
- [ ] Restoration procedure tested

---

## Monitoring

- [ ] Storage status can be checked via `storage-stats.php`
- [ ] Log files can be tailed for health monitoring
- [ ] Automated cleanup cron job is configured (recommended: daily at 3 AM)
- [ ] Alerting plan in place for `critical` storage status

---

## Portability Verification

- [ ] Project was tested in a different folder (not the intended deployment location)
- [ ] `php index.php` setup check passes in the target environment
- [ ] No hardcoded paths exist in source code
- [ ] CDN_URL was updated correctly after any test moves
