<?php
// InventarioController — ROOT, database and config are loaded by the caller
class InventarioController {

    /**
     * Sube una foto de producto de manera segura (validando firma binaria y tipo MIME real).
     */
    public function subirFoto(array $file): ?string {
        if (empty($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) return null;
        if ($file['size'] > 2 * 1024 * 1024) return null; // 2MB max

        // 1. Validar extensión en lista blanca estricta
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($ext, $allowedExts, true)) return null;

        // 2. Validar tipo MIME real por inspección binaria (finfo)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $realMime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($realMime, $allowedMimes, true)) return null;

        // 3. Verificar que sea una imagen válida procesable
        if (!@getimagesize($file['tmp_name'])) return null;

        // 4. Generar nombre criptográficamente seguro
        $newName = 'prod_' . bin2hex(random_bytes(16)) . '.' . $ext;
        $destDir = ROOT . '/Assets/uploads/productos/';
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }
        $destPath = $destDir . $newName;

        if (move_uploaded_file($file['tmp_name'], $destPath)) {
            @chmod($destPath, 0644); // Permiso de lectura/escritura únicamente (sin ejecución)
            return 'Assets/uploads/productos/' . $newName;
        }
        return null;
    }

    public function listar(): array {
        $pdo = db();
        $sql = "SELECT p.*, c.nombre AS categoria, pr.nombre AS proveedor
                FROM productos p
                LEFT JOIN categorias c ON p.categoria_id = c.id
                LEFT JOIN proveedores pr ON p.proveedor_id = pr.id
                WHERE p.activo = 1
                ORDER BY p.nombre ASC";
        return $pdo->query($sql)->fetchAll();
    }

    public function buscar(string $q): array {
        $pdo = db();
        $sql = "SELECT p.*, c.nombre AS categoria
                FROM productos p
                LEFT JOIN categorias c ON p.categoria_id = c.id
                WHERE p.activo = 1 AND (p.nombre LIKE ? OR p.codigo LIKE ? OR c.nombre LIKE ?)
                ORDER BY p.nombre";
        $term = "%{$q}%";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$term, $term, $term]);
        return $stmt->fetchAll();
    }

    public function getById(int $id): ?array {
        $pdo  = db();
        $stmt = $pdo->prepare("SELECT * FROM productos WHERE id = ? AND activo = 1");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function crear(array $data): bool|string {
        try {
            $pdo = db();

            $codigo = !empty($data['codigo']) ? trim($data['codigo']) : null;

            // Verificar código duplicado solo si fue proporcionado
            if ($codigo !== null) {
                $check = $pdo->prepare("SELECT id FROM productos WHERE codigo = ? AND activo = 1 LIMIT 1");
                $check->execute([$codigo]);
                if ($check->fetch()) {
                    return 'Ya existe un producto con el código "' . $codigo . '". Usa un código diferente.';
                }
            }

            $sql = "INSERT INTO productos (codigo, nombre, descripcion, categoria_id, proveedor_id,
                        precio_compra, precio_venta, stock, stock_minimo, unidad, marca, condicion, ubicacion, foto)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
            $stmt = $pdo->prepare($sql);
            return $stmt->execute([
                $codigo, $data['nombre'], $data['descripcion'] ?? '',
                $data['categoria_id'] ?: null, $data['proveedor_id'] ?: null,
                $data['precio_compra'], $data['precio_venta'],
                $data['stock'], $data['stock_minimo'] ?? 5, $data['unidad'] ?? 'unidad',
                $data['marca'] ?? null, $data['condicion'] ?? 'nuevo',
                $data['ubicacion'] ?? null,
                $data['foto'] ?? null
            ]);
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                return 'Error de duplicado: el código o algún dato único ya existe en el sistema.';
            }
            error_log('[TENKI Inventario] Error al crear producto: ' . $e->getMessage());
            return 'Error inesperado al guardar el producto. Intente de nuevo.';
        }
    }

    public function actualizar(int $id, array $data): bool {
        try {
            $pdo = db();
            $codigo = !empty($data['codigo']) ? trim($data['codigo']) : null;
            // Build query dynamically: only update foto if provided
            $fields = "codigo=?, nombre=?, descripcion=?, categoria_id=?, proveedor_id=?,
                        precio_compra=?, precio_venta=?, stock=?, stock_minimo=?, unidad=?, marca=?, condicion=?, ubicacion=?";
            $params = [
                $codigo, $data['nombre'], $data['descripcion'] ?? '',
                $data['categoria_id'] ?: null, $data['proveedor_id'] ?: null,
                $data['precio_compra'], $data['precio_venta'],
                $data['stock'], $data['stock_minimo'] ?? 5, $data['unidad'] ?? 'unidad',
                $data['marca'] ?? null, $data['condicion'] ?? 'nuevo',
                $data['ubicacion'] ?? null,
            ];
            if (isset($data['foto'])) {
                $fields .= ", foto=?";
                $params[] = $data['foto'];
            }
            $params[] = $id;
            $sql = "UPDATE productos SET {$fields} WHERE id=?";
            $stmt = $pdo->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function eliminar(int $id): bool {
        try {
            $pdo  = db();
            $stmt = $pdo->prepare("DELETE FROM productos WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function getCategorias(): array {
        return db()->query("SELECT id, nombre FROM categorias ORDER BY nombre")->fetchAll();
    }

    public function getProveedores(): array {
        return db()->query("SELECT id, nombre FROM proveedores WHERE activo = 1 ORDER BY nombre")->fetchAll();
    }

    public function getStockBajo(): array {
        $pdo = db();
        return $pdo->query("SELECT p.*, c.nombre AS categoria
                FROM productos p LEFT JOIN categorias c ON p.categoria_id = c.id
                WHERE p.activo = 1 AND p.stock <= p.stock_minimo
                ORDER BY p.stock ASC")->fetchAll();
    }

    public function totalProductos(): int {
        return (int)db()->query("SELECT COUNT(*) FROM productos WHERE activo=1")->fetchColumn();
    }

    public function valorInventario(): float {
        return (float)db()->query("SELECT COALESCE(SUM(stock * precio_venta), 0) FROM productos WHERE activo=1")->fetchColumn();
    }

    public function productosPorCategoria(): array {
        return db()->query("SELECT c.nombre AS categoria, COUNT(p.id) AS total
                FROM productos p
                LEFT JOIN categorias c ON p.categoria_id = c.id
                WHERE p.activo = 1
                GROUP BY c.nombre
                ORDER BY total DESC
                LIMIT 6")->fetchAll();
    }
}
