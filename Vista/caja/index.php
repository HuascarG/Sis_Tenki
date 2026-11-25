<?php
// Vista/caja/index.php
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/Config/database.php';
require_once ROOT . '/Config/config.php';
require_once ROOT . '/Config/auth.php';
require_once ROOT . '/Config/bcv.php';
require_once ROOT . '/Controlador/CajaController.php';

$cajaCtrl = new CajaController();
$usuarioId = (int)($_SESSION['user_id'] ?? 1);

// 1. Manejo de Apertura de Caja
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'abrir') {
    validateCsrf();
    $montoUsd = (float)($_POST['monto_inicial_usd'] ?? 0);
    $montoBs  = (float)($_POST['monto_inicial_bs'] ?? 0);
    $obs      = trim($_POST['observaciones'] ?? '');

    $res = $cajaCtrl->abrirCaja($montoUsd, $montoBs, $obs, $usuarioId);
    if ($res === true) {
        setFlash('success', 'Turno de caja abierto exitosamente.');
    } else {
        setFlash('danger', is_string($res) ? $res : 'Error al abrir caja.');
    }
    redirect('Vista/caja/index.php');
}

// 2. Manejo de Movimiento Manual (Ingreso / Egreso)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'movimiento') {
    validateCsrf();
    $cajaId   = (int)($_POST['caja_id'] ?? 0);
    $tipo     = $_POST['tipo'] ?? 'egreso';
    $concepto = trim($_POST['concepto'] ?? '');
    $monto    = (float)($_POST['monto'] ?? 0);
    $moneda   = $_POST['moneda'] ?? 'USD';

    if ($cajaCtrl->registrarMovimiento($cajaId, $tipo, $concepto, $monto, $moneda, $usuarioId)) {
        setFlash('success', 'Movimiento registrado correctamente.');
    } else {
        setFlash('danger', 'Error al registrar el movimiento.');
    }
    redirect('Vista/caja/index.php');
}

// 3. Manejo de Cierre de Caja
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cerrar') {
    validateCsrf();
    $cajaId     = (int)($_POST['caja_id'] ?? 0);
    $finalUsd   = (float)($_POST['monto_final_usd'] ?? 0);
    $finalBs    = (float)($_POST['monto_final_bs'] ?? 0);
    $obs        = trim($_POST['observaciones'] ?? '');

    if ($cajaCtrl->cerrarCaja($cajaId, $finalUsd, $finalBs, $obs, $usuarioId)) {
        setFlash('success', 'Turno de caja cerrado y arqueado correctamente.');
        redirect('Vista/caja/ver.php?id=' . $cajaId);
    } else {
        setFlash('danger', 'Error al cerrar el turno de caja.');
        redirect('Vista/caja/index.php');
    }
}

$cajaActiva = $cajaCtrl->getCajaAbierta();
$resumen    = $cajaActiva ? $cajaCtrl->getResumenCaja((int)$cajaActiva['id']) : null;
$movimientos = $cajaActiva ? $cajaCtrl->getMovimientos((int)$cajaActiva['id']) : [];
$ventas      = $cajaActiva ? $cajaCtrl->getVentasCaja((int)$cajaActiva['id']) : [];

$bcvRate  = getBcvRate();
$bcvFecha = getBcvCacheDate();

$pageTitle = 'Control de Caja';
include ROOT . '/Vista/Includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
  <div>
    <div class="d-flex align-items-center gap-2 mb-1">
      <h3 class="mb-0 text-dark fw-bold"><i class="bi bi-cash-coin me-2 text-primary"></i>Control de Caja</h3>
      <?php if ($cajaActiva): ?>
      <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-1 fw-bold">
        <i class="bi bi-circle-fill me-1" style="font-size:0.5rem; vertical-align:middle;"></i>Turno Activo #<?= $cajaActiva['id'] ?>
      </span>
      <?php else: ?>
      <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-3 py-1 fw-bold">
        <i class="bi bi-lock-fill me-1"></i>Caja Cerrada
      </span>
      <?php endif; ?>
    </div>
    <p class="text-muted small mb-0">
      <?php if ($cajaActiva): ?>
        Iniciado por <strong><?= htmlspecialchars($cajaActiva['usuario_apertura'] ?? 'Usuario') ?></strong> · Apertura: <?= date('d/m/Y h:i A', strtotime($cajaActiva['fecha_apertura'])) ?>
      <?php else: ?>
        Gestión de efectivo, recaudación multimoneda (USD / Bs) y arqueos diarios.
      <?php endif; ?>
    </p>
  </div>

  <div class="d-flex align-items-center gap-2 flex-wrap">
    <?php if ($bcvRate): ?>
    <span class="badge badge-gold fw-semi py-2 px-3 d-none d-md-inline-block" title="Tasa BCV: <?= $bcvFecha ?>">
      <i class="bi bi-currency-exchange me-1"></i>BCV: Bs.&nbsp;<?= number_format($bcvRate, 4, ',', '.') ?>
    </span>
    <?php endif; ?>
    
    <a href="<?= BASE_URL ?>Vista/caja/historial.php" class="btn btn-outline-secondary btn-sm" title="Ver turnos anteriores">
      <i class="bi bi-clock-history me-1"></i>Historial
    </a>

    <?php if ($cajaActiva): ?>
    <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalMovimiento">
      <i class="bi bi-arrow-down-up me-1"></i>Movimiento Manual
    </button>
    <a href="<?= BASE_URL ?>Vista/caja/ver.php?id=<?= $cajaActiva['id'] ?>&corte=X" target="_blank" class="btn btn-outline-secondary btn-sm" title="Imprimir Corte Parcial X">
      <i class="bi bi-printer me-1"></i>Corte X
    </a>
    <button type="button" class="btn btn-danger btn-sm px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalCerrarCaja">
      <i class="bi bi-lock-fill me-1"></i>Cerrar Caja
    </button>
    <?php endif; ?>
  </div>
