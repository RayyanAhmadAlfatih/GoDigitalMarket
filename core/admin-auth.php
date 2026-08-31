<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| ADMIN AUTH HELPERS
|--------------------------------------------------------------------------
| Centralized admin authentication for all dashboard routes.
| Public output uses buyer-friendly/admin-friendly wording only.
|--------------------------------------------------------------------------
*/

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

if (!defined('ADMIN_AUTH_SESSION_KEY')) {
    define('ADMIN_AUTH_SESSION_KEY', 'admin_logged_in');
}

if (!defined('ADMIN_AUTH_LEGACY_SESSION_KEY')) {
    define('ADMIN_AUTH_LEGACY_SESSION_KEY', 'admin_articles_logged_in');
}

if (!defined('ADMIN_AUTH_LAST_ACTIVITY_KEY')) {
    define('ADMIN_AUTH_LAST_ACTIVITY_KEY', 'admin_last_activity');
}

if (!defined('ADMIN_AUTH_USER_SESSION_KEY')) {
    define('ADMIN_AUTH_USER_SESSION_KEY', 'admin_user');
}

if (!function_exists('admin_auth_timeout_seconds')) {
    function admin_auth_timeout_seconds(): int
    {
        $value = (int)($_ENV['ADMIN_SESSION_TIMEOUT'] ?? 7200);
        return max(900, min($value, 86400));
    }
}

if (!function_exists('admin_auth_password')) {
    function admin_auth_password(): string
    {
        return trim((string)($_ENV['ADMIN_PASSWORD'] ?? ''));
    }
}

if (!function_exists('admin_auth_password_hash')) {
    function admin_auth_password_hash(): string
    {
        return trim((string)($_ENV['ADMIN_PASSWORD_HASH'] ?? ''));
    }
}

if (!function_exists('admin_auth_password_needs_setup')) {
    function admin_auth_password_needs_setup(): bool
    {
        if (function_exists('admin_users_read_all')) {
            foreach (admin_users_read_all() as $row) {
                if ((string)($row['role'] ?? '') === 'owner' && (string)($row['status'] ?? 'active') === 'active' && (string)($row['password_hash'] ?? '') !== '') {
                    return false;
                }
            }
        }

        $hash = admin_auth_password_hash();
        if ($hash !== '' && preg_match('/^\$2y\$|^\$argon2/i', $hash)) {
            return false;
        }

        return function_exists('admin_password_needs_setup')
            ? admin_password_needs_setup(admin_auth_password())
            : admin_auth_password() === '';
    }
}

if (!function_exists('admin_auth_is_logged_in')) {
    function admin_auth_is_logged_in(): bool
    {
        if (empty($_SESSION[ADMIN_AUTH_SESSION_KEY])) {
            return false;
        }

        $lastActivity = (int)($_SESSION[ADMIN_AUTH_LAST_ACTIVITY_KEY] ?? 0);
        if ($lastActivity > 0 && (time() - $lastActivity) > admin_auth_timeout_seconds()) {
            admin_auth_logout(false);
            return false;
        }

        $_SESSION[ADMIN_AUTH_LAST_ACTIVITY_KEY] = time();
        // Keep legacy admin pages in sync with the central login session.
        // Older admin modules still read ADMIN_AUTH_LEGACY_SESSION_KEY internally,
        // while route access is now guarded centrally in index.php.
        $_SESSION[ADMIN_AUTH_LEGACY_SESSION_KEY] = true;
        return true;
    }
}

if (!function_exists('admin_auth_login')) {
    function admin_auth_login(?array $user = null): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        if (!$user && function_exists('admin_users_emergency_owner')) {
            $user = admin_users_emergency_owner();
        }

        if ($user) {
            $_SESSION[ADMIN_AUTH_USER_SESSION_KEY] = [
                'id' => (string)($user['id'] ?? 'env_owner'),
                'name' => (string)($user['name'] ?? 'Owner'),
                'email' => (string)($user['email'] ?? ''),
                'username' => (string)($user['username'] ?? ''),
                'role' => (string)($user['role'] ?? 'owner'),
                'auth_source' => (string)($user['auth_source'] ?? 'env'),
            ];
        }

        $_SESSION[ADMIN_AUTH_SESSION_KEY] = true;
        $_SESSION[ADMIN_AUTH_LEGACY_SESSION_KEY] = true;
        $_SESSION[ADMIN_AUTH_LAST_ACTIVITY_KEY] = time();

        if ($user && function_exists('admin_users_touch_login')) {
            admin_users_touch_login($user);
        }
    }
}

