<?php
// Vista/ventas/index.php
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/Config/database.php';
require_once ROOT . '/Config/config.php';
require_once ROOT . '/Config/auth.php';
require_once ROOT . '/Controlador/VentaController.php';

$ctrl = new VentaController();

// Handle annul BEFORE output
if (isset($_GET['anular'])) {
    requireAdmin();
    if ($ctrl->anular((int)$_GET['anular'])) {
        setFlash('success', 'Venta anulada y stock restaurado.');
    } else {
        setFlash('danger', 'No se pudo anular la venta.');
    }
    redirect('Vista/ventas/index.php');
}

$filtros = [
    'f_desde' => $_GET['f_desde'] ?? '',
    'f_hasta' => $_GET['f_hasta'] ?? '',
    'estado'  => $_GET['estado']  ?? ''
];
$ventas = $ctrl->listar($filtros);
$pageTitle = 'Ventas';
include ROOT . '/Vista/Includes/header.php';
?>

<div class="card">
  <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
    <span class="card-title mb-0"><i class="bi bi-cart3 me-2"></i>Registro de Ventas</span>
    <div class="d-flex flex-wrap gap-2">
      <div class="input-group input-group-sm rounded-pill border overflow-hidden p-0 me-2" style="width:250px; background:var(--surface);">
        <span class="input-group-text border-0 bg-transparent text-muted"><i class="bi bi-search"></i></span>
        <input type="text" id="liveSearchVentas" class="form-control border-0 shadow-none bg-transparent" placeholder="Buscar factura o cliente...">
      </div>
      <a href="<?= BASE_URL ?>Vista/ventas/nueva.php" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg me-1"></i>Nueva Venta
      </a>
    </div>
  </div>

  <div class="card-body border-bottom bg-light py-3">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-12 col-sm-6 col-md-3">
        <label class="form-label mb-1" style="font-size:0.8rem">Desde</label>
        <input type="date" name="f_desde" class="form-control form-control-sm" value="<?= htmlspecialchars($filtros['f_desde']) ?>">
      </div>
      <div class="col-12 col-sm-6 col-md-3">
        <label class="form-label mb-1" style="font-size:0.8rem">Hasta</label>
        <input type="date" name="f_hasta" class="form-control form-control-sm" value="<?= htmlspecialchars($filtros['f_hasta']) ?>">
      </div>
      <div class="col-12 col-md-3">
        <label class="form-label mb-1" style="font-size:0.8rem">Estado</label>
        <select name="estado" class="form-select form-select-sm">
          <option value="">Todos los Estados</option>
          <option value="completada" <?= $filtros['estado'] === 'completada' ? 'selected' : '' ?>>Completada</option>
          <option value="credito" <?= $filtros['estado'] === 'credito' ? 'selected' : '' ?>>Crédito</option>
          <option value="pendiente" <?= $filtros['estado'] === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
          <option value="anulada" <?= $filtros['estado'] === 'anulada' ? 'selected' : '' ?>>Anulada</option>
        </select>
      </div>
      <div class="col-12 col-md-3">
        <div class="d-flex gap-1 w-100">
          <button type="submit" class="btn btn-primary btn-sm flex-grow-1"><i class="bi bi-funnel me-1"></i>Filtrar</button>
          <a href="<?= BASE_URL ?>Vista/ventas/index.php" class="btn btn-secondary btn-sm" title="Limpiar"><i class="bi bi-arrow-counterclockwise"></i></a>
        </div>
      </div>
    </form>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>#Venta</th><th>Cliente</th>
          <th class="d-none d-md-table-cell">Fecha</th>
          <th class="d-none d-lg-table-cell">Subtotal</th>
          <th class="d-none d-lg-table-cell">IVA</th>
          <th>Total</th>
          <th class="d-none d-md-table-cell">Moneda</th>
          <th class="d-none d-lg-table-cell">Pago</th>
          <th>Estado</th><th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($ventas)): ?>
          <tr><td colspan="10" class="text-center text-muted py-4">Sin ventas registradas</td></tr>
        <?php else: foreach ($ventas as $v):
          $metodoLabels = [
            'efectivo_usd'=>'💵 Efectivo $','binance'=>'🟡 Binance','zelle'=>'🟢 Zelle','zinli'=>'🟣 Zinli',
            'efectivo_bs'=>'💵 Efectivo Bs','transferencia'=>'🏦 Transf.','punto'=>'💳 Punto','pago_movil'=>'📱 P. Móvil',
            'credito'=>'🔄 Crédito','efectivo'=>'💵 Efectivo','mixto'=>'🔄 Mixto'
          ];
          $monV = $v['moneda'] ?? 'USD';
          $metV = $v['metodo_pago'] ?? 'efectivo_usd';
          $metLabel = $metodoLabels[$metV] ?? ucfirst($metV);
        ?>
          <tr class="venta-row" data-productos="<?= htmlspecialchars(strtolower($v['productos_comprados'] ?? ''), ENT_QUOTES) ?>">
            <td class="fw-semi text-accent"><?= htmlspecialchars($v['numero_venta']) ?></td>
            <td><?= htmlspecialchars($v['cliente_nombre'] ?? 'Cliente general') ?></td>
            <td class="d-none d-md-table-cell"><?= date('d/m/Y', strtotime($v['fecha'])) ?></td>
            <td class="d-none d-lg-table-cell"><?= formatMoney((float)$v['subtotal']) ?></td>
            <td class="d-none d-lg-table-cell"><?= formatMoney((float)$v['iva']) ?></td>
            <td class="fw-semi"><?= formatMoney((float)$v['total']) ?></td>
            <td class="d-none d-md-table-cell">
              <span class="badge <?= $monV === 'BS' ? 'badge-warning-c' : 'badge-success-c' ?>" style="font-size:.7rem;"><?= $monV ?></span>
            </td>
            <td class="d-none d-lg-table-cell">
              <span class="badge badge-info-c" style="font-size:.7rem;"><?= $metLabel ?></span>
            </td>
            <td>
              <span class="badge <?= $v['estado']==='completada'?'badge-success-c':($v['estado']==='anulada'?'badge-danger-c':($v['estado']==='credito'?'badge-credit-c':'badge-warning-c')) ?>">
                <?= $v['estado']==='credito' ? '<i class="bi bi-credit-card me-1"></i>' : '' ?><?= ucfirst($v['estado']) ?>
              </span>
            </td>
            <td>
              <div class="d-flex gap-1 flex-wrap">
                <a href="<?= BASE_URL ?>Vista/ventas/detalle.php?id=<?= $v['id'] ?>" class="btn btn-secondary btn-sm" title="Ver detalle">
                  <i class="bi bi-eye"></i>
                </a>
                <a href="<?= BASE_URL ?>Vista/facturacion/ver.php?venta_id=<?= $v['id'] ?>" class="btn btn-success btn-sm" title="Factura">
                  <i class="bi bi-file-earmark-text"></i>
                </a>
                <?php if ($v['estado'] === 'credito'): ?>
                <a href="<?= BASE_URL ?>Vista/creditos/index.php?venta_id=<?= $v['id'] ?>" class="btn btn-sm" style="background:rgba(124,58,237,.1);color:#7c3aed;" title="Ver Crédito">
                  <i class="bi bi-credit-card-2-front"></i>
                </a>
                <?php endif; ?>
                <?php if ($v['estado'] === 'completada' && isAdmin()): ?>
                <a href="#" onclick="confirmDelete('<?= BASE_URL ?>Vista/ventas/index.php?anular=<?= $v['id'] ?>','venta <?= htmlspecialchars($v['numero_venta']) ?>')" class="btn btn-danger btn-sm" title="Anular">
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

<script>
  document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('liveSearchVentas');
    if (!searchInput) return;
    
    searchInput.addEventListener('input', function() {
      const criteria = this.value.toLowerCase().trim();
      document.querySelectorAll('tr.venta-row').forEach(row => {
        // Number is in first td, Client in second td
        const number = row.children[0].textContent.toLowerCase();
        const client = row.children[1].textContent.toLowerCase();
        const prods  = row.dataset.productos || "";
        
        if (number.includes(criteria) || client.includes(criteria) || prods.includes(criteria)) {
          row.style.display = '';
        } else {
          row.style.display = 'none';
        }
      });
    });
  });
</script>

<?php include ROOT . '/Vista/Includes/footer.php'; ?>
