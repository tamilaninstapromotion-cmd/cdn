<?php
/**
 * Club7 CDN - Admin Panel
 * No database — password stored in config.json
 */

session_start();

$ADMIN_DIR = __DIR__;

// Auto-create config if missing
$configFile = $ADMIN_DIR . '/config.json';
if (!file_exists($configFile)) {
    $defaultConfig = [
        'password' => password_hash('admin123', PASSWORD_DEFAULT),
        'password_hint' => 'Default: admin123 (change after login!)',
        'created_at' => date('c'),
    ];
    file_put_contents($configFile, json_encode($defaultConfig, JSON_PRETTY_PRINT));
    @chmod($configFile, 0600);
}

$config = json_decode(file_get_contents($configFile), true);

// Handle login
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $pw = (string) ($_POST['password'] ?? '');
    if (password_verify($pw, $config['password'])) {
        $_SESSION['club7_admin'] = true;
        $_SESSION['club7_admin_time'] = time();
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
        exit;
    } else {
        $error = 'Invalid password.';
    }
}

// Handle logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    if (!isset($_SESSION['club7_admin']) || !$_SESSION['club7_admin']) {
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
        exit;
    }
    $current = (string) ($_POST['current_password'] ?? '');
    $new = (string) ($_POST['new_password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');

    if (!password_verify($current, $config['password'])) {
        $error = 'Current password is incorrect.';
    } elseif (strlen($new) < 4) {
        $error = 'New password must be at least 4 characters.';
    } elseif ($new !== $confirm) {
        $error = 'New passwords do not match.';
    } else {
        $config['password'] = password_hash($new, PASSWORD_DEFAULT);
        $config['password_updated_at'] = date('c');
        file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));
        @chmod($configFile, 0600);
        $message = 'Password changed successfully!';
    }
}

// Handle delete
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['cat'], $_GET['file'])) {
    if (!isset($_SESSION['club7_admin']) || !$_SESSION['club7_admin']) {
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
        exit;
    }
    $cat = preg_replace('/[^a-z0-9_-]/', '', strtolower($_GET['cat']));
    $file = preg_replace('/[^a-zA-Z0-9._-]/', '', $_GET['file']);
    $root = dirname(__DIR__);
    $allowed = ['avatars', 'events', 'gallery', 'sponsors', 'documents', 'forms'];
    if (in_array($cat, $allowed, true) && $file) {
        $path = $root . '/uploads/' . $cat . '/' . $file;
        if (file_exists($path) && is_file($path)) {
            @unlink($path);
            // Also delete thumbnails
            $thumbDir = $root . '/thumbnails/' . $cat . '/';
            $base = pathinfo($file, PATHINFO_FILENAME);
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            foreach ([150, 400, 800] as $size) {
                $tf = $thumbDir . "{$base}_{$size}w.{$ext}";
                if (file_exists($tf))
                    @unlink($tf);
            }
        }
    }
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?msg=File deleted successfully&type=success' . (isset($_GET['tab']) ? '&tab=' . urlencode($_GET['tab']) : ''));
    exit;
}

// Handle upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload') {
    if (!isset($_SESSION['club7_admin']) || !$_SESSION['club7_admin']) {
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
        exit;
    }
    $cat = preg_replace('/[^a-z0-9_-]/', '', strtolower($_POST['category'] ?? ''));
    $allowed = ['avatars', 'events', 'gallery', 'sponsors', 'documents', 'forms'];
    if (!in_array($cat, $allowed, true) || empty($_FILES['file'])) {
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?msg=Invalid upload&type=error');
        exit;
    }
    $f = $_FILES['file'];
    if ($f['error'] !== UPLOAD_ERR_OK) {
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?msg=Upload error: code ' . $f['error'] . '&type=error');
        exit;
    }
    if ($f['size'] > 10 * 1024 * 1024) {
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?msg=File exceeds 10 MB limit&type=error');
        exit;
    }
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
    if (!in_array($ext, $allowedExts, true)) {
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?msg=File type not allowed&type=error');
        exit;
    }
    $newName = bin2hex(random_bytes(16)) . '.' . $ext;
    $destDir = dirname(__DIR__) . '/uploads/' . $cat . '/';
    if (!is_dir($destDir))
        @mkdir($destDir, 0755, true);
    $destPath = $destDir . $newName;
    if (move_uploaded_file($f['tmp_name'], $destPath)) {
        @chmod($destPath, 0644);
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?msg=File uploaded successfully!&type=success');
    } else {
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?msg=Failed to save file&type=error');
    }
    exit;
}

