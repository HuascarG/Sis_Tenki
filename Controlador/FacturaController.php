<?php
// FacturaController — ROOT, database and config are loaded by the caller
class FacturaController {

    public function listar(array $filtros = []): array {
        $pdo = db();
        $sql = "SELECT fv.*, v.numero_venta, v.estado AS estado_venta, c.nombre AS cliente_nombre, c.documento AS cliente_doc,
                       (SELECT GROUP_CONCAT(CONCAT(p.codigo, ' ', p.nombre) SEPARATOR ', ') 
                        FROM detalle_ventas dv 
                        JOIN productos p ON dv.producto_id = p.id 
                        WHERE dv.venta_id = v.id) AS productos_comprados
                FROM facturas_venta fv
                JOIN ventas v ON fv.venta_id = v.id
                LEFT JOIN clientes c ON v.cliente_id = c.id
                WHERE 1=1";
        $params = [];

        if (!empty($filtros['f_desde'])) {
            $sql .= " AND fv.fecha >= ?";
            $params[] = $filtros['f_desde'];
        }
        if (!empty($filtros['f_hasta'])) {
            $sql .= " AND fv.fecha <= ?";
            $params[] = $filtros['f_hasta'];
        }

        $sql .= " ORDER BY fv.created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getById(int $id): ?array {
        $pdo  = db();
        $stmt = $pdo->prepare("SELECT fv.*, v.numero_venta, v.observaciones,
                c.nombre AS cliente_nombre, c.documento AS cliente_doc, c.telefono AS cliente_tel, c.direccion AS cliente_dir
                FROM facturas_venta fv
                JOIN ventas v ON fv.venta_id = v.id
                LEFT JOIN clientes c ON v.cliente_id = c.id
                WHERE fv.id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function getByToken(string $token): ?array {
        $pdo  = db();
        $stmt = $pdo->prepare("SELECT fv.*, v.numero_venta, v.observaciones,
                c.nombre AS cliente_nombre, c.documento AS cliente_doc, c.telefono AS cliente_tel, c.direccion AS cliente_dir
                FROM facturas_venta fv
                JOIN ventas v ON fv.venta_id = v.id
                LEFT JOIN clientes c ON v.cliente_id = c.id
                WHERE fv.token = ?");
        $stmt->execute([$token]);
        return $stmt->fetch() ?: null;
    }

    public function getDetalleByVenta(int $venta_id): array {
        $pdo  = db();
        $stmt = $pdo->prepare("SELECT dv.*, p.nombre AS producto_nombre, p.codigo
                FROM detalle_ventas dv
                JOIN productos p ON dv.producto_id = p.id
                WHERE dv.venta_id = ?");
        $stmt->execute([$venta_id]);
        return $stmt->fetchAll();
    }

    public function totalMes(): float {
        $pdo  = db();
        $mes  = date('Y-m');
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(fv.total),0) 
                               FROM facturas_venta fv 
                               JOIN ventas v ON fv.venta_id = v.id 
                               WHERE DATE_FORMAT(fv.fecha,'%Y-%m')=? AND v.estado='completada'");
        $stmt->execute([$mes]);
        return (float)$stmt->fetchColumn();
    }

    public function totalFacturasMes(): int {
        $pdo = db();
        $mes = date('Y-m');
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM facturas_venta fv
                JOIN ventas v ON fv.venta_id = v.id
                WHERE DATE_FORMAT(fv.fecha,'%Y-%m')=? AND v.estado='completada'");
        $stmt->execute([$mes]);
        return (int)$stmt->fetchColumn();
    }

    public function facturacionPorMes(int $meses = 12): array {
        $pdo = db();
        $stmt = $pdo->prepare("SELECT DATE_FORMAT(fv.fecha,'%Y-%m') AS mes,
                COUNT(*) AS total_facturas,
                COALESCE(SUM(fv.subtotal),0) AS subtotal,
                COALESCE(SUM(fv.iva),0) AS iva,
                COALESCE(SUM(fv.total),0) AS total
                FROM facturas_venta fv
                JOIN ventas v ON fv.venta_id = v.id
                WHERE v.estado='completada' AND fv.fecha >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
                GROUP BY mes
                ORDER BY mes DESC");
        $stmt->execute([$meses]);
        return $stmt->fetchAll();
    }
}
