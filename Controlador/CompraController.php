<?php
// CompraController — ROOT, database and config are loaded by the caller
class CompraController {

    public function listar(): array {
        $pdo = db();
        return $pdo->query("SELECT c.*, p.nombre AS proveedor_nombre
                FROM compras c LEFT JOIN proveedores p ON c.proveedor_id = p.id
                ORDER BY c.created_at DESC")->fetchAll();
    }

    public function getById(int $id): ?array {
        $pdo  = db();
        $stmt = $pdo->prepare("SELECT c.*, p.nombre AS proveedor_nombre, p.rif AS proveedor_rif
                FROM compras c LEFT JOIN proveedores p ON c.proveedor_id = p.id
                WHERE c.id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function getDetalle(int $compra_id): array {
        $pdo  = db();
        $stmt = $pdo->prepare("SELECT dc.*, pr.nombre AS producto_nombre, pr.codigo
                FROM detalle_compras dc
                JOIN productos pr ON dc.producto_id = pr.id
                WHERE dc.compra_id = ?");
        $stmt->execute([$compra_id]);
        return $stmt->fetchAll();
    }

    public function crear(array $data, array $items): int|false {
        $pdo = db();
        try {
            $pdo->beginTransaction();

            $numero   = generarNumero('C', 'compras', 'numero_compra');
            $subtotal = 0;
            foreach ($items as $item) $subtotal += $item['price'] * $item['qty'];
            $iva   = $subtotal * IVA_RATE;
            $total = $subtotal + $iva;

            $stmt = $pdo->prepare("INSERT INTO compras (numero_compra, proveedor_id, numero_factura_proveedor, fecha, subtotal, iva, total, observaciones)
                VALUES (?,?,?,?,?,?,?,?)");
            $stmt->execute([
                $numero, $data['proveedor_id'] ?: null,
                $data['numero_factura_proveedor'] ?? '',
                $data['fecha'] ?? date('Y-m-d'),
                $subtotal, $iva, $total, $data['observaciones'] ?? ''
            ]);
            $compraId = (int)$pdo->lastInsertId();

            $stmtDet = $pdo->prepare("INSERT INTO detalle_compras (compra_id, producto_id, cantidad, precio_unitario, subtotal) VALUES (?,?,?,?,?)");
            $stmtStock = $pdo->prepare("UPDATE productos SET stock = stock + ?, precio_compra = ? WHERE id = ?");

            foreach ($items as $item) {
                $line = $item['price'] * $item['qty'];
                $stmtDet->execute([$compraId, $item['id'], $item['qty'], $item['price'], $line]);
                $stmtStock->execute([$item['qty'], $item['price'], $item['id']]);
            }

            $pdo->commit();
            return $compraId;
        } catch (Exception $e) {
            $pdo->rollBack();
            return false;
        }
    }

    public function getProveedores(): array {
        return db()->query("SELECT id, nombre, rif FROM proveedores WHERE activo=1 ORDER BY nombre")->fetchAll();
    }

    public function actualizar(int $id, array $data): bool {
        try {
            $pdo  = db();
            $stmt = $pdo->prepare(
                "UPDATE compras SET proveedor_id=?, numero_factura_proveedor=?, fecha=?, observaciones=? WHERE id=?"
            );
            return $stmt->execute([
                $data['proveedor_id'] ?: null,
                trim($data['numero_factura_proveedor'] ?? ''),
                $data['fecha'] ?? date('Y-m-d'),
                trim($data['observaciones'] ?? ''),
                $id,
            ]);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function anular(int $id): bool {
        $pdo = db();
        try {
            $pdo->beginTransaction();

            // Revertir stock de los productos recibidos
            $items   = $this->getDetalle($id);
            $stmtUpd = $pdo->prepare("UPDATE productos SET stock = stock - ? WHERE id = ? AND stock >= ?");
            foreach ($items as $item) {
                $stmtUpd->execute([$item['cantidad'], $item['producto_id'], $item['cantidad']]);
            }

            // Marcar compra como anulada
            $pdo->prepare("UPDATE compras SET observaciones = CONCAT('[ANULADA] ', COALESCE(observaciones,'')) WHERE id=?")
                ->execute([$id]);

            $pdo->commit();
            return true;
        } catch (Exception $e) {
            $pdo->rollBack();
            return false;
        }
    }

    public function totalMes(): float {
        $pdo  = db();
        $mes  = date('Y-m');
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(total),0) FROM compras WHERE DATE_FORMAT(fecha,'%Y-%m')=?");
        $stmt->execute([$mes]);
        return (float)$stmt->fetchColumn();
    }
}
