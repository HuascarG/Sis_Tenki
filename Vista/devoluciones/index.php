<?php
// Vista/devoluciones/index.php
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/Config/database.php';
require_once ROOT . '/Config/config.php';
require_once ROOT . '/Config/auth.php';
requireLogin();

require_once ROOT . '/Controlador/DevolucionController.php';

$ctrl = new DevolucionController();
$filtros = [
    'f_desde'    => $_GET['f_desde'] ?? '',
    'f_hasta'    => $_GET['f_hasta'] ?? '',
    'resolucion' => $_GET['resolucion'] ?? '',
];

$devoluciones = $ctrl->listar($filtros);

$pageTitle = 'Garantías y Devoluciones';
include ROOT . '/Vista/Includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
  <div>
    <h1 class="page-title mb-0"><i class="bi bi-arrow-left-right me-2 text-warning"></i>Garantías y Devoluciones</h1>
    <p class="text-muted small mb-0">Gestión de reclamos de repuestos, notas de crédito y reingresos a stock</p>
  </div>
  <a href="<?= BASE_URL ?>Vista/devoluciones/nueva.php" class="btn btn-primary">
    <i class="bi bi-plus-lg me-1"></i>Registrar Devolución / Garantía
  </a>
</div>

<!-- Filtros -->
<div class="card mb-4">
  <div class="card-body py-3">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-12 col-sm-6 col-md-3">
        <label class="form-label small text-muted mb-1">Desde</label>
        <input type="date" name="f_desde" class="form-control form-control-sm" value="<?= htmlspecialchars($filtros['f_desde']) ?>">
      </div>
      <div class="col-12 col-sm-6 col-md-3">
        <label class="form-label small text-muted mb-1">Hasta</label>
        <input type="date" name="f_hasta" class="form-control form-control-sm" value="<?= htmlspecialchars($filtros['f_hasta']) ?>">
      </div>
      <div class="col-12 col-sm-6 col-md-3">
        <label class="form-label small text-muted mb-1">Resolución</label>
        <select name="resolucion" class="form-select form-select-sm">
          <option value="">Todas las resoluciones</option>
          <option value="nota_credito" <?= $filtros['resolucion']==='nota_credito'?'selected':'' ?>>Nota de Crédito (Saldo a favor)</option>
          <option value="cambio_producto" <?= $filtros['resolucion']==='cambio_producto'?'selected':'' ?>>Cambio de Producto</option>
          <option value="reembolso_dinero" <?= $filtros['resolucion']==='reembolso_dinero'?'selected':'' ?>>Reembolso de Dinero</option>
          <option value="solo_ingreso_garantia" <?= $filtros['resolucion']==='solo_ingreso_garantia'?'selected':'' ?>>En Revisión / Proveedor</option>
        </select>
      </div>
      <div class="col-12 col-sm-6 col-md-3 d-flex gap-2">
        <button type="submit" class="btn btn-secondary btn-sm flex-fill"><i class="bi bi-filter me-1"></i>Filtrar</button>
        <a href="<?= BASE_URL ?>Vista/devoluciones/index.php" class="btn btn-outline-secondary btn-sm" title="Limpiar"><i class="bi bi-arrow-counterclockwise"></i></a>
      </div>
    </form>
  </div>
</div>

<!-- Listado -->
<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>N° Devolución</th>
            <th>Venta de Origen</th>
            <th>Cliente</th>
            <th>Fecha</th>
            <th>Resolución</th>
            <th class="text-center">Ítems</th>
            <th class="text-end">Monto Total</th>
            <th class="text-center">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($devoluciones)): ?>
            <tr>
              <td colspan="8" class="text-center py-5 text-muted">
                <i class="bi bi-inbox fs-2 d-block mb-2 text-secondary"></i>
                No se han registrado devoluciones ni garantías en el período seleccionado.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($devoluciones as $d): ?>
              <?php
                $resBadges = [
                  'nota_credito' => ['badge-success-c', '<i class="bi bi-card-checklist me-1"></i>Nota de Crédito'],
                  'cambio_producto' => ['badge-info-c', '<i class="bi bi-arrow-repeat me-1"></i>Cambio Repuesto'],
                  'reembolso_dinero' => ['badge-warning-c', '<i class="bi bi-cash-stack me-1"></i>Reembolso'],
                  'solo_ingreso_garantia' => ['badge-danger-c', '<i class="bi bi-wrench-adjustable me-1"></i>En Revisión']
                ];
                $badgeInfo = $resBadges[$d['tipo_resolucion']] ?? ['badge-secondary', $d['tipo_resolucion']];
              ?>
              <tr>
                <td class="fw-bold text-accent">
                  <?= htmlspecialchars($d['numero_devolucion']) ?>
                </td>
                <td>
                  <a href="<?= BASE_URL ?>Vista/ventas/detalle.php?id=<?= $d['venta_id'] ?>" class="text-decoration-none fw-semi">
                    <?= htmlspecialchars($d['numero_venta']) ?>
                  </a>
                </td>
                <td>
                  <div class="fw-semi"><?= htmlspecialchars($d['cliente_nombre'] ?? 'Cliente General') ?></div>
                  <?php if (!empty($d['cliente_doc'])): ?>
                    <small class="text-muted"><?= htmlspecialchars($d['cliente_doc']) ?></small>
                  <?php endif; ?>
                </td>
                <td><?= date('d/m/Y', strtotime($d['fecha'])) ?></td>
                <td>
                  <span class="badge <?= $badgeInfo[0] ?>"><?= $badgeInfo[1] ?></span>
                </td>
                <td class="text-center">
                  <span class="badge bg-light text-dark border"><?= $d['total_items'] ?> repuesto(s)</span>
                </td>
                <td class="text-end fw-bold text-dark">
                  $<?= number_format((float)$d['monto_total'], 2, ',', '.') ?>
                </td>
                <td class="text-center">
                  <a href="<?= BASE_URL ?>Vista/devoluciones/ver.php?id=<?= $d['id'] ?>" class="btn btn-icon btn-sm" title="Ver Comprobante">
                    <i class="bi bi-eye"></i>
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include ROOT . '/Vista/Includes/footer.php'; ?>
