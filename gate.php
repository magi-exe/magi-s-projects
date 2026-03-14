<?php
/**
 * gate.php — Server-side Auth Gateway
 * ─────────────────────────────────────
 * .htaccess rewrites every .html request here.
 * We check the PHP session before serving the file.
 * If not authenticated → hard redirect to login.html.
 * No JavaScript can bypass this.
 */

session_start();

/* ── Pages that never require auth ───────────────────────── */
$PUBLIC_PAGES = ['login.html', 'developer.html'];

/* ── Which file was requested? ───────────────────────────── */
$requested = basename($_GET['page'] ?? '');

// Strip any directory traversal attempts
$requested = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $requested);

// Must be an .html file that actually exists in this folder
$filepath = __DIR__ . '/' . $requested;

/* ── Only these pages are allowed at all; everything else is banned for everyone ── */
$ALLOWED_PAGES = $PUBLIC_PAGES;
if (!in_array($requested, $ALLOWED_PAGES)) {
    http_response_code(403);
    exit('403 Forbidden');
}

if (!$requested || !file_exists($filepath) || pathinfo($filepath, PATHINFO_EXTENSION) !== 'html') {
    http_response_code(404);
    exit('404 Not Found');
}

/* ── Allow public pages through unconditionally ─────────── */
if (in_array($requested, $PUBLIC_PAGES)) {
    readfile($filepath);
    exit;
}

/* ── Check session ───────────────────────────────────────── */
$role = $_SESSION['role'] ?? null; // 'admin' | 'guest' | null

if (!$role) {
    // Not logged in → send to login
    header('Location: login.html');
    exit;
}

/* ── Guest access rules ──────────────────────────────────── */
// Guests may ONLY see the homepage activity feed
$GUEST_PAGES = ['index.html'];

if ($role === 'guest' && !in_array($requested, $GUEST_PAGES)) {
    header('Location: index.html');
    exit;
}

/* ── Authenticated: serve the file ──────────────────────── */
// Serve with correct Content-Type
header('Content-Type: text/html; charset=UTF-8');
// Prevent caching of authenticated pages
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

readfile($filepath);
exit;
