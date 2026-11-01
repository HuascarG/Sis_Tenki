<?php
// Vista/compras/index.php
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/Config/database.php';
require_once ROOT . '/Config/config.php';
require_once ROOT . '/Config/auth.php';
require_once ROOT . '/Controlador/CompraController.php';

$ctrl = new CompraController();

// Handle anular BEFORE output
if (isset($_GET['anular'])) {
    requireAdmin();
    if ($ctrl->anular((int)$_GET['anular'])) {
        setFlash('success', 'Compra anulada y stock revertido.');
    } else {
        setFlash('danger', 'No se pudo anular la compra.');
    }
    redirect('Vista/compras/index.php');
}

$compras   = $ctrl->listar();
$pageTitle = 'Compras';
include ROOT . '/Vista/Includes/header.php';
?>

<div class="card">
  <div class="card-header">
    <span class="card-title"><i class="bi bi-truck me-2"></i>Facturas de Compra</span>
    <a href="<?= BASE_URL ?>Vista/compras/nueva.php" class="btn btn-primary btn-sm">
      <i class="bi bi-plus-lg me-1"></i>Registrar Compra
    </a>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>#Compra</th><th>Proveedor</th>
          <th class="d-none d-md-table-cell">Fact. Proveedor</th>
          <th class="d-none d-sm-table-cell">Fecha</th>
          <th class="d-none d-lg-table-cell">IVA</th>
          <th>Total</th><th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($compras)): ?>
          <tr><td colspan="7" class="text-center text-muted py-4">Sin compras registradas</td></tr>
        <?php else: foreach ($compras as $c): ?>
          <tr>
            <td class="fw-semi text-accent"><?= htmlspecialchars($c['numero_compra']) ?></td>
            <td><?= htmlspecialchars($c['proveedor_nombre'] ?? '—') ?></td>
            <td class="d-none d-md-table-cell text-muted"><?= htmlspecialchars($c['numero_factura_proveedor'] ?? '—') ?></td>
            <td class="d-none d-sm-table-cell"><?= date('d/m/Y', strtotime($c['fecha'])) ?></td>
            <td class="d-none d-lg-table-cell"><?= formatMoney((float)$c['iva']) ?></td>
            <td class="fw-semi"><?= formatMoney((float)$c['total']) ?></td>
            <td>
              <div class="d-flex gap-1 flex-wrap">
                <a href="<?= BASE_URL ?>Vista/compras/ver.php?id=<?= $c['id'] ?>" class="btn btn-secondary btn-sm" title="Ver Detalle">
                  <i class="bi bi-eye"></i>
                </a>
                <a href="<?= BASE_URL ?>Vista/compras/editar.php?id=<?= $c['id'] ?>" class="btn btn-info btn-sm text-white" title="Editar">
                  <i class="bi bi-pencil"></i>
                </a>
                <?php if (isAdmin() && strpos($c['observaciones'] ?? '', '[ANULADA]') === false): ?>
                <a href="#" onclick="confirmDelete('<?= BASE_URL ?>Vista/compras/index.php?anular=<?= $c['id'] ?>','compra <?= htmlspecialchars($c['numero_compra']) ?> (SE REVERTIRÁ EL STOCK)')" class="btn btn-danger btn-sm" title="Anular">
                  <i class="bi bi-x-circle"></i>
                </a>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include ROOT . '/Vista/Includes/footer.php'; ?>
