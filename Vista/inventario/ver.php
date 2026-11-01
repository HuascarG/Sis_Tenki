<?php
// Vista/inventario/ver.php
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/Config/database.php';
require_once ROOT . '/Config/config.php';
require_once ROOT . '/Config/bcv.php';
require_once ROOT . '/Controlador/InventarioController.php';

$ctrl = new InventarioController();
$id   = (int)($_GET['id'] ?? 0);
$prod = $ctrl->getById($id);
if (!$prod) { setFlash('danger', 'Producto no encontrado.'); redirect('Vista/inventario/index.php'); }

// Fetch relationships names for display
$pdo = db();
$catNombre = '—';
if (!empty($prod['categoria_id'])) {
    $stmt = $pdo->prepare("SELECT nombre FROM categorias WHERE id = ?");
    $stmt->execute([$prod['categoria_id']]);
    $catNombre = $stmt->fetchColumn() ?: '—';
}
$provNombre = '—';
if (!empty($prod['proveedor_id'])) {
    $stmt = $pdo->prepare("SELECT nombre FROM proveedores WHERE id = ?");
    $stmt->execute([$prod['proveedor_id']]);
    $provNombre = $stmt->fetchColumn() ?: '—';
}

$bcvRate = getBcvRate();
$pageTitle = 'Detalle de Producto';
include ROOT . '/Vista/Includes/header.php';
?>

<div class="row justify-content-center">
<div class="col-12 col-xl-8">
  <div class="card shadow-sm border-0">
    <div class="card-header bg-primary bg-gradient text-white d-flex justify-content-between align-items-center">
      <span class="card-title mb-0"><i class="bi bi-box-seam me-2"></i>Detalle de Producto</span>
      <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>Vista/inventario/editar.php?id=<?= $id ?>" class="btn btn-light btn-sm text-primary fw-semi">
          <i class="bi bi-pencil me-1"></i>Editar
        </a>
        <a href="<?= BASE_URL ?>Vista/inventario/index.php" class="btn btn-dark btn-sm bg-black bg-opacity-25 border-0">
          <i class="bi bi-arrow-left me-1"></i>Volver
        </a>
      </div>
    </div>
    
    <div class="card-body">
      <div class="row g-4">
        <div class="col-12">
            <?php if (!empty($prod['foto'])): ?>
            <div class="text-center mb-3">
                <img src="<?= BASE_URL . htmlspecialchars($prod['foto']) ?>" alt="Foto del producto" class="rounded shadow" style="max-height: 200px; max-width: 100%; object-fit: contain;">
            </div>
            <?php endif; ?>
            <h4 class="mb-1 text-primary-emphasis fw-bold"><?= htmlspecialchars($prod['nombre']) ?></h4>
            <p class="text-muted mb-0">Código: <span class="badge bg-secondary ms-1 fs-6"><?= !empty($prod['codigo']) ? htmlspecialchars($prod['codigo']) : 'Sin código' ?></span></p>
        </div>
        
        <div class="col-12 col-md-6">
          <ul class="list-group list-group-flush">
            <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent px-0">
              <span class="text-muted"><i class="bi bi-tags me-2"></i>Categoría</span>
              <span class="fw-semi"><?= htmlspecialchars($catNombre) ?></span>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent px-0">
              <span class="text-muted"><i class="bi bi-bookmark me-2"></i>Marca</span>
              <span class="fw-semi"><?= htmlspecialchars($prod['marca'] ?? '—') ?></span>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent px-0">
              <span class="text-muted"><i class="bi bi-star me-2"></i>Condición</span>
              <span class="badge <?= ($prod['condicion']??'nuevo')==='nuevo' ? 'bg-success-c' : 'bg-warning-c' ?>">
                <?= ucfirst($prod['condicion'] ?? 'Nuevo') ?>
              </span>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent px-0">
              <span class="text-muted"><i class="bi bi-rulers me-2"></i>Unidad de Medida</span>
              <span class="fw-semi"><?= ucfirst(htmlspecialchars($prod['unidad'] ?? 'Unidad')) ?></span>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent px-0">
              <span class="text-muted"><i class="bi bi-truck me-2"></i>Proveedor</span>
              <span class="fw-semi"><?= htmlspecialchars($provNombre) ?></span>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent px-0">
              <span class="text-muted"><i class="bi bi-geo-alt me-2"></i>Ubicación</span>
              <span class="fw-semi"><?= htmlspecialchars($prod['ubicacion'] ?? '—') ?></span>
            </li>
          </ul>
        </div>
        
        <div class="col-12 col-md-6">
          <ul class="list-group list-group-flush">
            <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent px-0">
              <span class="text-muted"><i class="bi bi-cash-stack me-2"></i>Precio de Compra</span>
              <span class="fw-semi text-danger"><?= formatMoney((float)$prod['precio_compra']) ?></span>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent px-0">
              <span class="text-muted"><i class="bi bi-cash-coin me-2"></i>Precio de Venta ($)</span>
              <span class="fw-bold text-success fs-5"><?= formatMoney((float)$prod['precio_venta']) ?></span>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent px-0">
              <span class="text-muted"><i class="bi bi-currency-exchange me-2"></i>P. Venta (Bs.)</span>
              <span class="fw-bold text-info-emphasis fs-5">
                <?php if ($bcvRate && $prod['precio_venta'] > 0): ?>
                  <?= formatBs((float)$prod['precio_venta'] * $bcvRate) ?>
                  <small class="d-block text-end fw-normal text-muted" style="font-size: 0.7rem">Tasa: Bs. <?= number_format($bcvRate,2,',','.') ?></small>
                <?php else: ?>
                  —
                <?php endif; ?>
              </span>
            </li>
          </ul>
        </div>

        <!-- STOCKS BLOCK -->
        <div class="col-12 mt-4">
            <div class="p-3 bg-light rounded border border-light-subtle d-flex justify-content-around text-center">
                <div>
                   <span class="d-block text-muted small text-uppercase fw-bold mb-1">Stock Actual</span>
                   <span class="fs-4 fw-bold <?= $prod['stock'] <= $prod['stock_minimo'] ? 'text-danger' : 'text-primary' ?>">
                     <?= $prod['stock'] ?>
                   </span>
                </div>
                <div class="border-start"></div>
                <div>
                   <span class="d-block text-muted small text-uppercase fw-bold mb-1">Stock Mínimo</span>
                   <span class="fs-4 fw-semi text-black-50">
                     <?= $prod['stock_minimo'] ?>
                   </span>
                </div>
                <div class="border-start"></div>
                <div>
                   <span class="d-block text-muted small text-uppercase fw-bold mb-1">Estado</span>
                   <?php if ($prod['stock'] <= $prod['stock_minimo']): ?>
                       <span class="badge bg-danger mt-1"><i class="bi bi-exclamation-triangle me-1"></i>Crítico</span>
                   <?php else: ?>
                       <span class="badge bg-success mt-1"><i class="bi bi-check-circle me-1"></i>Óptimo</span>
                   <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if (!empty($prod['descripcion'])): ?>
        <div class="col-12">
            <label class="text-muted small fw-bold text-uppercase mb-2"><i class="bi bi-card-text me-1"></i>Descripción Detallada</label>
            <div class="p-3 bg-white border rounded">
                <?= nl2br(htmlspecialchars($prod['descripcion'])) ?>
            </div>
        </div>
        <?php endif; ?>

      </div>
    </div>
  </div>
</div>
</div>

<?php include ROOT . '/Vista/Includes/footer.php'; ?>