// Check authentication
$isLoggedIn = isset($_SESSION['club7_admin']) && $_SESSION['club7_admin'];
if (!$isLoggedIn) {
    $timeout = (int) ($config['session_timeout'] ?? 3600);
    if (isset($_SESSION['club7_admin_time']) && (time() - $_SESSION['club7_admin_time']) > $timeout) {
        session_destroy();
        $isLoggedIn = false;
    }
}

// Session timeout check
if (!$isLoggedIn) {
    ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Club7 CDN — Admin Login</title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                background: #0a0a12;
                color: #e0e0e0;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 2rem;
            }

            .login-card {
                background: #111120;
                border: 1px solid #2a2a45;
                border-radius: 16px;
                padding: 2.5rem;
                width: 100%;
                max-width: 400px;
                box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6);
            }

            .login-card h1 {
                font-size: 1.5rem;
                font-weight: 700;
                background: linear-gradient(90deg, #6366f1, #a78bfa);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                background-clip: text;
                margin-bottom: 0.5rem;
            }

            .login-card .subtitle {
                color: #666688;
                font-size: 0.875rem;
                margin-bottom: 2rem;
            }

            .form-group {
                margin-bottom: 1.25rem;
            }

            .form-group label {
                display: block;
                font-size: 0.8rem;
                font-weight: 600;
                color: #888899;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                margin-bottom: 0.5rem;
            }

            .form-group input {
                width: 100%;
                padding: 0.75rem 1rem;
                background: #0a0a15;
                border: 1px solid #2a2a45;
                border-radius: 8px;
                color: #e0e0e0;
                font-size: 1rem;
                outline: none;
                transition: border-color 0.2s;
            }

            .form-group input:focus {
                border-color: #6366f1;
            }

            .btn {
                width: 100%;
                padding: 0.75rem 1rem;
                border: none;
                border-radius: 8px;
                font-size: 1rem;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.2s;
            }

            .btn-primary {
                background: linear-gradient(135deg, #6366f1, #8b5cf6);
                color: #fff;
            }

            .btn-primary:hover {
                opacity: 0.9;
                transform: translateY(-1px);
            }

            .error {
                background: #3b1515;
                border: 1px solid #7f1d1d;
                color: #fca5a5;
                padding: 0.75rem 1rem;
                border-radius: 8px;
                font-size: 0.875rem;
                margin-bottom: 1.25rem;
            }

            .footer {
                text-align: center;
                margin-top: 1.5rem;
                font-size: 0.75rem;
                color: #444455;
            }

            .footer a {
                color: #6366f1;
                text-decoration: none;
            }
        </style>
    </head>

    <body>
        <div class="login-card">
            <h1>&#x1F310; Club7 CDN</h1>
            <p class="subtitle">Admin Panel — Authentication Required</p>
            <?php if ($error): ?>
                <div class="error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="POST">
                <input type="hidden" name="action" value="login">
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="Enter admin password" autofocus
                        autocomplete="current-password">
                </div>
                <button type="submit" class="btn btn-primary">Sign In</button>
            </form>
            <div class="footer">
                Club7 CDN &mdash; Secure Admin Access
            </div>
        </div>
    </body>

    </html>
    <?php
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Club7 CDN — Admin Panel</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #0a0a12;
            color: #e0e0e0;
            min-height: 100vh;
        }

        .header {
            background: #111120;
            border-bottom: 1px solid #2a2a45;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 1.25rem;
            font-weight: 700;
            background: linear-gradient(90deg, #6366f1, #a78bfa);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .badge {
            background: rgba(34, 197, 94, 0.12);
            color: #4ade80;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .logout-btn {
            background: #2a2a45;
            color: #a5a5c0;
            border: 1px solid #3a3a55;
            padding: 0.4rem 1rem;
            border-radius: 8px;
            font-size: 0.8rem;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
        }

        .logout-btn:hover {
            background: #3a1a1a;
            border-color: #7f1d1d;
            color: #fca5a5;
        }

        .container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 2rem;
        }

        .alert {
            padding: 0.75rem 1rem;
            border-radius: 8px;
            font-size: 0.875rem;
            margin-bottom: 1.5rem;
        }

        .alert-success {
            background: #052e16;
            border: 1px solid #166534;
            color: #86efac;
        }

        .alert-error {
            background: #3b1515;
            border: 1px solid #7f1d1d;
            color: #fca5a5;
        }

        /* Stats grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: #111120;
            border: 1px solid #2a2a45;
            border-radius: 12px;
            padding: 1.25rem;
        }

        .stat-card .label {
            font-size: 0.75rem;
            color: #666688;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
        }

        .stat-card .value {
            font-size: 1.75rem;
            font-weight: 700;
        }

        .stat-card .sub {
            font-size: 0.8rem;
            color: #888899;
            margin-top: 0.25rem;
        }

        .text-green {
            color: #4ade80;
        }

        .text-yellow {
            color: #fbbf24;
        }

        .text-red {
            color: #f87171;
        }

        .text-blue {
            color: #60a5fa;
        }

        .text-purple {
            color: #a78bfa;
        }

        /* Sections */
        .section {
            background: #111120;
            border: 1px solid #2a2a45;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .section-title {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid #2a2a45;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Category bars */
        .cat-bar {
            margin-bottom: 0.75rem;
        }

        .cat-bar-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.3rem;
            font-size: 0.875rem;
        }

        .cat-bar-header .name {
            color: #c0c0e0;
            font-weight: 500;
        }

        .cat-bar-header .size {
            color: #666688;
        }

        .progress-track {
            background: #1a1a2e;
            border-radius: 4px;
            height: 8px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            border-radius: 4px;
            background: linear-gradient(90deg, #6366f1, #a78bfa);
            transition: width 0.5s ease;
        }

        /* Upload form */
        .upload-form {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 0.75rem;
            align-items: end;
        }

        .form-field {
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
        }

        .form-field label {
            font-size: 0.75rem;
            color: #888899;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .form-field select,
        .form-field input[type="file"] {
            padding: 0.6rem 0.75rem;
            background: #0a0a15;
            border: 1px solid #2a2a45;
            border-radius: 8px;
            color: #e0e0e0;
            font-size: 0.9rem;
            outline: none;
            cursor: pointer;
        }

        .form-field select:focus {
            border-color: #6366f1;
        }

        .btn-upload {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: #fff;
            border: none;
            padding: 0.65rem 1.25rem;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            white-space: nowrap;
            transition: opacity 0.2s;
        }

        .btn-upload:hover {
            opacity: 0.9;
        }

        .upload-hint {
            font-size: 0.75rem;
            color: #555566;
            margin-top: 0.5rem;
        }

        /* File table */
        .file-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }

        .file-table th {
            text-align: left;
            padding: 0.6rem 0.75rem;
            color: #666688;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 1px solid #2a2a45;
        }

        .file-table td {
            padding: 0.6rem 0.75rem;
            border-bottom: 1px solid #1a1a2e;
            vertical-align: middle;
        }

        .file-table tr:last-child td {
            border-bottom: none;
        }

        .file-table tr:hover td {
            background: rgba(99, 102, 241, 0.03);
        }

        .file-preview {
            width: 40px;
            height: 40px;
            border-radius: 6px;
            object-fit: cover;
            background: #1a1a2e;
        }

        .file-name {
            color: #a5b4fc;
            font-weight: 500;
            word-break: break-all;
            max-width: 200px;
        }

        .file-cat {
            display: inline-block;
            background: rgba(99, 102, 241, 0.12);
            color: #818cf8;
            padding: 0.15rem 0.5rem;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .file-size {
            color: #666688;
        }

        .file-date {
            color: #555566;
            font-size: 0.8rem;
        }

        .btn-delete {
            background: #2a1515;
            color: #f87171;
            border: 1px solid #4a2020;
            padding: 0.3rem 0.6rem;
            border-radius: 6px;
            font-size: 0.75rem;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-delete:hover {
            background: #3b1a1a;
            border-color: #7f1d1d;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #444455;
            font-size: 0.9rem;
        }

        /* Password change */
        .pw-form {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 0.75rem;
            align-items: end;
            max-width: 600px;
        }

        /* Grid layout */
        .main-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        .full-width {
            grid-column: 1 / -1;
        }

        @media (max-width: 900px) {
            .main-grid {
                grid-template-columns: 1fr;
            }

            .upload-form,
            .pw-form {
                grid-template-columns: 1fr;
            }
        }

        /* Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal {
            background: #111120;
            border: 1px solid #2a2a45;
            border-radius: 16px;
            padding: 2rem;
            max-width: 500px;
            width: 90%;
        }

        .modal h2 {
            font-size: 1.15rem;
            margin-bottom: 1rem;
        }

        .modal-actions {
            display: flex;
            gap: 0.75rem;
            margin-top: 1.5rem;
        }

        .btn-cancel {
            background: #1a1a2e;
            color: #a5a5c0;
            border: 1px solid #2a2a45;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
        }

        .btn-danger {
            background: #7f1d1d;
            color: #fca5a5;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
        }

        .btn-danger:hover {
            background: #991b1b;
        }

        .search-box {
            width: 100%;
            padding: 0.5rem 0.75rem;
            background: #0a0a15;
            border: 1px solid #2a2a45;
            border-radius: 8px;
            color: #e0e0e0;
            font-size: 0.85rem;
            outline: none;
            margin-bottom: 1rem;
        }

        .search-box:focus {
            border-color: #6366f1;
        }

        .tab-bar {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }

        .tab {
            padding: 0.4rem 0.85rem;
            border-radius: 6px;
            font-size: 0.8rem;
            cursor: pointer;
            border: 1px solid #2a2a45;
            background: #0a0a15;
            color: #888899;
            transition: all 0.2s;
            text-decoration: none;
        }

        .tab:hover,
        .tab.active {
            background: rgba(99, 102, 241, 0.15);
            border-color: #6366f1;
            color: #a5b4fc;
        }

        .tab-count {
            background: #2a2a45;
            padding: 0 0.35rem;
            border-radius: 999px;
            font-size: 0.7rem;
            margin-left: 0.35rem;
        }

        .tab.active .tab-count {
            background: #6366f1;
            color: #fff;
        }

        /* Loading */
        .spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(99, 102, 241, 0.3);
            border-top-color: #6366f1;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .hidden {
            display: none !important;
        }

        #uploadResult {
            margin-top: 0.75rem;
        }
    </style>
</head>

<body>
    <?php
    // ── Helpers ──────────────────────────────────────────────────────────────
    function admin_bytes($b)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($b >= 1024 && $i < count($units) - 1) {
            $b /= 1024;
            $i++;
        }
        return round($b, 2) . ' ' . $units[$i];
    }
    function admin_scan_dir(string $dir): array
    {
        $files = [];
        if (!is_dir($dir))
            return $files;
        $dh = @opendir($dir);
        if (!$dh)
            return $files;
        while (($f = readdir($dh)) !== false) {
            if ($f === '.' || $f === '..')
                continue;
            $fp = $dir . $f;
            if (is_file($fp)) {
                $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
                $files[] = ['name' => $f, 'size' => filesize($fp), 'ext' => $ext, 'modified' => filemtime($fp)];
            }
        }
        closedir($dh);
        usort($files, fn($a, $b) => $b['modified'] - $a['modified']);
        return $files;
    }
    function admin_storage_stats()
    {
        $root = dirname(__DIR__);
        $cats = ['avatars', 'events', 'gallery', 'sponsors', 'documents', 'forms'];
        $total = 0;
        $byCat = [];
        foreach ($cats as $cat) {
            $bytes = 0;
            $dh = @opendir($root . '/uploads/' . $cat . '/');
            if ($dh) {
                while (($f = readdir($dh)) !== false) {
                    if ($f === '.' || $f === '..')
                        continue;
                    $fp = $root . '/uploads/' . $cat . '/' . $f;
                    if (is_file($fp))
                        $bytes += filesize($fp);
                }
                closedir($dh);
            }
            $byCat[$cat] = ['bytes' => $bytes, 'count' => 0];
            $total += $bytes;
        }
        $max = 5 * 1024 * 1024 * 1024;
        $pct = $max > 0 ? round(($total / $max) * 100, 2) : 0;
        return ['total' => $total, 'max' => $max, 'pct' => $pct, 'status' => $pct >= 100 ? 'full' : ($pct >= 80 ? 'critical' : 'ok'), 'byCat' => $byCat];
    }
    function admin_thumb(string $dir, string $name, int $px = 80)
    {
        if (!is_dir($dir))
            return '';
        $base = pathinfo($name, PATHINFO_FILENAME);
        foreach (['webp', 'jpg', 'jpeg', 'png'] as $ext) {
            $f = $dir . "{$base}_{$px}w.{$ext}";
            if (file_exists($f))
                return $f;
        }
        return '';
    }
    function admin_image_ext(string $ext): bool
    {
        return in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'webp'], true);
    }

    // Check session
    $maxAge = (int) ($config['session_timeout'] ?? 3600);
    if (isset($_SESSION['club7_admin_time']) && (time() - $_SESSION['club7_admin_time']) > $maxAge) {
        session_destroy();
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
        exit;
    }
    $_SESSION['club7_admin_time'] = time();

    $stats = admin_storage_stats();
    $message = $_GET['msg'] ?? '';
    $msgType = $_GET['type'] ?? '';
    $activeTab = $_GET['tab'] ?? 'all';
    $search = $_GET['q'] ?? '';
    ?>

    <div class="header">
        <h1>&#x2699;&#xFE0F; Club7 CDN Admin</h1>
        <div class="header-right">
            <span class="badge">&#x25CF; Online</span>
            <a href="?action=logout" class="logout-btn" onclick="return confirm('Log out of admin panel?')">Logout</a>
        </div>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-<?= $msgType === 'error' ? 'error' : 'success' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- ── Storage Overview ── -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="label">Total Used</div>
                <div class="value <?= $stats['pct'] >= 80 ? 'text-red' : 'text-green' ?>">
                    <?= admin_bytes($stats['total']) ?>
                </div>
                <div class="sub"><?= $stats['pct'] ?>% of 5 GB</div>
            </div>
            <div class="stat-card">
                <div class="label">Remaining</div>
                <div class="value text-blue">
                    <?= admin_bytes($stats['max'] - $stats['total']) ?>
                </div>
                <div class="sub"><?= round(($stats['max'] - $stats['total']) / 1024 / 1024 / 1024, 2) ?> GB left</div>
            </div>
            <div class="stat-card">
                <div class="label">Status</div>
                <div
                    class="value <?= $stats['status'] === 'full' ? 'text-red' : ($stats['status'] === 'critical' ? 'text-yellow' : 'text-green') ?>">
                    <?= strtoupper($stats['status']) ?>
                </div>
                <div class="sub"><?= $stats['pct'] >= 100 ? 'Uploads blocked' : 'Uploads allowed' ?></div>
            </div>
            <div class="stat-card">
                <div class="label">Max Upload</div>
                <div class="value text-purple">10 MB</div>
                <div class="sub">Images &amp; Documents</div>
            </div>
        </div>

        <!-- ── Main Grid ── -->
        <div class="main-grid">

            <!-- ── Category Usage ── -->
            <div class="section">
                <div class="section-title">&#x1F4CA; Storage by Category</div>
                <?php foreach ($stats['byCat'] as $cat => $data):
                    $catPct = $stats['max'] > 0 ? round(($data['bytes'] / $stats['max']) * 100, 2) : 0; ?>
                    <div class="cat-bar">
                        <div class="cat-bar-header">
                            <span class="name"><?= ucfirst($cat) ?></span>
                            <span class="size"><?= admin_bytes($data['bytes']) ?> (<?= $catPct ?>%)</span>
                        </div>
                        <div class="progress-track">
                            <div class="progress-fill" style="width: <?= min($catPct, 100) ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- ── Upload ── -->
            <div class="section">
                <div class="section-title">&#x2B06;&#xFE0F; Upload File</div>
                <form class="upload-form" id="uploadForm" enctype="multipart/form-data" method="POST"
                    action="?action=upload">
                    <input type="hidden" name="csrf_token"
                        value="<?= htmlspecialchars($_SESSION['club7_csrf'] ?? bin2hex(random_bytes(16))) ?>">
                    <div class="form-field">
                        <label>Category</label>
                        <select name="category" required>
                            <option value="">Select category...</option>
                            <option value="avatars">Avatars</option>
                            <option value="events">Events</option>
                            <option value="gallery">Gallery</option>
                            <option value="sponsors">Sponsors</option>
                            <option value="documents">Documents</option>
                            <option value="forms">Forms</option>
                        </select>
                    </div>
                    <div class="form-field">
                        <label>File (JPG, PNG, WebP, PDF — max 10 MB)</label>
                        <input type="file" name="file" accept=".jpg,.jpeg,.png,.webp,.pdf" required>
                    </div>
                    <button type="submit" class="btn-upload" id="uploadBtn">&#x2B06;&#xFE0F; Upload</button>
                </form>
                <div class="upload-hint">Allowed: jpg, jpeg, png, webp, pdf &nbsp;|&nbsp; Max: 10 MB per file</div>
                <div id="uploadResult"></div>
            </div>

            <!-- ── Password Change ── -->
            <div class="section">
                <div class="section-title">&#x1F510; Change Admin Password</div>
                <form class="pw-form" method="POST">
                    <input type="hidden" name="action" value="change_password">
                    <div class="form-field">
                        <label>Current Password</label>
                        <input type="password" name="current_password" placeholder="Enter current password" required>
                    </div>
                    <div class="form-field">
                        <label>New Password</label>
                        <input type="password" name="new_password" placeholder="At least 4 characters" required>
                    </div>
                    <button type="submit" class="btn-upload">&#x1F510; Change</button>
                </form>
            </div>

            <!-- ── File Manager ── -->
            <div class="section full-width">
                <div class="section-title">&#x1F4C1; File Manager</div>

                <div
                    style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.75rem; margin-bottom:1rem;">
                    <input type="text" class="search-box" id="searchBox" placeholder="Search files..."
                        value="<?= htmlspecialchars($search) ?>" style="max-width:300px; margin-bottom:0;">
                    <span style="font-size:0.8rem; color:#555566;" id="fileCount"></span>
                </div>

                <div class="tab-bar">
                    <?php
                    $allCats = ['all' => 'All Files', 'avatars' => 'Avatars', 'events' => 'Events', 'gallery' => 'Gallery', 'sponsors' => 'Sponsors', 'documents' => 'Documents', 'forms' => 'Forms'];
                    foreach ($allCats as $key => $label):
                        $count = $key === 'all' ? 0 : $stats['byCat'][$key]['bytes'];
                        // count files
                        $fileCount = 0;
                        if ($key === 'all') {
                            foreach (['avatars', 'events', 'gallery', 'sponsors', 'documents', 'forms'] as $c) {
                                $dh = @opendir(dirname(__DIR__) . '/uploads/' . $c . '/');
                                if ($dh) {
                                    while (($ff = readdir($dh)) !== false) {
                                        if ($ff === '.' || $ff === '..')
                                            continue;
                                        if (is_file(dirname(__DIR__) . '/uploads/' . $c . '/' . $ff))
                                            $fileCount++;
                                    }
                                    closedir($dh);
                                }
                            }
                        }
                        ?>
                        <a href="?tab=<?= $key ?><?= $search ? '&q=' . urlencode($search) : '' ?>"
                            class="tab <?= $activeTab === $key ? 'active' : '' ?>">
                            <?= $label ?>
                            <?php if ($key === 'all'): ?>
                                <?php
                                $totalFiles = 0;
                                foreach (['avatars', 'events', 'gallery', 'sponsors', 'documents', 'forms'] as $c) {
                                    $dh2 = @opendir(dirname(__DIR__) . '/uploads/' . $c . '/');
                                    if ($dh2) {
                                        while (($ff = readdir($dh2)) !== false) {
                                            if ($ff === '.' || $ff === '..')
                                                continue;
                                            if (is_file(dirname(__DIR__) . '/uploads/' . $c . '/' . $ff))
                                                $totalFiles++;
                                        }
                                        closedir($dh2);
                                    }
                                }
                                ?>
                                <span class="tab-count"><?= $totalFiles ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>

                <div style="overflow-x:auto;">
                    <table class="file-table">
                        <thead>
                            <tr>
                                <th>Preview</th>
                                <th>Filename</th>
                                <th>Category</th>
                                <th>Size</th>
                                <th>Uploaded</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="fileTableBody">
                            <?php
                            $root = dirname(__DIR__);
                            $categories = ['avatars', 'events', 'gallery', 'sponsors', 'documents', 'forms'];
                            $allFiles = [];
                            foreach ($categories as $cat) {
                                $dir = $root . '/uploads/' . $cat . '/';
                                foreach (admin_scan_dir($dir) as $f) {
                                    $f['category'] = $cat;
                                    $allFiles[] = $f;
                                }
                            }
                            usort($allFiles, fn($a, $b) => $b['modified'] - $a['modified']);

                            // Filter
                            if ($activeTab !== 'all') {
                                $allFiles = array_filter($allFiles, fn($f) => $f['category'] === $activeTab);
                            }
                            if ($search) {
                                $allFiles = array_filter($allFiles, fn($f) => stripos($f['name'], $search) !== false);
                            }

                            if (empty($allFiles)):
                                ?>
                                <tr>
                                    <td colspan="6" class="empty-state">
                                        <?php if ($activeTab !== 'all' || $search): ?>
                                            &#x1F50D; No files match your filter.
                                        <?php else: ?>
                                            &#x1F4C1; No files uploaded yet. Use the form above to upload your first file.
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php else:
                                $displayFiles = array_slice($allFiles, 0, 100);
                                foreach ($displayFiles as $f):
                                    $relPath = 'uploads/' . $f['category'] . '/' . $f['name'];
                                    $thumbPath = admin_thumb($root . '/thumbnails/' . $f['category'] . '/', $f['name']);
                                    $previewSrc = '';
                                    if (admin_image_ext($f['ext'])) {
                                        if ($thumbPath) {
                                            $previewSrc = 'thumbnails/' . $f['category'] . '/' . basename($thumbPath);
                                        } else {
                                            $previewSrc = $relPath;
                                        }
                                    }
                                    ?>
                                    <tr>
                                        <td>
                                            <?php if (admin_image_ext($f['ext'])): ?>
                                                <img src="../<?= htmlspecialchars($previewSrc) ?>" class="file-preview" alt="">
                                            <?php else: ?>
                                                <div class="file-preview"
                                                    style="display:flex;align-items:center;justify-content:center;font-size:1.2rem;">
                                                    &#x1F4C4;</div>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="file-name"><?= htmlspecialchars($f['name']) ?></span></td>
                                        <td><span class="file-cat"><?= $f['category'] ?></span></td>
                                        <td class="file-size"><?= admin_bytes($f['size']) ?></td>
                                        <td class="file-date">
                                            <?= date('M d, Y', $f['modified']) ?><br><small><?= date('H:i', $f['modified']) ?></small>
                                        </td>
                                        <td>
                                            <a href="../<?= htmlspecialchars($relPath) ?>" target="_blank" class="btn-delete"
                                                style="margin-right:0.35rem;">&#x1F517; View</a>
                                            <a href="?action=delete&file=<?= urlencode($f['name']) ?>&cat=<?= urlencode($f['category']) ?>&tab=<?= urlencode($activeTab) ?><?= $search ? '&q=' . urlencode($search) : '' ?>"
                                                class="btn-delete"
                                                onclick="return confirm('Delete <?= htmlspecialchars($f['name']) ?>? This cannot be undone.')">
                                                &#x1F5D1; Delete
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach;
                                if (count($allFiles) > 100): ?>
                                    <tr>
                                        <td colspan="6" style="text-align:center; color:#555566; padding:1rem;">
                                            Showing 100 of <?= count($allFiles) ?> files. Use search or filter to narrow.
                                        </td>
                                    </tr>
                                <?php endif;
                            endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div><!-- end main-grid -->

        <footer style="text-align:center; padding:2rem; color:#333344; font-size:0.8rem;">
            Club7 CDN Admin Panel &mdash; All paths are relative &mdash; No database
        </footer>
    </div>

    <!-- ── Delete Confirmation Modal ── -->
    <div class="modal-overlay" id="deleteModal">
        <div class="modal">
            <h2>&#x26A0;&#xFE0F; Confirm Deletion</h2>
            <p style="color:#a5a5c0; font-size:0.9rem; line-height:1.6;">
                You are about to permanently delete <strong id="deleteFileName"></strong>.<br>
                This action cannot be undone.
            </p>
            <div class="modal-actions">
                <button class="btn-cancel"
                    onclick="document.getElementById('deleteModal').classList.remove('active')">Cancel</button>
                <button class="btn-danger" id="confirmDeleteBtn">&#x1F5D1; Delete Permanently</button>
            </div>
        </div>
    </div>

    <script>
        // Upload via AJAX
        const uploadForm = document.getElementById('uploadForm');
        if (uploadForm) {
            uploadForm.addEventListener('submit', async function (e) {
                e.preventDefault();
                const btn = document.getElementById('uploadBtn');
                const result = document.getElementById('uploadResult');
                const formData = new FormData(this);

                btn.disabled = true;
                btn.innerHTML = '<span class="spinner"></span> Uploading...';
                result.innerHTML = '';

                try {
                    const resp = await fetch('../api/upload.php', {
                        method: 'POST',
                        headers: { 'X-Api-Key': '<?= htmlspecialchars($config["password"]) ?>' },
                        body: formData,
                    });
                    const data = await resp.json();
                    if (resp.ok && data.success) {
                        result.innerHTML = '<div class="alert alert-success">&#x2705; ' + data.message + ' — <a href="../' + data.file.relative_path + '" target="_blank" style="color:#86efac;">View file</a></div>';
                        uploadForm.reset();
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        result.innerHTML = '<div class="alert alert-error">&#x274C; ' + (data.error || 'Upload failed') + '</div>';
                    }
                } catch (err) {
                    result.innerHTML = '<div class="alert alert-error">&#x274C; Network error: ' + err.message + '</div>';
                }
                btn.disabled = false;
                btn.innerHTML = '&#x2B06;&#xFE0F; Upload';
            });
        }

        // Search
        const searchBox = document.getElementById('searchBox');
        if (searchBox) {
            searchBox.addEventListener('input', function () {
                const q = this.value;
                if (q.length > 0) location.href = '?tab=<?= urlencode($activeTab) ?>&q=' + encodeURIComponent(q);
                else location.href = '?tab=<?= urlencode($activeTab) ?>';
            });
        }

        // Delete confirmation
        const deleteLinks = document.querySelectorAll('a[href*="action=delete"]');
        const modal = document.getElementById('deleteModal');
        const fileNameEl = document.getElementById('deleteFileName');
        const confirmBtn = document.getElementById('confirmDeleteBtn');

        deleteLinks.forEach(function (link) {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                const fname = this.getAttribute('data-file') || this.textContent.trim();
                fileNameEl.textContent = fname;
                confirmBtn.onclick = function () {
                    modal.classList.remove('active');
                    window.location.href = this.href;
                };
                modal.classList.add('active');
            });
        });
    </script>
</body>

</html>