</div>

<?php if (!$cajaActiva): ?>
  <!-- ================= VISTA CAJA CERRADA ================= -->
  <div class="card border-0 shadow-sm text-center py-5 my-3">
    <div class="card-body p-4">
      <div class="mb-3">
        <span class="badge bg-danger-subtle text-danger rounded-pill px-3 py-2 fw-semi fs-6">
          <i class="bi bi-lock-fill me-1"></i>Caja Actualmente Cerrada
        </span>
      </div>
      <h3 class="fw-bold mb-2">No hay un turno de caja abierto</h3>
      <p class="text-muted mx-auto mb-4" style="max-width: 500px;">
        Para comenzar a registrar ventas con control de efectivo, arqueos y movimientos de caja, debes iniciar la apertura de un nuevo turno.
      </p>
      <button type="button" class="btn btn-primary btn-lg px-5 rounded-pill shadow-sm" data-bs-toggle="modal" data-bs-target="#modalAbrirCaja">
        <i class="bi bi-unlock-fill me-2"></i>Abrir Turno de Caja
      </button>
    </div>
  </div>

  <!-- Modal Apertura de Caja -->
  <div class="modal fade" id="modalAbrirCaja" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title fw-bold"><i class="bi bi-unlock-fill me-2"></i>Apertura de Turno de Caja</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <form method="POST">
          <div class="modal-body p-4">
            <input type="hidden" name="action" value="abrir">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

            <p class="text-muted small mb-3">Ingresa los fondos iniciales (cambio/sencillo) con los que inicia la gaveta.</p>

            <div class="mb-3">
              <label class="form-label fw-semi">Monto Inicial en Efectivo (USD $)</label>
              <div class="input-group">
                <span class="input-group-text">$</span>
                <input type="number" step="0.01" min="0" name="monto_inicial_usd" class="form-control" value="0.00" required>
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label fw-semi">Monto Inicial en Efectivo (Bolívares Bs)</label>
              <div class="input-group">
                <span class="input-group-text">Bs.</span>
                <input type="number" step="0.01" min="0" name="monto_inicial_bs" class="form-control" value="0.00" required>
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label fw-semi">Observaciones (Opcional)</label>
              <textarea name="observaciones" class="form-control" rows="2" placeholder="Ej. Turno de la mañana, responsable de caja..."></textarea>
            </div>
          </div>
          <div class="modal-footer bg-light">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-primary px-4"><i class="bi bi-check-lg me-1"></i>Abrir Caja</button>
          </div>
        </form>
      </div>
    </div>
  </div>

