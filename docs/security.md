# Club7 CDN — Security Architecture

## Layer 1: File Type Filtering

Before any file reaches disk, it is filtered through four independent checks:

| Check | What it catches | Rejection code |
|-------|----------------|----------------|
| Extension allowlist | Unknown extensions | 415 |
| Extension blocklist | Dangerous types (.php, .exe, .sh, …) | 415 |
| MIME sniffing (finfo) | Mismatched content | 415 |
| Magic bytes verification | Fake file headers | 415 |

---

## Layer 2: File Storage

- **Random filenames** — `random_bytes(32)` generates a cryptographically random hex string per file. Original names are discarded. Attackers cannot predict file paths.
- **Directory isolation** — each category lives in its own subdirectory: `uploads/events/`, `uploads/gallery/`, etc.
- **Safe permissions** — `0644` (read-only for web; not executable).
- **No script execution** — `.htaccess` disables PHP engine in uploads, thumbnails, and temp directories.
- **No path traversal** — all paths are resolved with `realpath()` and verified to remain inside the project root.

---

## Layer 3: Authentication

Every mutating endpoint (`upload`, `delete`, `cleanup`) requires a valid API key.

**Header options:**
```
X-Api-Key: sk_cdn_xxx
Authorization: Bearer sk_cdn_xxx
```

The API key:
- Must be at least 8 characters
- Is stored only in `.env`
- Is never logged or echoed in responses
- Failed attempts are logged with IP and user-agent

---

## Layer 4: Rate Limiting

IP-based sliding-window rate limiter.

| Scope | Limit | Window |
|-------|-------|--------|
| Per IP | 60 req/min (configurable) | 60 s sliding |
| Per API key | 120 req/min | 60 s sliding |

Violations return HTTP `429 Retry-After: 60`.

---

## Layer 5: Storage Quotas

| Check | Limit | Behavior |
|-------|-------|---------|
| Max storage | 5 GB (configurable) | Blocks new uploads, returns `507` |
| Per-file: images | 10 MB (configurable) | Returns `413` |
| Per-file: documents | 10 MB (configurable) | Returns `413` |

The system scans all upload directories recursively before accepting any file.

---

## Layer 6: Request Hardening

- **Blocked HTTP methods:** TRACE, TRACK, PUT, DELETE (via `.htaccess`)
- **Query-string traversal blocked:** `../` and `%00` (null byte) in query strings return 403.
- **CORS restricted to GET/POST/OPTIONS** only.
- **Security headers on every response:**

| Header | Value |
|--------|-------|
| `X-Content-Type-Options` | `nosniff` |
| `X-Frame-Options` | `DENY` |
| `X-XSS-Protection` | `1; mode=block` |
| `Referrer-Policy` | `strict-origin-when-cross-origin` |
| `Permissions-Policy` | `geolocation=(), microphone=(), camera=()` |

---

## Layer 7: Directory & File Protection

The following are **never accessible via HTTP** (returns 403):

```
.env
config/*
logs/*
docs/*
README.md
src/
api/index.php
```

Uploaded files are the only publicly accessible content.

---

## Layer 8: Logging & Auditing

All security-relevant events are logged to `logs/security.log`:

- Invalid API key attempts
- Blocked extension uploads
- MIME mismatches
- File signature failures
- Rate limit violations
- Storage limit reached
- File deletion attempts

---

## Blocked File Types

```
php php3 php4 php5 phtml exe dll sh bat pl
py jsp asp aspx cgi
```

These are blocked regardless of file extension or MIME type. The upload handler rejects them before `move_uploaded_file` is ever called.