if (!function_exists('admin_auth_logout')) {
    function admin_auth_logout(bool $regenerate = true): void
    {
        unset(
            $_SESSION[ADMIN_AUTH_SESSION_KEY],
            $_SESSION[ADMIN_AUTH_LEGACY_SESSION_KEY],
            $_SESSION[ADMIN_AUTH_LAST_ACTIVITY_KEY],
            $_SESSION[ADMIN_AUTH_USER_SESSION_KEY]
        );

        if ($regenerate && session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }
}

if (!function_exists('admin_auth_clean_next_path')) {
    function admin_auth_clean_next_path(?string $next): string
    {
        $next = trim((string)$next);
        if ($next === '') {
            return 'admin/brand';
        }

        $next = str_replace('\\', '/', $next);
        $next = preg_replace('#^https?://[^/]+#i', '', $next) ?? '';
        $next = ltrim($next, '/');

        $basePath = trim((string)(parse_url((string)BASE_URL, PHP_URL_PATH) ?? ''), '/');
        if ($basePath !== '' && str_starts_with($next, $basePath . '/')) {
            $next = substr($next, strlen($basePath) + 1);
        }

        if ($next === '' || str_starts_with($next, 'admin/login') || str_starts_with($next, 'admin/logout')) {
            return 'admin/brand';
        }

        if (!admin_auth_is_admin_path($next)) {
            return 'admin/brand';
        }

        return $next;
    }
}

if (!function_exists('admin_auth_is_admin_path')) {
    function admin_auth_is_admin_path(string $path): bool
    {
        $path = trim($path, '/');
        return $path === 'admin'
            || str_starts_with($path, 'admin/')
            || str_starts_with($path, 'admin-');
    }
}

if (!function_exists('admin_auth_is_public_path')) {
    function admin_auth_is_public_path(string $path): bool
    {
        $path = trim($path, '/');
        return in_array($path, ['admin/login', 'admin-login', 'admin/logout', 'admin-logout', 'admin/password-reset', 'admin-password-reset', 'admin/forgot-password', 'admin-forgot-password'], true);
    }
}

if (!function_exists('admin_auth_current_next')) {
    function admin_auth_current_next(string $fallbackPath = 'admin/brand'): string
    {
        $requestUri = (string)($_SERVER['REQUEST_URI'] ?? '');
        if ($requestUri === '') {
            return $fallbackPath;
        }

        return admin_auth_clean_next_path($requestUri);
    }
}

if (!function_exists('admin_auth_require')) {
    function admin_auth_require(string $nextPath = 'admin/brand'): void
    {
        if (!admin_auth_is_logged_in()) {
            redirect_302('admin/login?next=' . rawurlencode(admin_auth_clean_next_path($nextPath)));
        }

        if (function_exists('admin_users_can_access_path') && !admin_users_can_access_path($nextPath)) {
            $role = function_exists('admin_users_current_role') ? admin_users_current_role() : 'owner';
            $fallback = function_exists('admin_users_default_path_for_role') ? admin_users_default_path_for_role($role) : 'admin/brand';
            if (admin_users_can_access_path($fallback)) {
                redirect_302($fallback . '?message=' . rawurlencode('Menu tersebut tidak tersedia untuk role akun Anda.'));
            }
            http_response_code(403);
            exit('Akses dashboard tidak tersedia untuk role akun ini.');
        }
    }
}

if (!function_exists('require_admin_auth')) {
    /**
     * Backward-compatible alias for older admin pages.
     * Current U-Growth routing uses admin_auth_require(), but a few migration
     * module pages from earlier versions still called require_admin_auth().
     */
    function require_admin_auth(string $nextPath = 'admin/brand'): void
    {
        admin_auth_require($nextPath);
    }
}

if (!function_exists('admin_panel_logged_in')) {
    function admin_panel_logged_in(): bool
    {
        return admin_auth_is_logged_in();
    }
}


if (!function_exists('admin_auth_attempt_login')) {
    function admin_auth_attempt_login(string $login, string $password): array
    {
        $login = trim($login);
        if ($login !== '' && function_exists('admin_users_verify_login')) {
            $user = admin_users_verify_login($login, $password);
            if ($user) {
                admin_auth_login($user);
                return ['ok' => true, 'user' => $user, 'source' => 'admin_user'];
            }
        }

        if (function_exists('admin_auth_password_needs_setup') && admin_auth_password_needs_setup()) {
            return ['ok' => false, 'message' => 'Password admin utama belum aman. Silakan ganti ke password kuat sebelum masuk dashboard.'];
        }

        $envPasswordHash = function_exists('admin_auth_password_hash') ? admin_auth_password_hash() : (string)($_ENV['ADMIN_PASSWORD_HASH'] ?? '');
        if ($envPasswordHash !== '' && password_verify($password, $envPasswordHash)) {
            $user = function_exists('admin_users_emergency_owner') ? admin_users_emergency_owner() : ['role' => 'owner', 'name' => 'Owner'];
            admin_auth_login($user);
            return ['ok' => true, 'user' => $user, 'source' => 'env_hash'];
        }

        $envPassword = function_exists('admin_auth_password') ? admin_auth_password() : (string)($_ENV['ADMIN_PASSWORD'] ?? '');
        if ($envPassword !== '' && hash_equals($envPassword, $password)) {
            $user = function_exists('admin_users_emergency_owner') ? admin_users_emergency_owner() : ['role' => 'owner', 'name' => 'Owner'];
            admin_auth_login($user);
            return ['ok' => true, 'user' => $user, 'source' => 'env'];
        }

        return ['ok' => false, 'message' => $login !== '' ? 'Email/username atau password admin salah.' : 'Password admin salah.'];
    }
}

if (!function_exists('admin_auth_current_user')) {
    function admin_auth_current_user(): array
    {
        return function_exists('admin_users_current') ? admin_users_current() : (is_array($_SESSION[ADMIN_AUTH_USER_SESSION_KEY] ?? null) ? $_SESSION[ADMIN_AUTH_USER_SESSION_KEY] : []);
    }
}
