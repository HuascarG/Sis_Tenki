<?php
// Vista/creditos/index.php — Credit management dashboard
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/Config/database.php';
require_once ROOT . '/Config/config.php';
require_once ROOT . '/Config/auth.php';
require_once ROOT . '/Controlador/CreditoController.php';

requireLogin();
$ctrl = new CreditoController();
$resumen = $ctrl->resumen();

$filtros = [
    'estado'    => $_GET['estado'] ?? '',
    'cliente_id'=> $_GET['cliente_id'] ?? '',
    'f_desde'   => $_GET['f_desde'] ?? '',
    'f_hasta'   => $_GET['f_hasta'] ?? '',
];

// If coming from ventas with venta_id, find the credit
if (!empty($_GET['venta_id'])) {
    $creditoByVenta = $ctrl->getByVentaId((int)$_GET['venta_id']);
    if ($creditoByVenta) {
        header('Location: ' . BASE_URL . 'Vista/creditos/ver.php?id=' . $creditoByVenta['id']);
        exit;
    }
}

$creditos = $ctrl->listar($filtros);
$pageTitle = 'Créditos';
include ROOT . '/Vista/Includes/header.php';
?>

<!-- STATS -->
<div class="stats-grid mb-4" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(124,58,237,.12);color:#7c3aed;"><i class="bi bi-cash-stack"></i></div>
    <div>
      <div class="stat-label">Por Cobrar</div>
      <div class="stat-value"><?= formatMoney($resumen['total_por_cobrar']) ?></div>
      <div class="stat-sub">deuda total activa</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(245,158,11,.12);color:#f59e0b;"><i class="bi bi-credit-card-2-front"></i></div>
    <div>
      <div class="stat-label">Créditos Activos</div>
      <div class="stat-value"><?= $resumen['creditos_activos'] ?></div>
      <div class="stat-sub">pendientes y parciales</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green"><i class="bi bi-coin"></i></div>
    <div>
      <div class="stat-label">Cobrado este Mes</div>
      <div class="stat-value"><?= formatMoney($resumen['cobrado_mes']) ?></div>
      <div class="stat-sub">en abonos recibidos</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon blue"><i class="bi bi-people"></i></div>
    <div>
      <div class="stat-label">Clientes con Deuda</div>
      <div class="stat-value"><?= $resumen['clientes_con_deuda'] ?></div>
      <div class="stat-sub">con crédito activo</div>
    </div>
  </div>
</div>

<!-- CREDITOS TABLE -->
<div class="card">
  <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
    <span class="card-title mb-0"><i class="bi bi-credit-card-2-front me-2"></i>Gestión de Créditos</span>
    <div class="input-group input-group-sm rounded-pill border overflow-hidden p-0" style="width:250px; background:var(--surface);">
      <span class="input-group-text border-0 bg-transparent text-muted"><i class="bi bi-search"></i></span>
      <input type="text" id="liveSearchCreditos" class="form-control border-0 shadow-none bg-transparent" placeholder="Buscar cliente o venta...">
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
          <option value="">Todos</option>
          <option value="pendiente" <?= $filtros['estado'] === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
          <option value="parcial" <?= $filtros['estado'] === 'parcial' ? 'selected' : '' ?>>Parcial</option>
          <option value="pagado" <?= $filtros['estado'] === 'pagado' ? 'selected' : '' ?>>Pagado</option>
        </select>
      </div>
      <div class="col-12 col-md-3">
        <div class="d-flex gap-1 w-100">
          <button type="submit" class="btn btn-primary btn-sm flex-grow-1"><i class="bi bi-funnel me-1"></i>Filtrar</button>
          <a href="<?= BASE_URL ?>Vista/creditos/index.php" class="btn btn-secondary btn-sm" title="Limpiar"><i class="bi bi-arrow-counterclockwise"></i></a>
        </div>
      </div>
    </form>
  </div>

  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>Venta</th><th>Cliente</th>
          <th class="d-none d-md-table-cell">Fecha</th>
          <th>Total</th><th>Pagado</th><th>Pendiente</th>
          <th>Progreso</th><th>Estado</th><th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($creditos)): ?>
          <tr><td colspan="9" class="text-center text-muted py-4">Sin créditos registrados</td></tr>
        <?php else: foreach ($creditos as $cr): ?>
          <?php
            $pct = $cr['monto_total'] > 0 ? round(($cr['monto_pagado'] / $cr['monto_total']) * 100) : 0;
            $vencido = !empty($cr['fecha_limite']) && $cr['fecha_limite'] < date('Y-m-d') && $cr['estado'] !== 'pagado';
          ?>
          <tr class="credito-row">
            <td class="fw-semi text-accent"><?= htmlspecialchars($cr['numero_venta']) ?></td>
            <td>
              <?= htmlspecialchars($cr['cliente_nombre']) ?>
              <br><small class="text-muted"><?= htmlspecialchars($cr['cliente_doc']) ?></small>
            </td>
            <td class="d-none d-md-table-cell"><?= date('d/m/Y', strtotime($cr['fecha_venta'])) ?></td>
            <td><?= formatMoney((float)$cr['monto_total']) ?></td>
            <td class="text-success fw-semi"><?= formatMoney((float)$cr['monto_pagado']) ?></td>
            <td class="fw-bold <?= $vencido ? 'text-danger' : '' ?>"><?= formatMoney((float)$cr['monto_pendiente']) ?></td>
            <td style="min-width:120px;">
              <div class="credit-progress-bar">
                <div class="credit-progress-fill" style="width:<?= $pct ?>%;background:<?= $pct >= 100 ? '#16a34a' : ($pct > 0 ? '#f59e0b' : '#e5e7eb') ?>;"></div>
              </div>
              <small class="text-muted d-block text-center" style="font-size:.7rem"><?= $pct ?>%</small>
            </td>
            <td>
              <?php if ($cr['estado'] === 'pagado'): ?>
                <span class="badge badge-success-c"><i class="bi bi-check-circle me-1"></i>Pagado</span>
              <?php elseif ($cr['estado'] === 'parcial'): ?>
                <span class="badge badge-credit-c"><i class="bi bi-clock-history me-1"></i>Parcial</span>
              <?php else: ?>
                <span class="badge badge-warning-c"><?= $vencido ? '<i class="bi bi-exclamation-triangle me-1"></i>Vencido' : '<i class="bi bi-hourglass-split me-1"></i>Pendiente' ?></span>
              <?php endif; ?>
            </td>
            <td>
              <a href="<?= BASE_URL ?>Vista/creditos/ver.php?id=<?= $cr['id'] ?>" class="btn btn-sm" style="background:rgba(124,58,237,.1);color:#7c3aed;" title="Ver / Abonar">
                <i class="bi bi-eye"></i>
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
  const si = document.getElementById('liveSearchCreditos');
  if (!si) return;
  si.addEventListener('input', function() {
    const q = this.value.toLowerCase().trim();
    document.querySelectorAll('tr.credito-row').forEach(row => {
      const text = row.textContent.toLowerCase();
      row.style.display = text.includes(q) ? '' : 'none';
    });
  });
});
</script>

<?php include ROOT . '/Vista/Includes/footer.php'; ?>