<?php else: ?>
  <!-- ================= VISTA CAJA ABIERTA ================= -->

  <!-- TARJETAS DE FONDOS EN EFECTIVO Y RECAUDACIÓN (ESTILO DASHBOARD LIMPIO) -->
  <div class="stats-grid mb-4">
    <!-- EFECTIVO USD -->
    <div class="stat-card">
      <div class="stat-icon green"><i class="bi bi-cash-stack"></i></div>
      <div>
        <div class="stat-label">Efectivo Gaveta ($)</div>
        <div class="stat-value text-success">$ <?= number_format($resumen['total_esperado_usd'], 2, ',', '.') ?></div>
        <div class="stat-sub">Fondo: $ <?= number_format($resumen['monto_inicial_usd'], 2) ?> · Ventas: +$ <?= number_format($resumen['ventas_efectivo_usd'], 2) ?></div>
      </div>
    </div>

    <!-- EFECTIVO BS -->
    <div class="stat-card">
      <div class="stat-icon blue"><i class="bi bi-cash"></i></div>
      <div>
        <div class="stat-label">Efectivo Gaveta (Bs)</div>
        <div class="stat-value text-primary">Bs. <?= number_format($resumen['total_esperado_bs'], 2, ',', '.') ?></div>
        <div class="stat-sub">Fondo: Bs. <?= number_format($resumen['monto_inicial_bs'], 2) ?> · Ventas: +Bs. <?= number_format($resumen['ventas_efectivo_bs'], 2) ?></div>
      </div>
    </div>

    <!-- DIGITAL USD -->
    <div class="stat-card">
      <div class="stat-icon yellow"><i class="bi bi-wallet2"></i></div>
      <div>
        <div class="stat-label">Digital USD (Zelle / Binance)</div>
        <div class="stat-value">$ <?= number_format($resumen['ventas_digital_usd'], 2, ',', '.') ?></div>
        <div class="stat-sub">Recaudado en cuentas USD</div>
      </div>
    </div>

    <!-- DIGITAL BS -->
    <div class="stat-card">
      <div class="stat-icon gold"><i class="bi bi-credit-card-2-front"></i></div>
      <div>
        <div class="stat-label">Digital Bs (Punto / P.Móvil)</div>
        <div class="stat-value">Bs. <?= number_format($resumen['ventas_digital_bs'], 2, ',', '.') ?></div>
        <div class="stat-sub">Recaudado en bancos Bs.</div>
      </div>
    </div>
  </div>

  <!-- SECCIÓN DE DETALLES: MOVIMIENTOS Y VENTAS -->
  <div class="row g-4">
    <!-- MOVIMIENTOS MANUALES (GASTOS Y ENTRADAS) -->
    <div class="col-12 col-lg-5">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-header bg-transparent d-flex justify-content-between align-items-center py-3">
          <span class="fw-bold"><i class="bi bi-arrow-down-up me-2 text-primary"></i>Entradas y Gastos del Turno</span>
          <button type="button" class="btn btn-primary btn-sm rounded-pill" data-bs-toggle="modal" data-bs-target="#modalMovimiento">
            <i class="bi bi-plus-lg me-1"></i>Nuevo
          </button>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size:0.875rem;">
              <thead class="table-light">
                <tr>
                  <th>Tipo</th><th>Concepto</th><th class="text-end">Monto</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($movimientos)): ?>
                  <tr><td colspan="3" class="text-center text-muted py-4">No hay movimientos registrados en este turno</td></tr>
                <?php else: foreach ($movimientos as $m): ?>
                  <tr>
                    <td>
                      <span class="badge <?= $m['tipo'] === 'ingreso' ? 'bg-success' : 'bg-danger' ?>">
                        <?= $m['tipo'] === 'ingreso' ? '+ Entrada' : '- Gasto' ?>
                      </span>
                    </td>
                    <td>
                      <div class="fw-semi text-dark"><?= htmlspecialchars($m['concepto']) ?></div>
                      <small class="text-muted"><?= date('h:i A', strtotime($m['created_at'])) ?> (<?= htmlspecialchars($m['usuario_nombre'] ?? '') ?>)</small>
                    </td>
                    <td class="text-end fw-bold <?= $m['tipo'] === 'ingreso' ? 'text-success' : 'text-danger' ?>">
                      <?= $m['tipo'] === 'ingreso' ? '+' : '-' ?> <?= $m['moneda'] === 'USD' ? '$' : 'Bs.' ?> <?= number_format($m['monto'], 2, ',', '.') ?>
                    </td>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- VENTAS DEL TURNO -->
    <div class="col-12 col-lg-7">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-header bg-transparent d-flex justify-content-between align-items-center py-3">
          <span class="fw-bold"><i class="bi bi-cart3 me-2 text-primary"></i>Ventas Registradas en este Turno</span>
          <span class="badge bg-secondary"><?= count($ventas) ?> Ventas</span>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size:0.875rem;">
              <thead class="table-light">
                <tr>
                  <th>#Venta</th><th>Cliente</th><th>Método</th><th class="text-end">Total</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($ventas)): ?>
                  <tr><td colspan="4" class="text-center text-muted py-4">Sin ventas realizadas aún en este turno</td></tr>
                <?php else: foreach ($ventas as $v):
                  $metodoLabels = [
                    'efectivo_usd'=>'💵 Efectivo $','binance'=>'🟡 Binance','zelle'=>'🟢 Zelle','zinli'=>'🟣 Zinli',
                    'efectivo_bs'=>'💵 Efectivo Bs','transferencia'=>'🏦 Transf.','punto'=>'💳 Punto','pago_movil'=>'📱 P. Móvil',
                    'credito'=>'🔄 Crédito'
                  ];
                  $metLabel = $metodoLabels[$v['metodo_pago']] ?? ucfirst($v['metodo_pago']);
                ?>
                  <tr>
                    <td class="fw-semi text-accent"><?= htmlspecialchars($v['numero_venta']) ?></td>
                    <td><?= htmlspecialchars($v['cliente_nombre'] ?? 'Cliente General') ?></td>
                    <td><span class="badge badge-info-c" style="font-size:0.75rem;"><?= $metLabel ?></span></td>
                    <td class="text-end fw-bold"><?= $v['moneda'] === 'BS' ? 'Bs.' : '$' ?> <?= number_format($v['total'], 2, ',', '.') ?></td>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- MODAL MOVIMIENTO MANUAL (INGRESO / GASTO) -->
  <div class="modal fade" id="modalMovimiento" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title fw-bold"><i class="bi bi-arrow-down-up me-2"></i>Registrar Movimiento de Caja</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <form method="POST">
          <div class="modal-body p-4">
            <input type="hidden" name="action" value="movimiento">
            <input type="hidden" name="caja_id" value="<?= $cajaActiva['id'] ?>">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

            <div class="mb-3">
              <label class="form-label fw-semi">Tipo de Movimiento</label>
              <select name="tipo" class="form-select" required>
                <option value="egreso">Egreso / Gasto (Salida de dinero)</option>
                <option value="ingreso">Ingreso Extra (Entrada de dinero)</option>
              </select>
            </div>

            <div class="row g-2 mb-3">
              <div class="col-7">
                <label class="form-label fw-semi">Monto</label>
                <input type="number" step="0.01" min="0.01" name="monto" class="form-control" placeholder="0.00" required>
              </div>
              <div class="col-5">
                <label class="form-label fw-semi">Moneda</label>
                <select name="moneda" class="form-select" required>
                  <option value="USD">Dólares ($)</option>
                  <option value="BS">Bolívares (Bs)</option>
                </select>
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label fw-semi">Concepto o Motivo</label>
              <input type="text" name="concepto" class="form-control" placeholder="Ej. Pago de delivery, compra de agua, sencillo..." required>
            </div>
          </div>
          <div class="modal-footer bg-light">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-primary px-4"><i class="bi bi-check-lg me-1"></i>Guardar Movimiento</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- MODAL CIERRE DE CAJA Y ARQUEO -->
  <div class="modal fade" id="modalCerrarCaja" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow">
        <div class="modal-header bg-danger text-white">
          <h5 class="modal-title fw-bold"><i class="bi bi-lock-fill me-2"></i>Cierre de Turno y Arqueo (Corte Z)</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <form method="POST">
          <div class="modal-body p-4">
            <input type="hidden" name="action" value="cerrar">
            <input type="hidden" name="caja_id" value="<?= $cajaActiva['id'] ?>">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

            <div class="alert alert-warning py-2 small mb-3">
              <i class="bi bi-exclamation-triangle-fill me-1"></i>
              Cuenta el dinero físico presente en la gaveta e ingrésalo a continuación. El sistema calculará si hay sobrante o faltante.
            </div>

            <div class="bg-light p-3 rounded-3 mb-3 border">
              <div class="d-flex justify-content-between mb-1">
                <span class="text-muted small">Efectivo Esperado USD:</span>
                <span class="fw-bold text-success">$ <?= number_format($resumen['total_esperado_usd'], 2, ',', '.') ?></span>
              </div>
              <div class="d-flex justify-content-between">
                <span class="text-muted small">Efectivo Esperado Bs:</span>
                <span class="fw-bold text-primary">Bs. <?= number_format($resumen['total_esperado_bs'], 2, ',', '.') ?></span>
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label fw-semi text-success">Efectivo Físico Contado (USD $)</label>
              <div class="input-group">
                <span class="input-group-text">$</span>
                <input type="number" step="0.01" min="0" name="monto_final_usd" class="form-control" value="<?= $resumen['total_esperado_usd'] ?>" required>
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label fw-semi text-primary">Efectivo Físico Contado (Bolívares Bs)</label>
              <div class="input-group">
                <span class="input-group-text">Bs.</span>
                <input type="number" step="0.01" min="0" name="monto_final_bs" class="form-control" value="<?= $resumen['total_esperado_bs'] ?>" required>
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label fw-semi">Observaciones de Cierre</label>
              <textarea name="observaciones" class="form-control" rows="2" placeholder="Ej. Cuadre exacto, billete de 20 deteriorado, etc."></textarea>
            </div>
          </div>
          <div class="modal-footer bg-light">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-danger px-4"><i class="bi bi-lock-fill me-1"></i>Confirmar y Cerrar Caja</button>
          </div>
        </form>
      </div>
    </div>
  </div>

<?php endif; ?>

<?php include ROOT . '/Vista/Includes/footer.php'; ?>

