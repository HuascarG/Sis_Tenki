<?php
/**
 * Controlador/AuthController.php
 */
class AuthController {

    // ── Login / Logout ────────────────────────────────────────

    public function login(string $cedula, string $pass): bool {
        $pdo  = db();
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE cedula = ? AND activo = 1 LIMIT 1");
        $stmt->execute([trim($cedula)]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($pass, $user['password'])) {
            return false;
        }
        startUserSession($user);
        return true;
    }

    public function logout(): void {
        destroySession();
    }

    // ── Recuperación de contraseña ────────────────────────────

    /**
     * Genera token de recuperación y envía el email.
     * Devuelve true siempre (no revelar si el correo existe o no).
     */
    public function solicitarRecuperacion(string $correo): bool {
        $pdo  = db();
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE correo = ? AND activo = 1 LIMIT 1");
        $stmt->execute([trim($correo)]);
        $user = $stmt->fetch();

        if (!$user) return true; // Respuesta genérica por seguridad

        $token   = bin2hex(random_bytes(32)); // 64 chars hex
        $expiry  = date('Y-m-d H:i:s', strtotime('+2 hours'));

        $pdo->prepare("UPDATE usuarios SET reset_token=?, reset_expiry=? WHERE id=?")
            ->execute([$token, $expiry, $user['id']]);

        $link    = BASE_URL . "Vista/auth/nueva_password.php?token={$token}";
        $nombre  = htmlspecialchars($user['nombre']);
        $html    = "
            <div style='font-family:Inter,sans-serif;max-width:520px;margin:auto'>
              <div style='background:#1a1a2e;padding:24px;border-radius:12px 12px 0 0;text-align:center'>
                <h1 style='color:#f97316;margin:0'>TENKI</h1>
                <p style='color:#94a3b8;margin:4px 0 0'>Sistema de Gestión de Repuestos</p>
              </div>
              <div style='background:#16213e;padding:32px;border-radius:0 0 12px 12px'>
                <p style='color:#e2e8f0'>Hola, <strong>{$nombre}</strong>:</p>
                <p style='color:#94a3b8'>Recibimos una solicitud para restablecer la contraseña de tu cuenta TENKI.</p>
                <div style='text-align:center;margin:32px 0'>
                  <a href='{$link}'
                     style='background:#f97316;color:#fff;padding:14px 28px;border-radius:8px;
                            text-decoration:none;font-weight:600;display:inline-block'>
                    Restablecer contraseña
                  </a>
                </div>
                <p style='color:#64748b;font-size:13px'>
                  Este enlace expira en <strong>2 horas</strong>.<br>
                  Si no solicitaste este cambio, ignora este correo.
                </p>
                <hr style='border-color:#334155;margin:24px 0'>
                <p style='color:#64748b;font-size:12px'>
                  O copia y pega este enlace en tu navegador:<br>
                  <span style='color:#f97316'>{$link}</span>
                </p>
              </div>
            </div>";

        try {
            require_once dirname(__DIR__) . '/Config/env.php';
            require_once dirname(__DIR__) . '/Config/mail.php';
            sendMail(
                $user['correo'],
                $user['nombre'] . ' ' . $user['apellido'],
                'Recuperación de contraseña — TENKI',
                $html
            );
        } catch (\Throwable $e) {
            error_log('[TENKI Auth] Error al enviar email de recuperación: ' . $e->getMessage());
        }

        return true;
    }

    /** Valida el token y retorna el usuario, o null si inválido/expirado */
    public function validarToken(string $token): ?array {
        $pdo  = db();
        $stmt = $pdo->prepare(
            "SELECT * FROM usuarios WHERE reset_token = ? AND reset_expiry > NOW() AND activo = 1 LIMIT 1"
        );
        $stmt->execute([trim($token)]);
        return $stmt->fetch() ?: null;
    }

    /** Cambia la contraseña y borra el token de recuperación */
    public function cambiarPassword(int $id, string $nuevaPass): bool {
        $hash = password_hash($nuevaPass, PASSWORD_BCRYPT, ['cost' => 12]);
        $pdo  = db();
        return $pdo->prepare(
            "UPDATE usuarios SET password=?, reset_token=NULL, reset_expiry=NULL WHERE id=?"
        )->execute([$hash, $id]);
    }

    // ── Validación de contraseña ─────────────────────────────

    /**
     * Valida la política de contraseña:
     * ≥8 chars, 1 mayúscula, 1 minúscula, 1 carácter especial.
     */
    public static function validarPassword(string $pass): bool {
        return (bool) preg_match(
            '/^(?=.*[a-z])(?=.*[A-Z])(?=.*[^a-zA-Z0-9]).{8,}$/',
            $pass
        );
    }
}
