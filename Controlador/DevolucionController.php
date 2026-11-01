<?php
// Controlador/DevolucionController.php

class DevolucionController {

    public function listar(array $filtros = []): array {
        $pdo = db();
        $sql = "SELECT d.*, v.numero_venta, c.nombre AS cliente_nombre, c.documento AS cliente_doc,
                       (SELECT COUNT(*) FROM detalle_devoluciones dd WHERE dd.devolucion_id = d.id) AS total_items
                FROM devoluciones d
                JOIN ventas v ON d.venta_id = v.id
                LEFT JOIN clientes c ON d.cliente_id = c.id
                WHERE 1=1";
        $params = [];

        if (!empty($filtros['f_desde'])) {
            $sql .= " AND d.fecha >= ?";
            $params[] = $filtros['f_desde'];
        }
        if (!empty($filtros['f_hasta'])) {
            $sql .= " AND d.fecha <= ?";
            $params[] = $filtros['f_hasta'];
        }
        if (!empty($filtros['resolucion'])) {
            $sql .= " AND d.tipo_resolucion = ?";
            $params[] = $filtros['resolucion'];
        }

        $sql .= " ORDER BY d.created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById(int $id): ?array {
        $pdo = db();
        $stmt = $pdo->prepare("SELECT d.*, v.numero_venta, v.fecha AS fecha_venta, v.total AS total_venta,
                                      c.nombre AS cliente_nombre, c.documento AS cliente_doc, c.telefono AS cliente_tel,
                                      u.nombre AS usuario_nombre
                               FROM devoluciones d
                               JOIN ventas v ON d.venta_id = v.id
                               LEFT JOIN clientes c ON d.cliente_id = c.id
                               LEFT JOIN usuarios u ON d.usuario_id = u.id
                               WHERE d.id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getDetalle(int $devolucion_id): array {
        $pdo = db();
        $stmt = $pdo->prepare("SELECT dd.*, p.nombre AS producto_nombre, p.codigo AS producto_codigo, p.marca
                               FROM detalle_devoluciones dd
                               JOIN productos p ON dd.producto_id = p.id
                               WHERE dd.devolucion_id = ?");
        $stmt->execute([$devolucion_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener los productos de una venta con las cantidades ya devueltas previamente
     */
    public function getItemsDisponiblesParaDevolucion(int $venta_id): array {
        $pdo = db();
        $sql = "SELECT dv.id AS detalle_venta_id, dv.producto_id, dv.cantidad AS cantidad_vendida,
                       dv.precio_unitario, dv.subtotal,
                       p.nombre AS producto_nombre, p.codigo AS producto_codigo, p.marca, p.stock AS stock_actual,
                       COALESCE((
                           SELECT SUM(dd.cantidad)
                           FROM detalle_devoluciones dd
                           JOIN devoluciones d ON dd.devolucion_id = d.id
                           WHERE d.venta_id = dv.venta_id 
                             AND dd.producto_id = dv.producto_id 
                             AND d.estado != 'anulada'
                       ), 0) AS cantidad_ya_devuelta
                FROM detalle_ventas dv
                JOIN productos p ON dv.producto_id = p.id
                WHERE dv.venta_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$venta_id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($items as &$item) {
            $item['cantidad_disponible'] = max(0, (int)$item['cantidad_vendida'] - (int)$item['cantidad_ya_devuelta']);
        }
        return $items;
    }

    /**
     * Procesar una nueva devolución / garantía
     */
    public function crear(array $data, array $items): int|false {
        $pdo = db();
        try {
            $pdo->beginTransaction();

            // 1. Generar número de devolución consecutivo
            $prefix = 'DEV-' . date('Ym') . '-';
            $stmt = $pdo->prepare("SELECT numero_devolucion FROM devoluciones WHERE numero_devolucion LIKE ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$prefix . '%']);
            $last = $stmt->fetchColumn();
            if ($last) {
                $num = (int)substr($last, strlen($prefix)) + 1;
            } else {
                $num = 1;
            }
            $numeroDevolucion = $prefix . str_pad($num, 4, '0', STR_PAD_LEFT);

            // 2. Insertar cabecera de devolución
            $stmt = $pdo->prepare("INSERT INTO devoluciones 
                (numero_devolucion, venta_id, cliente_id, usuario_id, fecha, tipo_resolucion, estado, monto_total, motivo, observaciones) 
                VALUES (?, ?, ?, ?, ?, ?, 'procesada', ?, ?, ?)");

            $stmt->execute([
                $numeroDevolucion,
                $data['venta_id'],
                $data['cliente_id'] ?: null,
                $_SESSION['user']['id'] ?? null,
                $data['fecha'] ?? date('Y-m-d'),
                $data['tipo_resolucion'] ?? 'nota_credito',
                $data['monto_total'] ?? 0.00,
                $data['motivo'],
                $data['observaciones'] ?? null
            ]);
            $devolucion_id = (int)$pdo->lastInsertId();

            // 3. Insertar detalle y manejar stock si corresponde
            $stmtItem = $pdo->prepare("INSERT INTO detalle_devoluciones 
                (devolucion_id, producto_id, cantidad, precio_unitario, subtotal, accion_stock, motivo_item) 
                VALUES (?, ?, ?, ?, ?, ?, ?)");

            $stmtStock = $pdo->prepare("UPDATE productos SET stock = stock + ? WHERE id = ?");

            foreach ($items as $item) {
                $cant = (int)$item['cantidad'];
                if ($cant <= 0) continue;

                $precio = (float)$item['precio_unitario'];
                $subtotal = $cant * $precio;
                $accion = $item['accion_stock'] ?? 'reingresar_stock';

                $stmtItem->execute([
                    $devolucion_id,
                    $item['producto_id'],
                    $cant,
                    $precio,
                    $subtotal,
                    $accion,
                    $item['motivo_item'] ?? null
                ]);

                // Si la acción es reingresar al inventario apto para la venta
                if ($accion === 'reingresar_stock') {
                    $stmtStock->execute([$cant, $item['producto_id']]);
                }
            }

            $pdo->commit();
            return $devolucion_id;
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Error al crear devolución: " . $e->getMessage());
            return false;
        }
    }
}
