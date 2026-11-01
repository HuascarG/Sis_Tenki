<?php
// Vista/creditos/ver.php — Credit detail & payment registration
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/Config/database.php';
require_once ROOT . '/Config/config.php';
require_once ROOT . '/Config/auth.php';
require_once ROOT . '/Controlador/CreditoController.php';
require_once ROOT . '/Controlador/VentaController.php';

requireLogin();
$ctrl = new CreditoController();
$ventaCtrl = new VentaController();

$id = (int)($_GET['id'] ?? 0);
$credito = $ctrl->getById($id);
if (!$credito) { setFlash('danger', 'Crédito no encontrado.'); redirect('Vista/creditos/index.php'); }

// Handle abono submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrar_abono'])) {
    $montoAbono = (float)str_replace(',', '.', $_POST['monto'] ?? '0');
    if ($montoAbono <= 0) {
        setFlash('danger', 'El monto del abono debe ser mayor a 0.');
        redirect('Vista/creditos/ver.php?id=' . $id);
    }
    if ($montoAbono > (float)$credito['monto_pendiente'] + 0.01) {
        setFlash('danger', 'El monto supera la deuda pendiente.');
        redirect('Vista/creditos/ver.php?id=' . $id);
    }
    $ok = $ctrl->registrarAbono($id, [
        'monto'         => $montoAbono,
        'moneda'        => in_array($_POST['moneda'] ?? '', ['USD','BS']) ? $_POST['moneda'] : 'USD',
        'metodo_pago'   => $_POST['metodo_pago'] ?? 'efectivo_usd',
        'referencia'    => trim($_POST['referencia'] ?? ''),
        'observaciones' => trim($_POST['observaciones'] ?? ''),
        'fecha'         => $_POST['fecha'] ?? date('Y-m-d'),
    ]);
    if ($ok) {
        setFlash('success', 'Abono registrado exitosamente.');
    } else {
        setFlash('danger', 'Error al registrar el abono.');
    }
    redirect('Vista/creditos/ver.php?id=' . $id);
}

$abonos  = $ctrl->getAbonos($id);
$detalle = $ventaCtrl->getDetalle($credito['venta_id']);
$pct = $credito['monto_total'] > 0 ? round(($credito['monto_pagado'] / $credito['monto_total']) * 100) : 0;
$vencido = !empty($credito['fecha_limite']) && $credito['fecha_limite'] < date('Y-m-d') && $credito['estado'] !== 'pagado';

$pageTitle = 'Crédito - ' . $credito['numero_venta'];
include ROOT . '/Vista/Includes/header.php';
?>

