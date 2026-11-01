<?php
// Vista/facturacion/index.php
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/Config/database.php';
require_once ROOT . '/Config/config.php';
require_once ROOT . '/Controlador/FacturaController.php';

$ctrl     = new FacturaController();
$filtros = [
    'f_desde' => $_GET['f_desde'] ?? '',
    'f_hasta' => $_GET['f_hasta'] ?? ''
];
$facturas = $ctrl->listar($filtros);
$pageTitle = 'Facturación';
include ROOT . '/Vista/Includes/header.php';
?>

<div class="card">
  <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
    <span class="card-title mb-0"><i class="bi bi-file-earmark-text me-2"></i>Facturas de Venta</span>
    <div class="d-flex flex-wrap gap-2">
      <div class="input-group input-group-sm rounded-pill border overflow-hidden p-0 me-2" style="width:250px; background:var(--surface);">
        <span class="input-group-text border-0 bg-transparent text-muted"><i class="bi bi-search"></i></span>
        <input type="text" id="liveSearchFacturas" class="form-control border-0 shadow-none bg-transparent" placeholder="Buscar factura, venta o cliente...">
      </div>
      <a href="<?= BASE_URL ?>Vista/ventas/nueva.php" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg me-1"></i>Nueva Venta
      </a>
    </div>
  </div>

  <div class="card-body border-bottom bg-light py-3">
    <form method="GET" class="row g-2 align-items-end justify-content-start">
      <div class="col-12 col-sm-6 col-md-3">
        <label class="form-label mb-1" style="font-size:0.8rem">Desde</label>
        <input type="date" name="f_desde" class="form-control form-control-sm" value="<?= htmlspecialchars($filtros['f_desde']) ?>">
      </div>
      <div class="col-12 col-sm-6 col-md-3">
        <label class="form-label mb-1" style="font-size:0.8rem">Hasta</label>
        <input type="date" name="f_hasta" class="form-control form-control-sm" value="<?= htmlspecialchars($filtros['f_hasta']) ?>">
      </div>
      <div class="col-12 col-md-3">
        <div class="d-flex gap-1">
          <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>Filtrar</button>
          <a href="<?= BASE_URL ?>Vista/facturacion/index.php" class="btn btn-secondary btn-sm" title="Limpiar"><i class="bi bi-arrow-counterclockwise"></i></a>
        </div>
      </div>
    </form>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>#Factura</th>
          <th class="d-none d-sm-table-cell">#Venta</th>
          <th>Cliente</th>
          <th class="d-none d-md-table-cell">Fecha</th>
          <th class="d-none d-lg-table-cell">IVA</th>
          <th>Total</th>
          <th>Estado</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($facturas)): ?>
          <tr><td colspan="7" class="text-center text-muted py-4">Sin facturas generadas aún</td></tr>
        <?php else: foreach ($facturas as $f): ?>
          <tr class="factura-row" data-productos="<?= htmlspecialchars(strtolower($f['productos_comprados'] ?? ''), ENT_QUOTES) ?>">
            <td class="fw-semi text-accent"><?= htmlspecialchars($f['numero_factura']) ?></td>
            <td class="d-none d-sm-table-cell text-muted"><?= htmlspecialchars($f['numero_venta']) ?></td>
            <td><?= htmlspecialchars($f['cliente_nombre'] ?? 'Cliente general') ?></td>
            <td class="d-none d-md-table-cell"><?= date('d/m/Y', strtotime($f['fecha'])) ?></td>
            <td class="d-none d-lg-table-cell"><?= formatMoney((float)$f['iva']) ?></td>
            <td class="fw-semi <?= ($f['estado_venta'] ?? '') === 'anulada' ? 'text-decoration-line-through text-muted' : '' ?>">
              <?= formatMoney((float)$f['total']) ?>
            </td>
            <td>
              <?php if (($f['estado_venta'] ?? '') === 'anulada'): ?>
                <span class="badge badge-danger-c">Anulada</span>
              <?php else: ?>
                <span class="badge badge-success-c">Vigente</span>
              <?php endif; ?>
            </td>
            <td>
              <a href="<?= BASE_URL ?>Vista/facturacion/ver.php?id=<?= $f['id'] ?>" class="btn btn-success btn-sm">
                <i class="bi bi-printer me-1"></i><span class="d-none d-sm-inline">Ver / Imprimir</span>
              </a>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('liveSearchFacturas');
    if (!searchInput) return;
    
    searchInput.addEventListener('input', function() {
      const criteria = this.value.toLowerCase().trim();
      document.querySelectorAll('tr.factura-row').forEach(row => {
        // Fac# is child 0, Venta# is child 1, Client is child 2
        const facNum = row.children[0].textContent.toLowerCase();
        const venNum = row.children[1].textContent.toLowerCase();
        const client = row.children[2].textContent.toLowerCase();
        const prods  = row.dataset.productos || "";
        
        if (facNum.includes(criteria) || venNum.includes(criteria) || client.includes(criteria) || prods.includes(criteria)) {
          row.style.display = '';
        } else {
          row.style.display = 'none';
        }
      });
    });
  });
</script>

<?php include ROOT . '/Vista/Includes/footer.php'; ?>
