<?php
// VentaController — ROOT, database and config are loaded by the caller
class VentaController {

    public function listar(array $filtros = []): array {
        $pdo = db();
        $sql = "SELECT v.*, c.nombre AS cliente_nombre,
                       (SELECT GROUP_CONCAT(CONCAT(p.codigo, ' ', p.nombre) SEPARATOR ', ') 
                        FROM detalle_ventas dv 
                        JOIN productos p ON dv.producto_id = p.id 
                        WHERE dv.venta_id = v.id) AS productos_comprados
                FROM ventas v
                LEFT JOIN clientes c ON v.cliente_id = c.id
                WHERE 1=1";
        $params = [];

        if (!empty($filtros['f_desde'])) {
            $sql .= " AND v.fecha >= ?";
            $params[] = $filtros['f_desde'];
        }
        if (!empty($filtros['f_hasta'])) {
            $sql .= " AND v.fecha <= ?";
            $params[] = $filtros['f_hasta'];
        }
        if (!empty($filtros['estado'])) {
            $sql .= " AND v.estado = ?";
            $params[] = $filtros['estado'];
        }

        $sql .= " ORDER BY v.created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getById(int $id): ?array {
        $pdo  = db();
        $stmt = $pdo->prepare("SELECT v.*, c.nombre AS cliente_nombre, c.documento AS cliente_doc
                FROM ventas v LEFT JOIN clientes c ON v.cliente_id = c.id
                WHERE v.id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function getDetalle(int $venta_id): array {
        $pdo  = db();
        $stmt = $pdo->prepare("SELECT dv.*, p.nombre AS producto_nombre, p.codigo
                FROM detalle_ventas dv
                JOIN productos p ON dv.producto_id = p.id
                WHERE dv.venta_id = ?");
        $stmt->execute([$venta_id]);
        return $stmt->fetchAll();
    }

    public function crear(array $data, array $items): int|false {
        $pdo = db();
        try {
            $pdo->beginTransaction();

            $tipoPago  = $data['tipo_pago'] ?? 'contado';
            $esCredito = ($tipoPago === 'credito');
            $estado    = $esCredito ? 'credito' : 'completada';

            // Credit requires a client
            if ($esCredito && empty($data['cliente_id'])) {
                throw new Exception("Las ventas a crédito requieren un cliente.");
            }

            $numero    = generarNumero('V', 'ventas', 'numero_venta');
            $subtotal  = 0;
            foreach ($items as $item) {
                $subtotal += $item['price'] * $item['qty'];
            }
            $aplicarIva = !empty($data['aplicar_iva']);
            $iva   = $aplicarIva ? $subtotal * IVA_RATE : 0;
            $total = $subtotal + $iva;

            $moneda    = in_array($data['moneda'] ?? '', ['USD','BS']) ? $data['moneda'] : 'USD';
            $metodoPago = $esCredito ? 'credito' : ($data['metodo_pago'] ?? 'efectivo_usd');

            $stmt = $pdo->prepare("INSERT INTO ventas (numero_venta, cliente_id, fecha, subtotal, iva, total, estado, moneda, metodo_pago, observaciones)
                VALUES (?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([
                $numero, $data['cliente_id'] ?: null, date('Y-m-d'),
                $subtotal, $iva, $total, $estado, $moneda, $metodoPago, $data['observaciones'] ?? ''
            ]);
            $ventaId = (int)$pdo->lastInsertId();

            $stmtDet = $pdo->prepare("INSERT INTO detalle_ventas (venta_id, producto_id, cantidad, precio_unitario, subtotal) VALUES (?,?,?,?,?)");
            $stmtUpd = $pdo->prepare("UPDATE productos SET stock = stock - ? WHERE id = ? AND stock >= ?");

            foreach ($items as $item) {
                $line = $item['price'] * $item['qty'];
                $stmtDet->execute([$ventaId, $item['id'], $item['qty'], $item['price'], $line]);
                $stmtUpd->execute([$item['qty'], $item['id'], $item['qty']]);
                if ($stmtUpd->rowCount() === 0) throw new Exception("Stock insuficiente para producto ID: {$item['id']}");
            }

            // Auto-generate invoice (always, even for credit sales — merchandise is delivered)
            $numFac = generarNumero('F', 'facturas_venta', 'numero_factura');
            $tokenFac = bin2hex(random_bytes(24));
            $stmtFac = $pdo->prepare("INSERT INTO facturas_venta (numero_factura, venta_id, token, fecha, subtotal, iva, total) VALUES (?,?,?,?,?,?,?)");
            $stmtFac->execute([$numFac, $ventaId, $tokenFac, date('Y-m-d'), $subtotal, $iva, $total]);

            // Create credit record if applicable
            if ($esCredito) {
                $stmtCred = $pdo->prepare("INSERT INTO creditos (venta_id, cliente_id, monto_total, monto_pendiente, fecha_limite, observaciones) VALUES (?,?,?,?,?,?)");
                $stmtCred->execute([
                    $ventaId,
                    (int)$data['cliente_id'],
                    $total,
                    $total,
                    !empty($data['fecha_limite']) ? $data['fecha_limite'] : null,
                    ''
                ]);
            }

            $pdo->commit();
            return $ventaId;
        } catch (Exception $e) {
            $pdo->rollBack();
            return false;
        }
    }

    public function anular(int $id): bool {
        $pdo = db();
        try {
            $pdo->beginTransaction();
            $items = $this->getDetalle($id);
            $stmtUpd = $pdo->prepare("UPDATE productos SET stock = stock + ? WHERE id = ?");
            foreach ($items as $item) {
                $stmtUpd->execute([$item['cantidad'], $item['producto_id']]);
            }
            $pdo->prepare("UPDATE ventas SET estado='anulada' WHERE id=?")->execute([$id]);
            $pdo->commit();
            return true;
        } catch (Exception $e) {
            $pdo->rollBack();
            return false;
        }
    }

    public function getClientes(): array {
        return db()->query("SELECT id, nombre, documento FROM clientes WHERE activo=1 ORDER BY nombre")->fetchAll();
    }

    public function estadisticasHoy(): array {
        $pdo = db();
        $hoy = date('Y-m-d');
        $stmt = $pdo->prepare("SELECT COUNT(*) AS total_ventas, COALESCE(SUM(total),0) AS monto FROM ventas WHERE fecha=? AND estado='completada'");
        $stmt->execute([$hoy]);
        return $stmt->fetch();
    }

    public function totalMes(): float {
        $pdo = db();
        $mes = date('Y-m');
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(total),0) FROM ventas WHERE DATE_FORMAT(fecha,'%Y-%m')=? AND estado='completada'");
        $stmt->execute([$mes]);
        return (float)$stmt->fetchColumn();
    }

    public function totalVentasMes(): int {
        $pdo = db();
        $mes = date('Y-m');
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM ventas WHERE DATE_FORMAT(fecha,'%Y-%m')=? AND estado='completada'");
        $stmt->execute([$mes]);
        return (int)$stmt->fetchColumn();
    }

    public function ventasPorMes(int $meses = 6): array {
        $pdo = db();
        $stmt = $pdo->prepare("SELECT DATE_FORMAT(fecha,'%Y-%m') AS mes,
                COUNT(*) AS total_ventas,
                COALESCE(SUM(total),0) AS monto
                FROM ventas
                WHERE estado='completada' AND fecha >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
                GROUP BY mes
                ORDER BY mes ASC");
        $stmt->execute([$meses]);
        return $stmt->fetchAll();
    }

    public function topProductosVendidos(int $limit = 5): array {
        $pdo = db();
        $mes = date('Y-m');
        $stmt = $pdo->prepare("SELECT p.nombre, p.codigo, SUM(dv.cantidad) AS total_vendido, SUM(dv.subtotal) AS total_monto
                FROM detalle_ventas dv
                JOIN productos p ON dv.producto_id = p.id
                JOIN ventas v ON dv.venta_id = v.id
                WHERE v.estado='completada' AND DATE_FORMAT(v.fecha,'%Y-%m')=?
                GROUP BY p.id, p.nombre, p.codigo
                ORDER BY total_vendido DESC
                LIMIT ?");
        $stmt->execute([$mes, $limit]);
        return $stmt->fetchAll();
    }
}
