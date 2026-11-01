<?php
// Controlador/CreditoController.php
class CreditoController {

    /**
     * Lista todos los créditos con datos de venta y cliente.
     */
    public function listar(array $filtros = []): array {
        $pdo = db();
        $sql = "SELECT cr.*, v.numero_venta, v.fecha AS fecha_venta, v.total AS venta_total,
                       c.nombre AS cliente_nombre, c.documento AS cliente_doc, c.telefono AS cliente_tel
                FROM creditos cr
                JOIN ventas v ON cr.venta_id = v.id
                JOIN clientes c ON cr.cliente_id = c.id
                WHERE 1=1";
        $params = [];

        if (!empty($filtros['estado'])) {
            $sql .= " AND cr.estado = ?";
            $params[] = $filtros['estado'];
        }
        if (!empty($filtros['cliente_id'])) {
            $sql .= " AND cr.cliente_id = ?";
            $params[] = (int)$filtros['cliente_id'];
        }
        if (!empty($filtros['f_desde'])) {
            $sql .= " AND v.fecha >= ?";
            $params[] = $filtros['f_desde'];
        }
        if (!empty($filtros['f_hasta'])) {
            $sql .= " AND v.fecha <= ?";
            $params[] = $filtros['f_hasta'];
        }

        $sql .= " ORDER BY cr.estado ASC, cr.created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Obtener un crédito por ID con datos completos.
     */
    public function getById(int $id): ?array {
        $pdo = db();
        $stmt = $pdo->prepare("SELECT cr.*, v.numero_venta, v.fecha AS fecha_venta, v.subtotal AS venta_subtotal,
                       v.iva AS venta_iva, v.total AS venta_total, v.observaciones AS venta_obs,
                       c.nombre AS cliente_nombre, c.documento AS cliente_doc,
                       c.telefono AS cliente_tel, c.email AS cliente_email, c.direccion AS cliente_dir
                FROM creditos cr
                JOIN ventas v ON cr.venta_id = v.id
                JOIN clientes c ON cr.cliente_id = c.id
                WHERE cr.id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Obtener un crédito por venta_id.
     */
    public function getByVentaId(int $ventaId): ?array {
        $pdo = db();
        $stmt = $pdo->prepare("SELECT cr.*, c.nombre AS cliente_nombre, c.documento AS cliente_doc
                FROM creditos cr
                JOIN clientes c ON cr.cliente_id = c.id
                WHERE cr.venta_id = ?");
        $stmt->execute([$ventaId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Obtener abonos de un crédito.
     */
    public function getAbonos(int $creditoId): array {
        $pdo = db();
        $stmt = $pdo->prepare("SELECT * FROM abonos_credito WHERE credito_id = ? ORDER BY fecha DESC, id DESC");
        $stmt->execute([$creditoId]);
        return $stmt->fetchAll();
    }

    /**
     * Registrar un abono parcial o total.
     * Actualiza montos del crédito y cambia estado de venta si se paga todo.
     */
    public function registrarAbono(int $creditoId, array $data): bool {
        $pdo = db();
        try {
            $pdo->beginTransaction();

            // Get current credit
            $stmt = $pdo->prepare("SELECT * FROM creditos WHERE id = ? FOR UPDATE");
            $stmt->execute([$creditoId]);
            $credito = $stmt->fetch();
            if (!$credito) throw new Exception("Crédito no encontrado");

            $monto = min((float)$data['monto'], (float)$credito['monto_pendiente']);
            if ($monto <= 0) throw new Exception("Monto inválido");

            // Insert abono
            $stmtAbono = $pdo->prepare("INSERT INTO abonos_credito (credito_id, monto, metodo_pago, moneda, referencia, observaciones, fecha) VALUES (?,?,?,?,?,?,?)");
            $stmtAbono->execute([
                $creditoId,
                $monto,
                $data['metodo_pago'] ?? 'efectivo_usd',
                $data['moneda'] ?? 'USD',
                trim($data['referencia'] ?? ''),
                trim($data['observaciones'] ?? ''),
                $data['fecha'] ?? date('Y-m-d')
            ]);

            // Update credit amounts
            $nuevoPagado = (float)$credito['monto_pagado'] + $monto;
            $nuevoPendiente = (float)$credito['monto_total'] - $nuevoPagado;
            $nuevoEstado = $nuevoPendiente <= 0.005 ? 'pagado' : 'parcial';

            $stmtUpd = $pdo->prepare("UPDATE creditos SET monto_pagado=?, monto_pendiente=?, estado=? WHERE id=?");
            $stmtUpd->execute([
                round($nuevoPagado, 2),
                round(max(0, $nuevoPendiente), 2),
                $nuevoEstado,
                $creditoId
            ]);

            // If fully paid, update venta estado to 'completada'
            if ($nuevoEstado === 'pagado') {
                $pdo->prepare("UPDATE ventas SET estado='completada' WHERE id=?")->execute([$credito['venta_id']]);
            }

            $pdo->commit();
            return true;
        } catch (Exception $e) {
            $pdo->rollBack();
            return false;
        }
    }

    /**
     * Crear un registro de crédito (llamado desde VentaController::crear).
     */
    public function crear(int $ventaId, int $clienteId, float $montoTotal, ?string $fechaLimite = null, string $obs = ''): bool {
        $pdo = db();
        $stmt = $pdo->prepare("INSERT INTO creditos (venta_id, cliente_id, monto_total, monto_pendiente, fecha_limite, observaciones) VALUES (?,?,?,?,?,?)");
        return $stmt->execute([$ventaId, $clienteId, $montoTotal, $montoTotal, $fechaLimite ?: null, $obs]);
    }

    /**
     * Resumen de créditos para el dashboard.
     */
    public function resumen(): array {
        $pdo = db();

        $totalPorCobrar = (float)$pdo->query("SELECT COALESCE(SUM(monto_pendiente),0) FROM creditos WHERE estado IN('pendiente','parcial')")->fetchColumn();
        $creditosActivos = (int)$pdo->query("SELECT COUNT(*) FROM creditos WHERE estado IN('pendiente','parcial')")->fetchColumn();
        $clientesConDeuda = (int)$pdo->query("SELECT COUNT(DISTINCT cliente_id) FROM creditos WHERE estado IN('pendiente','parcial')")->fetchColumn();

        $mes = date('Y-m');
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(monto),0) FROM abonos_credito WHERE DATE_FORMAT(fecha,'%Y-%m')=?");
        $stmt->execute([$mes]);
        $cobradoMes = (float)$stmt->fetchColumn();

        return [
            'total_por_cobrar'  => $totalPorCobrar,
            'creditos_activos'  => $creditosActivos,
            'clientes_con_deuda'=> $clientesConDeuda,
            'cobrado_mes'       => $cobradoMes,
        ];
    }

    /**
     * Créditos de un cliente específico.
     */
    public function getCreditosCliente(int $clienteId): array {
        $pdo = db();
        $stmt = $pdo->prepare("SELECT cr.*, v.numero_venta, v.fecha AS fecha_venta
                FROM creditos cr JOIN ventas v ON cr.venta_id = v.id
                WHERE cr.cliente_id = ?
                ORDER BY cr.estado ASC, cr.created_at DESC");
        $stmt->execute([$clienteId]);
        return $stmt->fetchAll();
    }

    /**
     * Total créditos por cobrar (for dashboard stat card).
     */
    public function totalPorCobrar(): float {
        return (float)db()->query("SELECT COALESCE(SUM(monto_pendiente),0) FROM creditos WHERE estado IN('pendiente','parcial')")->fetchColumn();
    }
}
