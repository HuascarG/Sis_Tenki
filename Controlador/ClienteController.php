<?php
// ClienteController — ROOT, database and config are loaded by the caller
class ClienteController {

    public function listar(): array {
        return db()->query("SELECT * FROM clientes WHERE activo=1 ORDER BY nombre")->fetchAll();
    }

    public function getById(int $id): ?array {
        $stmt = db()->prepare("SELECT * FROM clientes WHERE id=? AND activo=1");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function crear(array $data): bool {
        try {
            $pdo  = db();
            $stmt = $pdo->prepare("INSERT INTO clientes (tipo_documento, documento, nombre, telefono, email, direccion, referencia) VALUES (?,?,?,?,?,?,?)");
            return $stmt->execute([
                $data['tipo_documento'], $data['documento'], $data['nombre'],
                $data['telefono'] ?? '', $data['email'] ?? '', $data['direccion'] ?? '', $data['referencia'] ?? ''
            ]);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function actualizar(int $id, array $data): bool {
        try {
            $pdo  = db();
            $stmt = $pdo->prepare("UPDATE clientes SET tipo_documento=?, documento=?, nombre=?, telefono=?, email=?, direccion=?, referencia=? WHERE id=?");
            return $stmt->execute([
                $data['tipo_documento'], $data['documento'], $data['nombre'],
                $data['telefono'] ?? '', $data['email'] ?? '', $data['direccion'] ?? '', $data['referencia'] ?? '', $id
            ]);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function eliminar(int $id): bool {
        try {
            $stmt = db()->prepare("DELETE FROM clientes WHERE id=?");
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function total(): int {
        return (int)db()->query("SELECT COUNT(*) FROM clientes WHERE activo=1")->fetchColumn();
    }
}
