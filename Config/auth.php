<?php
/**
 * Config/auth.php
 * Helpers de autenticación y autorización basados en sesión PHP.
 */

if (session_status() === PHP_SESSION_NONE) {
    // Endurecer cookies de sesión automáticamente si hay HTTPS
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

/** ¿Hay una sesión activa? */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

/** ¿El usuario en sesión es administrador? */
function isAdmin(): bool {
    return ($_SESSION['user_rol'] ?? '') === 'admin';
}

/** Datos del usuario en sesión. Null si no hay sesión. */
function currentUser(): ?array {
    if (!isLoggedIn()) return null;
    return [
        'id'       => $_SESSION['user_id'],
        'nombre'   => $_SESSION['user_nombre'],
        'apellido' => $_SESSION['user_apellido'],
        'cedula'   => $_SESSION['user_cedula'],
        'correo'   => $_SESSION['user_correo'],
        'rol'      => $_SESSION['user_rol'],
    ];
}

/**
 * Redirige al login si no hay sesión activa.
 * Guarda la URL solicitada para redirigir después del login.
 */
function requireLogin(): void {
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '';
        header('Location: ' . BASE_URL . 'Vista/auth/login.php');
        exit;
    }
}

/** Aborta con 403 si el usuario no es admin. */
function requireAdmin(): void {
    requireLogin();
    if (!isAdmin()) {
        header('Location: ' . BASE_URL . 'Vista/auth/403.php');
        exit;
    }
}

/** Inicia la sesión del usuario tras login exitoso. */
function startUserSession(array $user): void {
    session_regenerate_id(true);
    $_SESSION['user_id']       = $user['id'];
    $_SESSION['user_nombre']   = $user['nombre'];
    $_SESSION['user_apellido'] = $user['apellido'];
    $_SESSION['user_cedula']   = $user['cedula'];
    $_SESSION['user_correo']   = $user['correo'];
    $_SESSION['user_rol']      = $user['rol'];
}

/** Destruye la sesión completamente. */
function destroySession(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

// ── Protección CSRF ─────────────────────────────────────────

/** Genera (o reutiliza) un token CSRF para la sesión actual */
function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Devuelve un campo hidden HTML con el token CSRF */
function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . csrfToken() . '">';
}

/** Valida el token CSRF del POST o header X-CSRF-Token. Aborta con 403 si es inválido. */
function validateCsrf(): void {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($token) || !hash_equals(csrfToken(), $token)) {
        http_response_code(403);
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || (isset($_SERVER['CONTENT_TYPE']) && str_contains($_SERVER['CONTENT_TYPE'], 'application/json'))) {
            header('Content-Type: application/json; charset=utf-8');
            die(json_encode(['status' => 'error', 'message' => 'Token de seguridad inválido o sesión expirada.']));
        }
        die('<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Error de Seguridad</title></head><body style="font-family:sans-serif;text-align:center;padding:50px;"><h2>403 - Solicitud Rechazada</h2><p>El token de seguridad es inválido o la sesión expiró. Por favor recargue la página y vuelva a intentarlo.</p><a href="javascript:history.back()">Regresar</a></body></html>');
    }
}

// ── Rate Limiting (anti fuerza bruta) ───────────────────────

/**
 * Registra un intento de login fallido.
 * Bloquea durante 60 segundos tras 5 intentos fallidos consecutivos.
 */
function checkLoginThrottle(): bool {
    $maxAttempts = 5;
    $lockoutTime = 60; // segundos

    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = 0;
        $_SESSION['login_last_attempt'] = 0;
    }

    // Si está bloqueado, verificar si ya pasó el tiempo
    if ($_SESSION['login_attempts'] >= $maxAttempts) {
        $elapsed = time() - $_SESSION['login_last_attempt'];
        if ($elapsed < $lockoutTime) {
            return false; // Todavía bloqueado
        }
        // Tiempo cumplido, resetear
        $_SESSION['login_attempts'] = 0;
    }
    return true; // Permitido
}

function registerFailedLogin(): void {
    $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
    $_SESSION['login_last_attempt'] = time();
}

function resetLoginAttempts(): void {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['login_last_attempt'] = 0;
}
