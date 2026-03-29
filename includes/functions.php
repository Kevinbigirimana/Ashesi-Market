<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';

session_name(SESSION_NAME);
session_start();


// CSRF helpers
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function verify_csrf(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals(csrf_token(), $token)) {
        http_response_code(403);
        die('CSRF token mismatch. Please go back and try again.');
    }
}


// Flash messages

function set_flash(string $type, string $msg): void {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function get_flash(): ?array {
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function render_flash(): string {
    $f = get_flash();
    if (!$f) return '';
    $cls = $f['type'] === 'success' ? 'alert-success' : ($f['type'] === 'error' ? 'alert-error' : 'alert-info');
    return '<div class="alert ' . $cls . '">' . htmlspecialchars($f['msg']) . '</div>';
}


// Auth helpers

function is_logged_in(): bool {
    return !empty($_SESSION['user_id']);
}

function current_user(): ?array {
    if (!is_logged_in()) return null;
    return $_SESSION['user'] ?? null;
}

function require_login(): void {
    if (!is_logged_in()) {
        set_flash('error', 'Please log in to continue.');
        header('Location: ' . BASE_URL . '/pages/login.php');
        exit;
    }
}

function require_complete_profile(): void {
    require_login();
    $u = current_user();
    if (empty($u['profile_complete'])) {
        set_flash('info', 'Please complete your profile before selling.');
        header('Location: ' . BASE_URL . '/pages/setup_profile.php');
        exit;
    }
}


// Redirect helper

function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}


// Sanitize output

function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}


// WhatsApp URL builder

function whatsapp_url(string $phone, string $message): string {
    // Strip non-digits, assume Ghanaian +233 if no country code
    $phone = preg_replace('/\D/', '', $phone);
    if (strlen($phone) === 10 && $phone[0] === '0') {
        $phone = '233' . substr($phone, 1);
    }
    return 'https://wa.me/' . $phone . '?text=' . rawurlencode($message);
}


// Refresh session user from DB

function refresh_session_user(mysqli $conn): void {
    if (!is_logged_in()) return;
    $stmt = $conn->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) $_SESSION['user'] = $row;
}


// Star rating HTML

function stars(float $rating, int $max = 5): string {
    $html = '<span class="stars">';
    for ($i = 1; $i <= $max; $i++) {
        $html .= '<span class="star' . ($i <= round($rating) ? ' filled' : '') . '">&#9733;</span>';
    }
    $html .= '</span>';
    return $html;
}


// Cart count for nav badge

function cart_count(mysqli $conn): int {
    if (!is_logged_in()) return 0;
    $uid = $_SESSION['user_id'];
    $stmt = $conn->prepare(
        'SELECT COALESCE(SUM(ci.quantity), 0) AS cnt
         FROM cart c
         JOIN cart_items ci ON ci.cart_id = c.id
         WHERE c.user_id = ?'
    );
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $cnt = (int) $stmt->get_result()->fetch_assoc()['cnt'];
    $stmt->close();
    return $cnt;
}


// Image upload helper

function handle_image_upload(array $file, string $subfolder): ?string {
    $allowed = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($file['type'], $allowed)) return null;
    if ($file['size'] > IMG_MAX_MB * 1024 * 1024) return null;

    $ext  = pathinfo($file['name'], PATHINFO_EXTENSION);
    $name = bin2hex(random_bytes(12)) . '.' . strtolower($ext);
    $dir  = UPLOAD_DIR . $subfolder . '/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    if (move_uploaded_file($file['tmp_name'], $dir . $name)) {
        return $subfolder . '/' . $name;
    }
    return null;
}
