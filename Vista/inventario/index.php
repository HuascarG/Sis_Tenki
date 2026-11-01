<?php
// Vista/inventario/index.php
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/Config/database.php';
require_once ROOT . '/Config/config.php';
require_once ROOT . '/Config/auth.php';
require_once ROOT . '/Config/bcv.php';
require_once ROOT . '/Controlador/InventarioController.php';

$ctrl = new InventarioController();
if (isset($_GET['delete'])) {
    requireAdmin();
    validateCsrf();
    $ctrl->eliminar((int)$_GET['delete']);
    setFlash('success', 'Producto eliminado correctamente.');
    redirect('Vista/inventario/index.php');
}

$q         = trim($_GET['q'] ?? '');
$productos = $q ? $ctrl->buscar($q) : $ctrl->listar();

// Tasa BCV
$bcvRate     = getBcvRate();        // float|null
$bcvFecha    = getBcvCacheDate();   // string|null

$pageTitle = 'Inventario';
include ROOT . '/Vista/Includes/header.php';
?>

<div class="card">
  <div class="card-header">
    <span class="card-title"><i class="bi bi-box-seam me-2"></i>Lista de Productos</span>
    <div class="d-flex align-items-center gap-2 flex-wrap">
      <?php if ($bcvRate): ?>
      <span class="badge badge-gold fw-semi" title="Tasa BCV actualizada: <?= $bcvFecha ?>">
        <i class="bi bi-currency-exchange me-1"></i>
        BCV: Bs.&nbsp;<?= number_format($bcvRate, 4, ',', '.') ?>
        <small class="opacity-75 ms-1"><?= $bcvFecha ?></small>
      </span>
      <?php else: ?>
      <span class="badge badge-danger-c"><i class="bi bi-exclamation-triangle me-1"></i>Tasa BCV no disponible</span>
      <?php endif; ?>
      <a href="<?= BASE_URL ?>Vista/inventario/crear.php" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg me-1"></i>Nuevo Producto
      </a>
    </div>
  </div>

  <!-- SEARCH -->
  <div class="card-body pb-0">
    <form method="GET" class="row g-2 align-items-end mb-3">
      <div class="col-12 col-md-8 col-lg-6">
        <div class="input-group">
          <span class="input-group-text" style="background:var(--bg-card);border-color:var(--border);color:var(--text-muted-c)">
            <i class="bi bi-search"></i>
          </span>
          <input type="text" id="liveSearchInventario" name="q" class="form-control"
                 placeholder="Buscar por nombre, código o categoría..."
                 value="<?= htmlspecialchars($q) ?>">
        </div>
      </div>
      <div class="col-auto">
        <button type="submit" class="btn btn-secondary"><i class="bi bi-search me-1"></i>Buscar (Servidor)</button>
      </div>
      <?php if ($q): ?>
      <div class="col-auto">
        <a href="<?= BASE_URL ?>Vista/inventario/index.php" class="btn btn-secondary">
          <i class="bi bi-x-lg me-1"></i>Limpiar
        </a>
      </div>
      <?php endif; ?>
    </form>
  </div>

  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>Código</th><th>Nombre</th>
          <th>Marca</th>
          <th>Condición</th>
          <th class="d-none d-md-table-cell">Categoría</th>
          <th>P. Venta ($)</th>
          <th class="d-none d-xl-table-cell">P. Venta (Bs.)</th>
          <th>Stock</th>
          <th class="d-none d-sm-table-cell">Estado</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($productos)): ?>
          <tr><td colspan="10" class="text-center text-muted py-4">No se encontraron productos</td></tr>
        <?php else: foreach ($productos as $p):
          $critical = $p['stock'] <= $p['stock_minimo'];
        ?>
          <tr class="producto-row">
            <td class="fw-semi text-accent" style="max-width: 120px; word-break: break-all; white-space: normal;" title="<?= htmlspecialchars($p['codigo'] ?? '') ?>">
              <?= !empty($p['codigo']) ? htmlspecialchars(strlen($p['codigo']) > 20 ? substr($p['codigo'], 0, 20) . '...' : $p['codigo']) : '<span class="text-muted fst-italic">S/C</span>' ?>
            </td>
            <td>
              <div><?= htmlspecialchars($p['nombre']) ?></div>
              <?php if (!empty($p['descripcion'])): ?>
              <small class="text-muted"><?= htmlspecialchars(substr($p['descripcion'],0,50)) ?><?= strlen($p['descripcion'])>50?'…':'' ?></small>
              <?php endif; ?>
            </td>
            <td><span class="text-secondary"><?= htmlspecialchars($p['marca'] ?? '—') ?></span></td>
            <td>
              <span class="badge <?= ($p['condicion']??'nuevo')==='nuevo' ? 'badge-success-c bg-opacity-10 text-success' : 'badge-warning-c bg-opacity-10 text-warning' ?>">
                <?= ucfirst($p['condicion'] ?? 'Nuevo') ?>
              </span>
            </td>
            <td class="d-none d-md-table-cell"><?= htmlspecialchars($p['categoria'] ?? '—') ?></td>
            <td class="fw-semi"><?= formatMoney((float)$p['precio_venta']) ?></td>
            <td class="d-none d-xl-table-cell fw-semi text-warning">
              <?php if ($bcvRate && $p['precio_venta'] > 0): ?>
                <?= formatBs((float)$p['precio_venta'] * $bcvRate) ?>
              <?php else: ?>
                <span class="text-muted">—</span>
              <?php endif; ?>
            </td>
            <td>
              <span class="badge <?= $critical ? 'badge-danger-c' : 'badge-success-c' ?>">
                <?= $p['stock'] ?> <?= htmlspecialchars($p['unidad']) ?>
              </span>
            </td>
            <td class="d-none d-sm-table-cell">
              <?= $critical
                ? '<span class="badge badge-warning-c"><i class="bi bi-exclamation-triangle me-1"></i>Bajo</span>'
                : '<span class="badge badge-success-c">OK</span>' ?>
            </td>
            <td>
              <div class="d-flex gap-1 flex-wrap">
                <a href="<?= BASE_URL ?>Vista/inventario/ver.php?id=<?= $p['id'] ?>" class="btn btn-info btn-sm text-white" title="Ver Detalle">
                  <i class="bi bi-eye"></i>
                </a>
                <a href="<?= BASE_URL ?>Vista/inventario/editar.php?id=<?= $p['id'] ?>" class="btn btn-secondary btn-sm" title="Editar">
                  <i class="bi bi-pencil"></i>
                </a>
                <?php if (isAdmin()): ?>
                <a href="#" onclick="confirmDelete('<?= BASE_URL ?>Vista/inventario/index.php?delete=<?= $p['id'] ?>','<?= htmlspecialchars($p['nombre']) ?>')" class="btn btn-danger btn-sm" title="Eliminar">
                  <i class="bi bi-trash"></i>
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

<script>
  document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('liveSearchInventario');
    if (!searchInput) return;
    
    // Prevent default form submission if the user just presses Enter to search fast
    const form = searchInput.closest('form');
    if (form) {
        form.addEventListener('submit', (e) => {
            // Allows the form to be submitted if the search button is clicked explicitly.
        });
    }
    
    searchInput.addEventListener('input', function() {
      const criteria = this.value.toLowerCase().trim();
      document.querySelectorAll('tr.producto-row').forEach(row => {
        // Child 0: Codigo
        // Child 1: Nombre (y descripcion)
        // Child 2: Marca
        // Child 4: Categoria
        const codigo = row.children[0]?.textContent.toLowerCase() || '';
        const nombre = row.children[1]?.textContent.toLowerCase() || '';
        const marca = row.children[2]?.textContent.toLowerCase() || '';
        const categor = row.children[4]?.textContent.toLowerCase() || '';
        
        if (codigo.includes(criteria) || nombre.includes(criteria) || marca.includes(criteria) || categor.includes(criteria)) {
          row.style.display = '';
        } else {
          row.style.display = 'none';
        }
      });
    });
  });
</script>

<?php include ROOT . '/Vista/Includes/footer.php'; ?>
