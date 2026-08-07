<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

/*
 * Central admin logout endpoint.
 *
 * Enterprise Control Center links to /admin/logout.php.
 * This endpoint clears every application session value, expires
 * the session cookie, destroys the active session and returns the
 * user to the unified PIN login screen at /admin/.
 */

try {
    audit_log('logout', 'Kullanıcı güvenli çıkış yaptı.');
} catch (Throwable) {
    // Logout must never be blocked by audit logging.
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'] ?: '/',
        $params['domain'] ?? '',
        (bool)($params['secure'] ?? false),
        (bool)($params['httponly'] ?? true)
    );
}

if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}

header('Location: ./', true, 303);
exit;
