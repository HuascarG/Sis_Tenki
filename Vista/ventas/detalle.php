<?php
// Vista/ventas/detalle.php
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/Config/database.php';
require_once ROOT . '/Config/config.php';
require_once ROOT . '/Controlador/VentaController.php';
require_once ROOT . '/Controlador/CreditoController.php';

$ctrl  = new VentaController();
$credCtrl = new CreditoController();
$id    = (int)($_GET['id'] ?? 0);
$venta = $ctrl->getById($id);
if (!$venta) { setFlash('danger', 'Venta no encontrada.'); redirect('Vista/ventas/index.php'); }
$detalle = $ctrl->getDetalle($id);
$credito = $credCtrl->getByVentaId($id);

$pageTitle = 'Detalle Venta ' . $venta['numero_venta'];
include ROOT . '/Vista/Includes/header.php';
?>

<div class="row justify-content-center">
<div class="col-12 col-xl-9">
<div class="card">
  <div class="card-header">
    <span class="card-title"><i class="bi bi-receipt me-2"></i>Venta: <?= htmlspecialchars($venta['numero_venta']) ?></span>
    <div class="d-flex gap-2 flex-wrap">
      <a href="<?= BASE_URL ?>Vista/devoluciones/nueva.php?venta_id=<?= $venta['id'] ?>" class="btn btn-warning btn-sm text-dark fw-semi">
        <i class="bi bi-arrow-left-right me-1"></i>Devolución / Garantía
      </a>
      <a href="<?= BASE_URL ?>Vista/facturacion/ver.php?venta_id=<?= $venta['id'] ?>" class="btn btn-success btn-sm">
        <i class="bi bi-printer me-1"></i>Factura
      </a>
      <a href="<?= BASE_URL ?>Vista/ventas/index.php" class="btn btn-secondary btn-sm btn-volver"><i class="bi bi-arrow-left me-1"></i>Volver</a>
    </div>
  </div>
  <div class="card-body">
    <div class="row g-3 mb-4">
      <div class="col-6 col-md-3">
        <small class="text-muted d-block text-uppercase fw-semi" style="font-size:10px;letter-spacing:.5px">Cliente</small>
        <span class="fw-semi"><?= htmlspecialchars($venta['cliente_nombre'] ?? 'Cliente general') ?></span>
      </div>
      <div class="col-6 col-md-3">
        <small class="text-muted d-block text-uppercase fw-semi" style="font-size:10px;letter-spacing:.5px">Fecha</small>
        <span class="fw-semi"><?= date('d/m/Y', strtotime($venta['fecha'])) ?></span>
      </div>
      <div class="col-6 col-md-3">
        <small class="text-muted d-block text-uppercase fw-semi" style="font-size:10px;letter-spacing:.5px">Estado</small>
        <span class="badge <?= $venta['estado']==='completada'?'badge-success-c':($venta['estado']==='anulada'?'badge-danger-c':($venta['estado']==='credito'?'badge-credit-c':'badge-warning-c')) ?>">
          <?= $venta['estado']==='credito' ? '<i class="bi bi-credit-card me-1"></i>' : '' ?><?= ucfirst($venta['estado']) ?>
        </span>
      </div>
      <?php
        $metodoLabels = [
          'efectivo_usd'=>'💵 Efectivo $','binance'=>'🟡 Binance','zelle'=>'🟢 Zelle','zinli'=>'🟣 Zinli',
          'efectivo_bs'=>'💵 Efectivo Bs','transferencia'=>'🏦 Transferencia','punto'=>'💳 Punto','pago_movil'=>'📱 Pago Móvil',
          'credito'=>'🔄 Crédito','efectivo'=>'💵 Efectivo','mixto'=>'🔄 Mixto'
        ];
        $monedaVenta = $venta['moneda'] ?? 'USD';
        $metodoVenta = $venta['metodo_pago'] ?? 'efectivo_usd';
        $metodoLabel = $metodoLabels[$metodoVenta] ?? ucfirst($metodoVenta);
      ?>
      <div class="col-6 col-md-3">
        <small class="text-muted d-block text-uppercase fw-semi" style="font-size:10px;letter-spacing:.5px">Moneda</small>
        <span class="badge <?= $monedaVenta === 'BS' ? 'badge-warning-c' : 'badge-success-c' ?>">
          <?= $monedaVenta === 'BS' ? '🇻🇪 Bolívares' : '💵 Dólares' ?>
        </span>
      </div>
      <div class="col-6 col-md-3">
        <small class="text-muted d-block text-uppercase fw-semi" style="font-size:10px;letter-spacing:.5px">Método de Pago</small>
        <span class="badge badge-info-c"><?= $metodoLabel ?></span>
      </div>
      <?php if ($venta['observaciones']): ?>
      <div class="col-12 col-md-3">
        <small class="text-muted d-block text-uppercase fw-semi" style="font-size:10px;letter-spacing:.5px">Observaciones</small>
        <span class="text-muted"><?= htmlspecialchars($venta['observaciones']) ?></span>
      </div>
      <?php endif; ?>
    </div>

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

    <div class="row justify-content-end mt-3">
      <div class="col-12 col-sm-6 col-md-4">
        <div class="totals-box">
          <div class="row-total"><span class="text-muted">Subtotal:</span><span><?= formatMoney((float)$venta['subtotal']) ?></span></div>
          <?php if ((float)$venta['iva'] > 0): ?>
          <div class="row-total"><span class="text-muted">IVA 16%:</span><span><?= formatMoney((float)$venta['iva']) ?></span></div>
          <?php else: ?>
          <div class="row-total"><span class="text-muted">IVA:</span><span class="text-muted fst-italic">No aplicado</span></div>
          <?php endif; ?>
          <div class="row-total row-grand"><span>Total:</span><span><?= formatMoney((float)$venta['total']) ?></span></div>
        </div>

        <?php if ($credito): ?>
        <?php $pct = $credito['monto_total'] > 0 ? round(($credito['monto_pagado'] / $credito['monto_total']) * 100) : 0; ?>
        <div class="mt-3 p-3 rounded" style="background:rgba(124,58,237,.05);border:1px solid rgba(124,58,237,.15);">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="fw-semi" style="color:#7c3aed;"><i class="bi bi-credit-card-2-front me-1"></i>Crédito</span>
            <span class="badge badge-credit-c"><?= ucfirst($credito['estado']) ?> — <?= $pct ?>%</span>
          </div>
          <div class="credit-progress-bar mb-2" style="height:8px;">
            <div class="credit-progress-fill" style="width:<?= $pct ?>%;height:100%;"></div>
          </div>
          <div class="d-flex justify-content-between" style="font-size:.85rem;">
            <span class="text-success">Pagado: <?= formatMoney((float)$credito['monto_pagado']) ?></span>
            <span class="fw-bold">Pendiente: <?= formatMoney((float)$credito['monto_pendiente']) ?></span>
          </div>
          <a href="<?= BASE_URL ?>Vista/creditos/ver.php?id=<?= $credito['id'] ?>" class="btn btn-sm mt-2 w-100" style="background:rgba(124,58,237,.1);color:#7c3aed;">
            <i class="bi bi-arrow-right me-1"></i>Gestionar Crédito
          </a>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
</div>
</div>

<?php include ROOT . '/Vista/Includes/footer.php'; ?>
