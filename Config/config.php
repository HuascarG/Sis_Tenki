<?php
define('APP_NAME', 'TENKI');
define('APP_SUBTITLE', 'Gestión de Repuestos Automotrices');
if (!function_exists('env')) {
    $envPath = dirname(__DIR__) . '/Config/env.php';
    if (file_exists($envPath)) {
        require_once $envPath;
    }
}

$appUrl = function_exists('env') ? env('APP_URL') : null;
if (!$appUrl) {
    // Detección automática de protocolo y host detrás de proxies (Render, Cloudflare, etc.)
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    $protocol = $isHttps ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $appUrl = $protocol . $host . '/';
}
// Asegurar que siempre termine en '/'
$appUrl = rtrim($appUrl, '/') . '/';

define('BASE_URL', $appUrl);
define('IVA_RATE', 0.16);  // 16%
define('TASA_BCV', 38.50); // Fallback tasa BCV

// Currency formatter
function formatMoney(float $amount): string {
    return '$' . number_format($amount, 2, '.', ',');
}

// Auto-generate consecutive numbers (whitelist tables to prevent SQL injection)
function generarNumero(string $prefix, string $tabla, string $campo): string {
    $allowedTables = ['ventas', 'compras', 'facturas_venta'];
    if (!in_array($tabla, $allowedTables, true)) {
        throw new InvalidArgumentException('Tabla no permitida para generar número.');
    }
    $pdo = db();
    $stmt = $pdo->query("SELECT COUNT(*) FROM `{$tabla}`");
    $count = (int)$stmt->fetchColumn() + 1;
    return $prefix . str_pad($count, 6, '0', STR_PAD_LEFT);
}

// Redirect helper
function redirect(string $url): void {
    header('Location: ' . BASE_URL . $url);
    exit;
}

// Flash messages via session
function setFlash(string $type, string $message): void {
    session_start();
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}
