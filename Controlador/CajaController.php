<?php
// Controlador/CajaController.php

class CajaController {

    /**
     * Retorna la caja activa actualmente abierta, o null si no hay ninguna.
     */
    public function getCajaAbierta(): ?array {
        $pdo = db();
        $stmt = $pdo->query("SELECT c.*, u.nombre AS usuario_apertura
                             FROM cajas c
                             LEFT JOIN usuarios u ON c.usuario_apertura_id = u.id
                             WHERE c.estado = 'abierta'
                             ORDER BY c.id DESC LIMIT 1");
        return $stmt->fetch() ?: null;
    }

    /**
     * Abre un nuevo turno de caja.
     */
    public function abrirCaja(float $montoInicialUsd, float $montoInicialBs, ?string $observaciones, int $usuarioId): bool|string {
        $pdo = db();
        // Verificar si ya hay una caja abierta
        if ($this->getCajaAbierta() !== null) {
            return 'Ya existe un turno de caja abierto en el sistema.';
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO cajas (usuario_apertura_id, fecha_apertura, monto_inicial_usd, monto_inicial_bs, estado, observaciones)
                                   VALUES (?, NOW(), ?, ?, 'abierta', ?)");
            $stmt->execute([
                $usuarioId,
                max(0, $montoInicialUsd),
                max(0, $montoInicialBs),
                trim($observaciones ?? '')
            ]);
            return true;
        } catch (PDOException $e) {
            error_log('[TENKI Caja] Error al abrir caja: ' . $e->getMessage());
            return 'Error inesperado al abrir la caja. Intente nuevamente.';
        }
    }

    /**
     * Obtiene los cálculos en tiempo real de ingresos, egresos y totales de una caja.
     */
    public function getResumenCaja(int $cajaId): array {
        $pdo = db();
        $stmt = $pdo->prepare("SELECT c.*, ua.nombre AS usuario_apertura, uc.nombre AS usuario_cierre
                               FROM cajas c
                               LEFT JOIN usuarios ua ON c.usuario_apertura_id = ua.id
                               LEFT JOIN usuarios uc ON c.usuario_cierre_id = uc.id
                               WHERE c.id = ?");
        $stmt->execute([$cajaId]);
        $caja = $stmt->fetch();
        if (!$caja) return [];

        $fechaApertura = $caja['fecha_apertura'];
        $fechaCierre   = $caja['fecha_cierre'] ?: date('Y-m-d H:i:s');

        // 1. VENTAS DURANTE EL TURNO (Completadas)
        // Ventas Efectivo USD
        $sqlVentasEfecUsd = "SELECT COALESCE(SUM(total), 0) FROM ventas
                             WHERE estado = 'completada'
                             AND (caja_id = ? OR (caja_id IS NULL AND created_at >= ? AND created_at <= ?))
                             AND metodo_pago IN ('efectivo_usd', 'efectivo')";
        $stmt = $pdo->prepare($sqlVentasEfecUsd);
        $stmt->execute([$cajaId, $fechaApertura, $fechaCierre]);
        $ventasEfecUsd = (float)$stmt->fetchColumn();

        // Ventas Efectivo Bs
        $sqlVentasEfecBs = "SELECT COALESCE(SUM(total), 0) FROM ventas
                            WHERE estado = 'completada'
                            AND (caja_id = ? OR (caja_id IS NULL AND created_at >= ? AND created_at <= ?))
                            AND metodo_pago = 'efectivo_bs'";
        $stmt = $pdo->prepare($sqlVentasEfecBs);
        $stmt->execute([$cajaId, $fechaApertura, $fechaCierre]);
        $ventasEfecBs = (float)$stmt->fetchColumn();

        // Ventas Digitales USD (Zelle, Binance, Zinli)
        $sqlVentasDigUsd = "SELECT COALESCE(SUM(total), 0) FROM ventas
                            WHERE estado = 'completada'
                            AND (caja_id = ? OR (caja_id IS NULL AND created_at >= ? AND created_at <= ?))
                            AND metodo_pago IN ('zelle', 'binance', 'zinli')";
        $stmt = $pdo->prepare($sqlVentasDigUsd);
        $stmt->execute([$cajaId, $fechaApertura, $fechaCierre]);
        $ventasDigUsd = (float)$stmt->fetchColumn();

        // Ventas Digitales Bs (Punto, Pago Móvil, Transferencia)
        $sqlVentasDigBs = "SELECT COALESCE(SUM(total), 0) FROM ventas
                           WHERE estado = 'completada'
                           AND (caja_id = ? OR (caja_id IS NULL AND created_at >= ? AND created_at <= ?))
                           AND metodo_pago IN ('pago_movil', 'punto', 'transferencia')";
        $stmt = $pdo->prepare($sqlVentasDigBs);
        $stmt->execute([$cajaId, $fechaApertura, $fechaCierre]);
        $ventasDigBs = (float)$stmt->fetchColumn();

        // 2. ABONOS DE CRÉDITO COBRADOS
        $sqlAbonosEfecUsd = "SELECT COALESCE(SUM(monto), 0) FROM abonos_credito
                             WHERE created_at >= ? AND created_at <= ?
                             AND moneda = 'USD' AND metodo_pago IN ('efectivo_usd', 'efectivo')";
        $stmt = $pdo->prepare($sqlAbonosEfecUsd);
        $stmt->execute([$fechaApertura, $fechaCierre]);
        $abonosEfecUsd = (float)$stmt->fetchColumn();

        $sqlAbonosEfecBs = "SELECT COALESCE(SUM(monto), 0) FROM abonos_credito
                            WHERE created_at >= ? AND created_at <= ?
                            AND moneda = 'BS' AND metodo_pago IN ('efectivo_bs', 'efectivo')";
        $stmt = $pdo->prepare($sqlAbonosEfecBs);
        $stmt->execute([$fechaApertura, $fechaCierre]);
        $abonosEfecBs = (float)$stmt->fetchColumn();

        // 3. MOVIMIENTOS MANUALES (Ingresos extra y Egresos / Gastos)
        $sqlMovs = "SELECT tipo, moneda, COALESCE(SUM(monto), 0) as total
                    FROM movimientos_caja
                    WHERE caja_id = ?
                    GROUP BY tipo, moneda";
        $stmt = $pdo->prepare($sqlMovs);
        $stmt->execute([$cajaId]);
        $movsRows = $stmt->fetchAll();

        $ingresosExtraUsd = 0.0;
        $ingresosExtraBs  = 0.0;
        $egresosUsd       = 0.0;
        $egresosBs        = 0.0;

        foreach ($movsRows as $m) {
            if ($m['tipo'] === 'ingreso' && $m['moneda'] === 'USD') $ingresosExtraUsd = (float)$m['total'];
            if ($m['tipo'] === 'ingreso' && $m['moneda'] === 'BS')  $ingresosExtraBs  = (float)$m['total'];
            if ($m['tipo'] === 'egreso'  && $m['moneda'] === 'USD') $egresosUsd       = (float)$m['total'];
            if ($m['tipo'] === 'egreso'  && $m['moneda'] === 'BS')  $egresosBs        = (float)$m['total'];
        }

        // 4. TOTALES ESPERADOS EN GAVETA (Físico)
        $montoInicialUsd = (float)$caja['monto_inicial_usd'];
        $montoInicialBs  = (float)$caja['monto_inicial_bs'];

        $totalEsperadoUsd = $montoInicialUsd + $ventasEfecUsd + $abonosEfecUsd + $ingresosExtraUsd - $egresosUsd;
        $totalEsperadoBs  = $montoInicialBs  + $ventasEfecBs  + $abonosEfecBs  + $ingresosExtraBs  - $egresosBs;

        return [
            'caja'                => $caja,
            'monto_inicial_usd'   => $montoInicialUsd,
            'monto_inicial_bs'    => $montoInicialBs,
            'ventas_efectivo_usd' => $ventasEfecUsd,
            'ventas_efectivo_bs'  => $ventasEfecBs,
            'ventas_digital_usd'  => $ventasDigUsd,
            'ventas_digital_bs'   => $ventasDigBs,
            'abonos_efectivo_usd' => $abonosEfecUsd,
            'abonos_efectivo_bs'  => $abonosEfecBs,
            'ingresos_extra_usd'  => $ingresosExtraUsd,
            'ingresos_extra_bs'   => $ingresosExtraBs,
            'egresos_usd'         => $egresosUsd,
            'egresos_bs'          => $egresosBs,
            'total_esperado_usd'  => $totalEsperadoUsd,
            'total_esperado_bs'   => $totalEsperadoBs,
            'total_digital_usd'   => $ventasDigUsd,
            'total_digital_bs'    => $ventasDigBs
        ];
    }

    /**
     * Registra un movimiento manual (Entrada extra o Gasto menor de caja).
     */
    public function registrarMovimiento(int $cajaId, string $tipo, string $concepto, float $monto, string $moneda, int $usuarioId): bool {
        if ($monto <= 0 || empty($concepto)) return false;
        if (!in_array($tipo, ['ingreso', 'egreso'], true)) return false;
        if (!in_array($moneda, ['USD', 'BS'], true)) return false;

        $pdo = db();
        try {
            $stmt = $pdo->prepare("INSERT INTO movimientos_caja (caja_id, usuario_id, tipo, concepto, monto, moneda)
                                   VALUES (?, ?, ?, ?, ?, ?)");
            return $stmt->execute([$cajaId, $usuarioId, $tipo, trim($concepto), $monto, $moneda]);
        } catch (PDOException $e) {
            error_log('[TENKI Caja] Error movimiento: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Realiza el cierre de turno y arqueo de caja.
     */
    public function cerrarCaja(int $cajaId, float $montoFinalUsd, float $montoFinalBs, ?string $observaciones, int $usuarioId): bool {
        $resumen = $this->getResumenCaja($cajaId);
        if (empty($resumen)) return false;

        $difUsd = $montoFinalUsd - $resumen['total_esperado_usd'];
        $difBs  = $montoFinalBs  - $resumen['total_esperado_bs'];

        $pdo = db();
        try {
            $stmt = $pdo->prepare("UPDATE cajas SET
                usuario_cierre_id    = ?,
                fecha_cierre         = NOW(),
                ventas_efectivo_usd  = ?,
                ventas_efectivo_bs   = ?,
                ventas_digital_usd   = ?,
                ventas_digital_bs    = ?,
                ingresos_extra_usd   = ?,
                ingresos_extra_bs    = ?,
                egresos_usd          = ?,
                egresos_bs           = ?,
                monto_final_usd      = ?,
                monto_final_bs       = ?,
                diferencia_usd       = ?,
                diferencia_bs        = ?,
                estado               = 'cerrada',
                observaciones        = ?
                WHERE id = ? AND estado = 'abierta'");

            return $stmt->execute([
                $usuarioId,
                $resumen['ventas_efectivo_usd'],
                $resumen['ventas_efectivo_bs'],
                $resumen['ventas_digital_usd'],
                $resumen['ventas_digital_bs'],
                $resumen['ingresos_extra_usd'],
                $resumen['ingresos_extra_bs'],
                $resumen['egresos_usd'],
                $resumen['egresos_bs'],
                $montoFinalUsd,
                $montoFinalBs,
                $difUsd,
                $difBs,
                trim($observaciones ?? ''),
                $cajaId
            ]);
        } catch (PDOException $e) {
            error_log('[TENKI Caja] Error al cerrar caja: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Retorna el listado de movimientos de una caja.
     */
    public function getMovimientos(int $cajaId): array {
        $pdo = db();
        $stmt = $pdo->prepare("SELECT m.*, u.nombre as usuario_nombre
                               FROM movimientos_caja m
                               LEFT JOIN usuarios u ON m.usuario_id = u.id
                               WHERE m.caja_id = ?
                               ORDER BY m.id DESC");
        $stmt->execute([$cajaId]);
        return $stmt->fetchAll();
    }

    /**
     * Retorna las ventas vinculadas a una caja.
     */
    public function getVentasCaja(int $cajaId): array {
        $resumen = $this->getResumenCaja($cajaId);
        if (empty($resumen)) return [];

        $caja = $resumen['caja'];
        $fechaApertura = $caja['fecha_apertura'];
        $fechaCierre   = $caja['fecha_cierre'] ?: date('Y-m-d H:i:s');

        $pdo = db();
        $sql = "SELECT v.*, c.nombre AS cliente_nombre
                FROM ventas v
                LEFT JOIN clientes c ON v.cliente_id = c.id
                WHERE (v.caja_id = ? OR (v.caja_id IS NULL AND v.created_at >= ? AND v.created_at <= ?))
                ORDER BY v.created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$cajaId, $fechaApertura, $fechaCierre]);
        return $stmt->fetchAll();
    }

    /**
     * Historial paginado de cajas cerradas.
     */
    public function historial(int $page = 1, int $perPage = 15): array {
        $pdo = db();

        $countStmt = $pdo->query("SELECT COUNT(*) FROM cajas WHERE estado = 'cerrada'");
        $total = (int)$countStmt->fetchColumn();

        $page = max(1, $page);
        $totalPages = $total > 0 ? (int)ceil($total / $perPage) : 1;
        if ($page > $totalPages) $page = $totalPages;
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT c.*, ua.nombre AS usuario_apertura, uc.nombre AS usuario_cierre
                FROM cajas c
                LEFT JOIN usuarios ua ON c.usuario_apertura_id = ua.id
                LEFT JOIN usuarios uc ON c.usuario_cierre_id = uc.id
                WHERE c.estado = 'cerrada'
                ORDER BY c.id DESC
                LIMIT {$perPage} OFFSET {$offset}";

        $stmt = $pdo->query($sql);
        $items = $stmt->fetchAll();

        return [
            'items'       => $items,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => $totalPages,
            'from'        => $total > 0 ? $offset + 1 : 0,
            'to'          => min($offset + $perPage, $total)
        ];
    }
}

