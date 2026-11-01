<?php
// Vista/compras/ver.php
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/Config/database.php';
require_once ROOT . '/Config/config.php';
require_once ROOT . '/Controlador/CompraController.php';

$ctrl   = new CompraController();
$id     = (int)($_GET['id'] ?? 0);
$compra = $ctrl->getById($id);
if (!$compra) { setFlash('danger', 'Compra no encontrada.'); redirect('Vista/compras/index.php'); }
$detalle = $ctrl->getDetalle($id);

$pageTitle = 'Compra ' . $compra['numero_compra'];
include ROOT . '/Vista/Includes/header.php';
?>

<div class="row justify-content-center">
<div class="col-12 col-xl-9">
<div class="card">
  <div class="card-header">
    <span class="card-title"><i class="bi bi-truck me-2"></i><?= htmlspecialchars($compra['numero_compra']) ?></span>
    <a href="<?= BASE_URL ?>Vista/compras/index.php" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Volver</a>
  </div>
  <div class="card-body">
    <div class="row g-3 mb-4">
      <div class="col-6 col-md-4">
        <small class="text-muted d-block text-uppercase fw-semi" style="font-size:10px;letter-spacing:.5px">Proveedor</small>
        <span class="fw-semi"><?= htmlspecialchars($compra['proveedor_nombre'] ?? '—') ?></span>
        <div class="text-muted" style="font-size:12px"><?= htmlspecialchars($compra['proveedor_rif'] ?? '') ?></div>
      </div>
      <div class="col-6 col-md-4">
        <small class="text-muted d-block text-uppercase fw-semi" style="font-size:10px;letter-spacing:.5px">Fact. Proveedor</small>
        <span><?= htmlspecialchars($compra['numero_factura_proveedor'] ?? '—') ?></span>
      </div>
      <div class="col-6 col-md-4">
        <small class="text-muted d-block text-uppercase fw-semi" style="font-size:10px;letter-spacing:.5px">Fecha</small>
        <span><?= date('d/m/Y', strtotime($compra['fecha'])) ?></span>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr><th>Código</th><th>Producto</th><th>Cantidad Recibida</th><th>P. Compra</th><th>Subtotal</th></tr></thead>
        <tbody>
          <?php foreach ($detalle as $d): ?>
          <tr>
            <td class="text-accent"><?= htmlspecialchars($d['codigo']) ?></td>
            <td><?= htmlspecialchars($d['producto_nombre']) ?></td>
            <td><span class="badge badge-success-c">+<?= $d['cantidad'] ?></span></td>
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
          <div class="row-total"><span class="text-muted">Subtotal:</span><span><?= formatMoney((float)$compra['subtotal']) ?></span></div>
          <div class="row-total"><span class="text-muted">IVA 16%:</span><span><?= formatMoney((float)$compra['iva']) ?></span></div>
          <div class="row-total row-grand"><span>Total:</span><span><?= formatMoney((float)$compra['total']) ?></span></div>
        </div>
      </div>
    </div>

    <?php if ($compra['observaciones']): ?>
    <div class="alert alert-info mt-3"><?= htmlspecialchars($compra['observaciones']) ?></div>
    <?php endif; ?>
  </div>
</div>
</div>
</div>

<?php include ROOT . '/Vista/Includes/footer.php'; ?>
