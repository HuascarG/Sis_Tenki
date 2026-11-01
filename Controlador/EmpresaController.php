<?php
// Controlador/EmpresaController.php
class EmpresaController {
    
    public function getDatos(): ?array {
        $pdo = db();
        $stmt = $pdo->query("SELECT * FROM empresa WHERE id = 1 LIMIT 1");
        return $stmt->fetch() ?: null;
    }

    public function actualizar(array $data): bool {
        try {
            $pdo = db();
            $sql = "UPDATE empresa SET 
                        nombre = ?, 
                        rif = ?, 
                        telefono = ?, 
                        email = ?, 
                        direccion = ?, 
                        descripcion = ? 
                    WHERE id = 1";
            $stmt = $pdo->prepare($sql);
            return $stmt->execute([
                $data['nombre'],
                $data['rif'],
                $data['telefono'],
                $data['email'],
                $data['direccion'],
                $data['descripcion']
            ]);
        } catch (PDOException $e) {
            return false;
        }
    }
}
