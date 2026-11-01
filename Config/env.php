<?php
/**
 * Config/env.php
 * Parser de variables de entorno desde el archivo .env
 * Sin dependencias externas — compatible con cualquier hosting PHP.
 */

function loadEnv(string $path): void {
    if (!file_exists($path)) return;

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        // Ignorar comentarios
        if (str_starts_with($line, '#') || !str_contains($line, '=')) continue;

        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);

        // Remover comillas opcionales
        $value = trim($value, '"\'');

        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key]    = $value;
            putenv("{$key}={$value}");
        }
    }
}

function env(string $key, mixed $default = null): mixed {
    return $_ENV[$key] ?? getenv($key) ?: $default;
}

// Cargar automáticamente al incluir este archivo
loadEnv(dirname(__DIR__) . '/.env');
