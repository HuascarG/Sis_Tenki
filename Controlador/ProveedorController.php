<?php
// ProveedorController — ROOT, database and config are loaded by the caller
class ProveedorController {

    public function listar(): array {
        return db()->query("SELECT * FROM proveedores WHERE activo=1 ORDER BY nombre")->fetchAll();
    }

    public function getById(int $id): ?array {
        $stmt = db()->prepare("SELECT * FROM proveedores WHERE id=? AND activo=1");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function crear(array $data): bool {
        try {
            $pdo  = db();
            $stmt = $pdo->prepare("INSERT INTO proveedores (rif, nombre, telefono, email, direccion, contacto) VALUES (?,?,?,?,?,?)");
            return $stmt->execute([
                $data['rif'], $data['nombre'], $data['telefono'] ?? '',
                $data['email'] ?? '', $data['direccion'] ?? '', $data['contacto'] ?? ''
            ]);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function actualizar(int $id, array $data): bool {
        try {
            $pdo  = db();
            $stmt = $pdo->prepare("UPDATE proveedores SET rif=?, nombre=?, telefono=?, email=?, direccion=?, contacto=? WHERE id=?");
            return $stmt->execute([
                $data['rif'], $data['nombre'], $data['telefono'] ?? '',
                $data['email'] ?? '', $data['direccion'] ?? '', $data['contacto'] ?? '', $id
            ]);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function eliminar(int $id): bool {
        try {
            $stmt = db()->prepare("DELETE FROM proveedores WHERE id=?");
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function total(): int {
        return (int)db()->query("SELECT COUNT(*) FROM proveedores WHERE activo=1")->fetchColumn();
    }
}
