<?php
// Vista/caja/ver.php
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/Config/database.php';
require_once ROOT . '/Config/config.php';
require_once ROOT . '/Config/auth.php';
require_once ROOT . '/Config/bcv.php';
require_once ROOT . '/Controlador/CajaController.php';

$cajaCtrl = new CajaController();
$cajaId   = (int)($_GET['id'] ?? 0);
$esCorteX = isset($_GET['corte']) && strtoupper($_GET['corte']) === 'X';

$resumen = $cajaCtrl->getResumenCaja($cajaId);
if (empty($resumen)) {
    setFlash('danger', 'Turno de caja no encontrado.');
    redirect('Vista/caja/index.php');
}

$caja        = $resumen['caja'];
$movimientos = $cajaCtrl->getMovimientos($cajaId);
$ventas      = $cajaCtrl->getVentasCaja($cajaId);

$tipoCorte = $esCorteX ? 'CORTE X (PARCIAL)' : 'CORTE Z (CIERRE FINAL)';
$pageTitle = 'Comprobante de Caja #' . $cajaId;
include ROOT . '/Vista/Includes/header.php';
?>

<div class="d-print-none d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
  <div>
    <h3 class="mb-1 text-dark fw-bold"><i class="bi bi-receipt me-2 text-primary"></i><?= $tipoCorte ?> — Turno #<?= $cajaId ?></h3>
    <p class="text-muted mb-0">Resumen detallado de recaudación multimoneda y arqueo de caja.</p>
  </div>
  <div class="d-flex gap-2">
    <button type="button" onclick="window.print()" class="btn btn-primary btn-sm">
      <i class="bi bi-printer me-1"></i>Imprimir Reporte
    </button>
    <a href="<?= BASE_URL ?>Vista/caja/index.php" class="btn btn-secondary btn-sm">
      <i class="bi bi-arrow-left me-1"></i>Volver a Caja
    </a>
  </div>
</div>

