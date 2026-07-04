# Club7 CDN — Deployment Guide

## Prerequisites

- PHP 8.0 or higher
- Apache with `mod_rewrite` enabled (for `.htaccess` rules)
- GD extension (for thumbnail generation)
- `allow_url_fopen` enabled
- `upload_max_filesize` and `post_max_size` ≥ 10 MB in `php.ini`

---

## Step 1 — Download / Clone

```bash
# Clone or copy the project to any directory, for example:
/home/user/cdn.tamilanpromotion.in/
```

Because all paths are relative to `__DIR__`, the project works in any location — no recompilation needed.

---

## Step 2 — Generate API Key

Generate a cryptographically secure key:

```bash
openssl rand -hex 32
```

Or use PHP:

```php
php -r "echo bin2hex(random_bytes(32));"
```

---

## Step 3 — Configure `.env`

```bash
cp env.example .env
nano .env  # or vim, code, etc.
```

Minimum required settings:

```env
APP_NAME="Club7 CDN"
CDN_URL="https://cdn.tamilanpromotion.in"
API_KEY="sk_cdn_your_generated_key_here"
MAX_STORAGE_GB=5
```

---

## Step 4 — Create Required Directories

```bash
php index.php
```

This runs the built-in setup check which also creates missing directories automatically.

Or create manually:

```bash
mkdir -p uploads/{avatars,events,gallery,sponsors,documents,forms,temp}
mkdir -p thumbnails logs config docs temp
chmod 755 uploads thumbnails logs config docs temp
chmod 755 uploads/*/ # ensure category dirs are writable
```

---

## Step 5 — Permissions

```bash
chmod 644 .env # lock the .env file
chmod 644 .htaccess # keep htaccess readable
chmod 755 uploads/ # allow PHP to write files
chmod 755 uploads/*/
chmod 755 thumbnails/
chmod 755 logs/
chmod 755 temp/
chmod 755 config/
```

> **Important:** The `uploads/` directory and all subdirectories must be writable by the web server (typically `www-data` or `apache` user).

---

## Step 6 — Configure Domain / Subdomain

### Option A — Subdomain (recommended for cPanel)

1. In cPanel → **Subdomains**, create: `cdn.tamilanpromotion.in`
2. Point document root to the project folder: `/home/user/cdn.tamilanpromotion.in/`
3. Enable SSL via **AutoSSL** or upload your certificate

### Option B — Addon Domain

1. In cPanel → **Addon Domains**, add `cdn.tamilanpromotion.in`
2. Point document root to the project folder

### Option C — Custom Path (no domain change)

Simply copy the project folder and point your web server to it. No code changes required.

---

## Step 7 — Apache Configuration

If not using cPanel, add to your Apache vhost or `.htaccess`:

```apache
<Directory /path/to/cdn>
 AllowOverride All
 Require all granted
</Directory>
```

Ensure `mod_rewrite` is enabled:

```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

---

## Step 8 — Verify Deployment

```bash
# Check setup
php index.php

# Test storage stats (no auth needed)
curl https://cdn.tamilpromotion.in/api/storage-stats.php

# Test upload (requires API key)
curl -X POST https://cdn.tamilpromotion.in/api/upload.php \
 -H "X-Api-Key: sk_cdn_your_generated_key_here" \
 -F "file=@/tmp/test.png" \
 -F "category=events"
```

---

## Production Checklist

- [ ] `.env` exists and contains a strong API key
- [ ] `.env` has `chmod 644` (not world-readable)
- [ ] SSL certificate is active on the domain
- [ ] `uploads/`, `thumbnails/`, `logs/`, `temp/` directories exist and are writable
- [ ] PHP version is 8.0+
- [ ] `mod_rewrite` is enabled
- [ ] `upload_max_filesize` and `post_max_size` ≥ 10 MB
- [ ] GD extension is installed (`php -m | grep gd`)
- [ ] `.htaccess` is being read (`Options -Indexes` must block directory listing)
- [ ] Storage stats return `ok` status

---

## Moving / Migrating

Because no absolute paths are hardcoded:

1. Copy the entire project folder to the new location
2. Update `CDN_URL` in `.env` to reflect the new domain
3. Point the web server document root to the new location
4. Done — no other changes needed

---

## Troubleshooting

| Symptom | Fix |
|---------|-----|
| 403 on uploaded files | Check `.htaccess` is present and `AllowOverride All` is set |
| 500 on upload | Check `uploads/` is writable by the web server user |
| Thumbnails not generated | Verify GD extension is installed |
| Storage stats returns 0 | Ensure directories were created inside the project folder |
| Rate limit immediately | Check `RATE_LIMIT_PER_MINUTE` in `.env` |
