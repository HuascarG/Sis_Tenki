<?php
/**
 * Controlador/UsuarioController.php
 */
class UsuarioController {

    public function listar(): array {
        return db()->query("SELECT id,nombre,apellido,cedula,telefono,correo,rol,activo,created_at
                            FROM usuarios ORDER BY nombre")->fetchAll();
    }

    public function getById(int $id): ?array {
        $stmt = db()->prepare("SELECT * FROM usuarios WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function crear(array $data): bool|string {
        // Verificar unicidad de cédula y correo
        $dup = $this->checkDuplicado($data['cedula'], $data['correo']);
        if ($dup) return $dup;

        if (!AuthController::validarPassword($data['password'])) {
            return 'La contraseña no cumple los requisitos de seguridad.';
        }

        $hash = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        try {
            $pdo  = db();
            $stmt = $pdo->prepare("INSERT INTO usuarios
                (nombre,apellido,cedula,telefono,correo,password,rol)
                VALUES (?,?,?,?,?,?,?)");
            return $stmt->execute([
                trim($data['nombre']),
                trim($data['apellido']),
                trim($data['cedula']),
                trim($data['telefono'] ?? ''),
                trim($data['correo']),
                $hash,
                $data['rol'] ?? 'usuario',
            ]);
        } catch (PDOException $e) {
            return 'Error al guardar. Verifique que cédula y correo sean únicos.';
        }
    }

    public function actualizar(int $id, array $data): bool|string {
        $dup = $this->checkDuplicado($data['cedula'], $data['correo'], $id);
        if ($dup) return $dup;

        $pdo = db();

        // Si envían contraseña nueva, validar y hashear
        if (!empty($data['password'])) {
            if (!AuthController::validarPassword($data['password'])) {
                return 'La contraseña no cumple los requisitos de seguridad.';
            }
            $hash = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $pdo->prepare("UPDATE usuarios
                SET nombre=?,apellido=?,cedula=?,telefono=?,correo=?,password=?,rol=?
                WHERE id=?");
            $stmt->execute([
                trim($data['nombre']), trim($data['apellido']),
                trim($data['cedula']), trim($data['telefono'] ?? ''),
                trim($data['correo']), $hash, $data['rol'] ?? 'usuario', $id
            ]);
        } else {
            // Sin cambio de contraseña
            $stmt = $pdo->prepare("UPDATE usuarios
                SET nombre=?,apellido=?,cedula=?,telefono=?,correo=?,rol=?
                WHERE id=?");
            $stmt->execute([
                trim($data['nombre']), trim($data['apellido']),
                trim($data['cedula']), trim($data['telefono'] ?? ''),
                trim($data['correo']), $data['rol'] ?? 'usuario', $id
            ]);
        }
        return true;
    }

    public function cambiarEstado(int $id, int $activo): bool {
        return db()->prepare("UPDATE usuarios SET activo=? WHERE id=?")
                   ->execute([$activo, $id]);
    }

    public function eliminar(int $id): bool {
        // No permite eliminar al usuario en sesión
        if ($id === (int)($_SESSION['user_id'] ?? 0)) return false;
        return db()->prepare("DELETE FROM usuarios WHERE id=?")->execute([$id]);
    }

    /** Verifica duplicado de cédula o correo, excluyendo $excludeId al editar */
    private function checkDuplicado(string $cedula, string $correo, int $excludeId = 0): string|false {
        $pdo  = db();
        $stmt = $pdo->prepare(
            "SELECT id FROM usuarios WHERE (cedula=? OR correo=?) AND id!=? LIMIT 1"
        );
        $stmt->execute([trim($cedula), trim($correo), $excludeId]);
        if ($stmt->fetch()) {
            return 'Ya existe un usuario con esa cédula o correo.';
        }
        return false;
    }
}