<div class="row g-4">

  <!-- LEFT: CREDIT INFO & PRODUCTS -->
  <div class="col-12 col-xl-7">

    <!-- CREDIT OVERVIEW CARD -->
    <div class="card mb-4">
      <div class="card-header">
        <span class="card-title"><i class="bi bi-credit-card-2-front me-2"></i>Crédito — <?= htmlspecialchars($credito['numero_venta']) ?></span>
        <div class="d-flex gap-2">
          <a href="<?= BASE_URL ?>Vista/ventas/detalle.php?id=<?= $credito['venta_id'] ?>" class="btn btn-secondary btn-sm"><i class="bi bi-receipt me-1"></i>Venta</a>
          <a href="<?= BASE_URL ?>Vista/facturacion/ver.php?venta_id=<?= $credito['venta_id'] ?>" class="btn btn-success btn-sm"><i class="bi bi-printer me-1"></i>Factura</a>
          <a href="<?= BASE_URL ?>Vista/creditos/index.php" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Volver</a>
        </div>
      </div>
      <div class="card-body">
        <!-- Client & Date Info -->
        <div class="row g-3 mb-4">
          <div class="col-6 col-md-3">
            <small class="text-muted d-block text-uppercase fw-semi" style="font-size:10px;letter-spacing:.5px">Cliente</small>
            <span class="fw-semi"><?= htmlspecialchars($credito['cliente_nombre']) ?></span>
            <br><small class="text-muted"><?= htmlspecialchars($credito['cliente_doc']) ?></small>
          </div>
          <div class="col-6 col-md-2">
            <small class="text-muted d-block text-uppercase fw-semi" style="font-size:10px;letter-spacing:.5px">Fecha Venta</small>
            <span class="fw-semi"><?= date('d/m/Y', strtotime($credito['fecha_venta'])) ?></span>
          </div>
          <div class="col-6 col-md-2">
            <small class="text-muted d-block text-uppercase fw-semi" style="font-size:10px;letter-spacing:.5px">Fecha Límite</small>
            <?php if (!empty($credito['fecha_limite'])): ?>
              <span class="fw-semi <?= $vencido ? 'text-danger' : '' ?>"><?= date('d/m/Y', strtotime($credito['fecha_limite'])) ?></span>
              <?php if ($vencido): ?><br><small class="text-danger"><i class="bi bi-exclamation-triangle"></i> Vencido</small><?php endif; ?>
            <?php else: ?>
              <span class="text-muted">—</span>
            <?php endif; ?>
          </div>
          <div class="col-6 col-md-2">
            <small class="text-muted d-block text-uppercase fw-semi" style="font-size:10px;letter-spacing:.5px">Estado</small>
            <?php if ($credito['estado'] === 'pagado'): ?>
              <span class="badge badge-success-c"><i class="bi bi-check-circle me-1"></i>Pagado</span>
            <?php elseif ($credito['estado'] === 'parcial'): ?>
              <span class="badge badge-credit-c"><i class="bi bi-clock-history me-1"></i>Parcial</span>
            <?php else: ?>
              <span class="badge badge-warning-c"><?= $vencido ? '<i class="bi bi-exclamation-triangle me-1"></i>Vencido' : '<i class="bi bi-hourglass-split me-1"></i>Pendiente' ?></span>
            <?php endif; ?>
          </div>
          <?php if ($credito['cliente_tel']): ?>
          <div class="col-6 col-md-3">
            <small class="text-muted d-block text-uppercase fw-semi" style="font-size:10px;letter-spacing:.5px">Teléfono</small>
            <span class="fw-semi"><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($credito['cliente_tel']) ?></span>
          </div>
          <?php endif; ?>
        </div>

        <!-- PROGRESS BAR -->
        <div class="mb-4">
          <div class="d-flex justify-content-between align-items-end mb-1">
            <small class="text-muted fw-semi">Progreso de Pago</small>
            <small class="fw-bold" style="color:<?= $pct >= 100 ? '#16a34a' : '#7c3aed' ?>"><?= $pct ?>%</small>
          </div>
          <div class="credit-progress-bar" style="height:12px;border-radius:6px;">
            <div class="credit-progress-fill" style="width:<?= $pct ?>%;height:100%;border-radius:6px;background:<?= $pct >= 100 ? 'linear-gradient(90deg,#16a34a,#22c55e)' : 'linear-gradient(90deg,#7c3aed,#a78bfa)' ?>;"></div>
          </div>
          <div class="d-flex justify-content-between mt-2">
            <div>
              <small class="text-muted d-block" style="font-size:.7rem">PAGADO</small>
              <span class="fw-bold text-success"><?= formatMoney((float)$credito['monto_pagado']) ?></span>
            </div>
            <div class="text-end">
              <small class="text-muted d-block" style="font-size:.7rem">PENDIENTE</small>
              <span class="fw-bold <?= $vencido ? 'text-danger' : '' ?>"><?= formatMoney((float)$credito['monto_pendiente']) ?></span>
            </div>
            <div class="text-end">
              <small class="text-muted d-block" style="font-size:.7rem">TOTAL</small>
              <span class="fw-semi"><?= formatMoney((float)$credito['monto_total']) ?></span>
            </div>
          </div>
        </div>

        <!-- PRODUCTS TABLE -->
        <h6 class="fw-semi mb-2"><i class="bi bi-box me-1"></i>Productos de la Venta</h6>
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead><tr><th>Código</th><th>Producto</th><th>Cant.</th><th>Precio</th><th>Subtotal</th></tr></thead>
            <tbody>
              <?php foreach ($detalle as $d): ?>
              <tr>
                <td class="text-accent"><?= htmlspecialchars($d['codigo']) ?></td>
                <td><?= htmlspecialchars($d['producto_nombre']) ?></td>
                <td><?= $d['cantidad'] ?></td>
                <td><?= formatMoney((float)$d['precio_unitario']) ?></td>
                <td class="fw-semi"><?= formatMoney((float)$d['subtotal']) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- PAYMENT HISTORY -->
    <div class="card">
      <div class="card-header">
        <span class="card-title"><i class="bi bi-clock-history me-2"></i>Historial de Abonos (<?= count($abonos) ?>)</span>
      </div>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead><tr><th>Fecha</th><th>Monto</th><th>Moneda</th><th>Método</th><th>Referencia</th><th>Obs.</th></tr></thead>
          <tbody>
            <?php if (empty($abonos)): ?>
              <tr><td colspan="6" class="text-center text-muted py-4">Sin abonos registrados aún</td></tr>
            <?php else: foreach ($abonos as $ab):
              $metodoLabels = [
                'efectivo_usd'=>'💵 Efectivo $','binance'=>'🟡 Binance','zelle'=>'🟢 Zelle','zinli'=>'🟣 Zinli',
                'efectivo_bs'=>'💵 Efectivo Bs','transferencia'=>'🏦 Transferencia','punto'=>'💳 Punto','pago_movil'=>'📱 Pago Móvil',
                'efectivo'=>'💵 Efectivo','mixto'=>'🔄 Mixto'
              ];
              $metodoLabel = $metodoLabels[$ab['metodo_pago']] ?? ucfirst($ab['metodo_pago']);
              $monedaBadge = ($ab['moneda'] ?? 'USD') === 'BS' ? 'badge-warning-c' : 'badge-success-c';
              $monedaText  = ($ab['moneda'] ?? 'USD') === 'BS' ? 'Bs' : 'USD';
            ?>
              <tr>
                <td class="fw-semi"><?= date('d/m/Y', strtotime($ab['fecha'])) ?></td>
                <td class="fw-bold text-success"><?= formatMoney((float)$ab['monto']) ?></td>
                <td><span class="badge <?= $monedaBadge ?>"><?= $monedaText ?></span></td>
                <td><span class="badge badge-info-c"><?= $metodoLabel ?></span></td>
                <td class="text-muted"><?= htmlspecialchars($ab['referencia'] ?: '—') ?></td>
                <td class="text-muted" style="max-width:150px;"><?= htmlspecialchars($ab['observaciones'] ?: '—') ?></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>

  <!-- RIGHT: PAYMENT FORM -->
  <div class="col-12 col-xl-5">
    <?php if ($credito['estado'] !== 'pagado'): ?>
    <div class="card mb-0" style="border:2px solid rgba(124,58,237,.2);">
      <div class="card-header" style="background:rgba(124,58,237,.06);">
        <span class="card-title" style="color:#7c3aed;"><i class="bi bi-coin me-2"></i>Registrar Abono</span>
      </div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="registrar_abono" value="1">

          <div class="alert py-2 mb-3" style="background:rgba(124,58,237,.06);border:1px solid rgba(124,58,237,.15);font-size:.85rem;color:#5b21b6;">
            <i class="bi bi-info-circle me-1"></i>Deuda pendiente: <strong><?= formatMoney((float)$credito['monto_pendiente']) ?></strong>
          </div>

          <div class="mb-3">
            <label class="form-label">Monto del Abono ($) *</label>
            <input type="number" name="monto" class="form-control" step="0.01" min="0.01"
                   max="<?= number_format((float)$credito['monto_pendiente'], 2, '.', '') ?>"
                   value="<?= number_format((float)$credito['monto_pendiente'], 2, '.', '') ?>" required>
          </div>

          <!-- MONEDA SELECTOR -->
          <div class="mb-3">
            <label class="form-label fw-semi">Moneda del Pago</label>
            <div class="d-flex gap-2">
              <div class="form-check form-check-inline flex-fill m-0">
                <input class="form-check-input d-none" type="radio" name="moneda" id="moneda_usd" value="USD" checked onchange="toggleMetodoPago()">
                <label class="form-check-label w-100 text-center py-2 rounded border moneda-label" for="moneda_usd" id="label_usd" style="cursor:pointer;transition:all .2s;background:rgba(22,163,74,.08);border-color:#16a34a !important;color:#16a34a;font-weight:600;">
                  💵 Dólares (USD)
                </label>
              </div>
              <div class="form-check form-check-inline flex-fill m-0">
                <input class="form-check-input d-none" type="radio" name="moneda" id="moneda_bs" value="BS" onchange="toggleMetodoPago()">
                <label class="form-check-label w-100 text-center py-2 rounded border moneda-label" for="moneda_bs" id="label_bs" style="cursor:pointer;transition:all .2s;">
                  🇻🇪 Bolívares (Bs)
                </label>
              </div>
            </div>
          </div>

          <!-- METODO DE PAGO (dynamic) -->
          <div class="mb-3">
            <label class="form-label">Método de Pago</label>
            <select name="metodo_pago" id="select_metodo_pago" class="form-select">
              <!-- USD Methods (default) -->
              <option value="efectivo_usd">💵 Efectivo (USD)</option>
              <option value="binance">🟡 Binance</option>
              <option value="zelle">🟢 Zelle</option>
              <option value="zinli">🟣 Zinli</option>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label">Referencia <small class="text-muted">(Opcional)</small></label>
            <input type="text" name="referencia" class="form-control" maxlength="100" placeholder="N° transferencia, comprobante...">
          </div>
          <div class="mb-3">
            <label class="form-label">Fecha del Pago</label>
            <input type="date" name="fecha" class="form-control" value="<?= date('Y-m-d') ?>">
          </div>
          <div class="mb-3">
            <label class="form-label">Observaciones <small class="text-muted">(Opcional)</small></label>
            <textarea name="observaciones" class="form-control" rows="2" maxlength="255"></textarea>
          </div>

          <div class="d-grid gap-2">
            <button type="submit" class="btn" style="background:#7c3aed;color:#fff;">
              <i class="bi bi-check-circle me-1"></i>Registrar Abono
            </button>
            <button type="button" class="btn btn-outline-secondary" onclick="document.querySelector('[name=monto]').value='<?= number_format((float)$credito['monto_pendiente'], 2, '.', '') ?>'">
              <i class="bi bi-cash-stack me-1"></i>Pagar Todo
            </button>
          </div>
        </form>
      </div>
    </div>
    <?php else: ?>
    <div class="card mb-0" style="border:2px solid rgba(22,163,74,.2);">
      <div class="card-body text-center py-5">
        <i class="bi bi-check-circle" style="font-size:3rem;color:#16a34a;"></i>
        <h4 class="mt-3 mb-1" style="color:#16a34a;">Crédito Pagado</h4>
        <p class="text-muted">Este crédito ha sido saldado completamente.</p>
        <div class="d-flex justify-content-center gap-3 mt-3">
          <span class="fw-semi">Total: <?= formatMoney((float)$credito['monto_total']) ?></span>
          <span class="text-muted">|</span>
          <span class="fw-semi">Abonos: <?= count($abonos) ?></span>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>