<div class="row justify-content-center">
  <div class="col-12 col-lg-8">
    <div class="card border-0 shadow-sm p-4 p-md-5 bg-white" id="impresion-caja">
      <!-- HEADER REPORTE -->
      <div class="text-center pb-4 border-bottom mb-4">
        <h4 class="fw-bold mb-1">TENKI REPUESTOS</h4>
        <div class="text-muted small">COMPROBANTE OFICIAL DE ARQUEO DE CAJA</div>
        <div class="badge bg-dark mt-2 py-2 px-3 fw-bold fs-6"><?= $tipoCorte ?></div>
      </div>

      <!-- METADATOS -->
      <div class="row g-3 mb-4 pb-3 border-bottom" style="font-size:0.9rem;">
        <div class="col-6">
          <div class="text-muted small">Turno #:</div>
          <div class="fw-bold">#<?= $cajaId ?> (<?= strtoupper($caja['estado']) ?>)</div>
          <div class="text-muted small mt-2">Apertura:</div>
          <div><?= date('d/m/Y h:i A', strtotime($caja['fecha_apertura'])) ?></div>
          <div class="text-muted small">Por: <?= htmlspecialchars($caja['usuario_apertura'] ?? '') ?></div>
        </div>
        <div class="col-6 text-end">
          <div class="text-muted small">Cierre:</div>
          <div><?= $caja['fecha_cierre'] ? date('d/m/Y h:i A', strtotime($caja['fecha_cierre'])) : 'En curso' ?></div>
          <div class="text-muted small">Por: <?= htmlspecialchars($caja['usuario_cierre'] ?? $caja['usuario_apertura'] ?? '') ?></div>
          <?php if (!empty($caja['observaciones'])): ?>
          <div class="text-muted small mt-2">Obs: <?= htmlspecialchars($caja['observaciones']) ?></div>
          <?php endif; ?>
        </div>
      </div>

      <!-- DESGLOSE DE FONDOS EN EFECTIVO -->
      <h6 class="fw-bold text-uppercase text-muted mb-3" style="font-size:0.8rem; letter-spacing:0.5px;">
        1. Movimiento de Efectivo Físico (Gaveta)
      </h6>
      <div class="table-responsive mb-4">
        <table class="table table-bordered align-middle mb-0" style="font-size:0.875rem;">
          <thead class="table-light">
            <tr>
              <th>Concepto</th>
              <th class="text-end">Dólares ($)</th>
              <th class="text-end">Bolívares (Bs.)</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>Fondo Inicial de Apertura</td>
              <td class="text-end">$ <?= number_format($resumen['monto_inicial_usd'], 2, ',', '.') ?></td>
              <td class="text-end">Bs. <?= number_format($resumen['monto_inicial_bs'], 2, ',', '.') ?></td>
            </tr>
            <tr>
              <td>(+) Ventas en Efectivo</td>
              <td class="text-end text-success">+ $ <?= number_format($resumen['ventas_efectivo_usd'], 2, ',', '.') ?></td>
              <td class="text-end text-success">+ Bs. <?= number_format($resumen['ventas_efectivo_bs'], 2, ',', '.') ?></td>
            </tr>
            <?php if ($resumen['abonos_efectivo_usd'] > 0 || $resumen['abonos_efectivo_bs'] > 0): ?>
            <tr>
              <td>(+) Abonos de Créditos en Efectivo</td>
              <td class="text-end text-success">+ $ <?= number_format($resumen['abonos_efectivo_usd'], 2, ',', '.') ?></td>
              <td class="text-end text-success">+ Bs. <?= number_format($resumen['abonos_efectivo_bs'], 2, ',', '.') ?></td>
            </tr>
            <?php endif; ?>
            <?php if ($resumen['ingresos_extra_usd'] > 0 || $resumen['ingresos_extra_bs'] > 0): ?>
            <tr>
              <td>(+) Entradas / Ingresos Manuales</td>
              <td class="text-end text-success">+ $ <?= number_format($resumen['ingresos_extra_usd'], 2, ',', '.') ?></td>
              <td class="text-end text-success">+ Bs. <?= number_format($resumen['ingresos_extra_bs'], 2, ',', '.') ?></td>
            </tr>
            <?php endif; ?>
            <?php if ($resumen['egresos_usd'] > 0 || $resumen['egresos_bs'] > 0): ?>
            <tr>
              <td>(-) Salidas / Gastos Menores</td>
              <td class="text-end text-danger">- $ <?= number_format($resumen['egresos_usd'], 2, ',', '.') ?></td>
              <td class="text-end text-danger">- Bs. <?= number_format($resumen['egresos_bs'], 2, ',', '.') ?></td>
            </tr>
            <?php endif; ?>
            <tr class="table-light fw-bold">
              <td>(=) TOTAL EFECTIVO ESPERADO EN GAVETA</td>
              <td class="text-end text-success">$ <?= number_format($resumen['total_esperado_usd'], 2, ',', '.') ?></td>
              <td class="text-end text-primary">Bs. <?= number_format($resumen['total_esperado_bs'], 2, ',', '.') ?></td>
            </tr>
            <?php if ($caja['estado'] === 'cerrada'): ?>
            <tr class="fw-bold">
              <td>EFECTIVO FÍSICO CONTADO (ARQUEO)</td>
              <td class="text-end">$ <?= number_format((float)$caja['monto_final_usd'], 2, ',', '.') ?></td>
              <td class="text-end">Bs. <?= number_format((float)$caja['monto_final_bs'], 2, ',', '.') ?></td>
            </tr>
            <tr class="table-warning fw-bold">
              <td>DIFERENCIA (Sobrante / Faltante)</td>
              <td class="text-end <?= (float)$caja['diferencia_usd'] < 0 ? 'text-danger' : 'text-success' ?>">
                <?= (float)$caja['diferencia_usd'] >= 0 ? '+' : '' ?> $ <?= number_format((float)$caja['diferencia_usd'], 2, ',', '.') ?>
              </td>
              <td class="text-end <?= (float)$caja['diferencia_bs'] < 0 ? 'text-danger' : 'text-success' ?>">
                <?= (float)$caja['diferencia_bs'] >= 0 ? '+' : '' ?> Bs. <?= number_format((float)$caja['diferencia_bs'], 2, ',', '.') ?>
              </td>
            </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- DESGLOSE DE RECAUDACIÓN DIGITAL -->
      <h6 class="fw-bold text-uppercase text-muted mb-3" style="font-size:0.8rem; letter-spacing:0.5px;">
        2. Recaudación en Canales Digitales y Bancarios
      </h6>
      <div class="row g-2 mb-4">
        <div class="col-6">
          <div class="p-3 border rounded-3 bg-light">
            <div class="text-muted small">Canales Digitales USD (Zelle / Binance / Zinli):</div>
            <div class="h5 fw-bold mb-0 text-dark">$ <?= number_format($resumen['ventas_digital_usd'], 2, ',', '.') ?></div>
          </div>
        </div>
        <div class="col-6">
          <div class="p-3 border rounded-3 bg-light">
            <div class="text-muted small">Canales Digitales Bs (Punto / Pago Móvil / Transf):</div>
            <div class="h5 fw-bold mb-0 text-dark">Bs. <?= number_format($resumen['ventas_digital_bs'], 2, ',', '.') ?></div>
          </div>
        </div>
      </div>

      <!-- FIRMAS -->
      <div class="row pt-5 mt-5 border-top text-center" style="font-size:0.85rem;">
        <div class="col-6">
          <div class="border-top mx-auto pt-2" style="max-width: 200px;">
            Firma Responsable Caja
          </div>
        </div>
        <div class="col-6">
          <div class="border-top mx-auto pt-2" style="max-width: 200px;">
            Firma Supervisor / Admin
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
@media print {
  body { background: #fff !important; }
  .sidebar, .navbar, .d-print-none { display: none !important; }
  #impresion-caja { box-shadow: none !important; border: none !important; padding: 0 !important; }
}
</style>

<?php include ROOT . '/Vista/Includes/footer.php'; ?>