</div>

<script>
function toggleMetodoPago() {
    const isUSD = document.getElementById('moneda_usd').checked;
    const sel = document.getElementById('select_metodo_pago');
    const labelUsd = document.getElementById('label_usd');
    const labelBs = document.getElementById('label_bs');

    // Clear options
    sel.innerHTML = '';

    if (isUSD) {
        sel.innerHTML = `
            <option value="efectivo_usd">💵 Efectivo (USD)</option>
            <option value="binance">🟡 Binance</option>
            <option value="zelle">🟢 Zelle</option>
            <option value="zinli">🟣 Zinli</option>
        `;
        labelUsd.style.background = 'rgba(22,163,74,.08)';
        labelUsd.style.borderColor = '#16a34a';
        labelUsd.style.color = '#16a34a';
        labelUsd.style.fontWeight = '600';
        labelBs.style.background = '';
        labelBs.style.borderColor = '';
        labelBs.style.color = '';
        labelBs.style.fontWeight = '';
    } else {
        sel.innerHTML = `
            <option value="efectivo_bs">💵 Efectivo (Bs)</option>
            <option value="transferencia">🏦 Transferencia</option>
            <option value="punto">💳 Punto de Venta</option>
            <option value="pago_movil">📱 Pago Móvil</option>
        `;
        labelBs.style.background = 'rgba(202,138,4,.08)';
        labelBs.style.borderColor = '#ca8a04';
        labelBs.style.color = '#92400e';
        labelBs.style.fontWeight = '600';
        labelUsd.style.background = '';
        labelUsd.style.borderColor = '';
        labelUsd.style.color = '';
        labelUsd.style.fontWeight = '';
    }
}
</script>

<?php include ROOT . '/Vista/Includes/footer.php'; ?>
