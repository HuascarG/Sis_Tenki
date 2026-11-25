<?php
// Vista/caja/historial.php
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/Config/database.php';
require_once ROOT . '/Config/config.php';
require_once ROOT . '/Config/auth.php';
require_once ROOT . '/Controlador/CajaController.php';

$cajaCtrl   = new CajaController();
$page       = max(1, (int)($_GET['page'] ?? 1));
$perPage    = 15;
$paginacion = $cajaCtrl->historial($page, $perPage);
$cajas      = $paginacion['items'];

$pageTitle = 'Historial de Cajas';
include ROOT . '/Vista/Includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
  <div>
    <h3 class="mb-1 text-dark fw-bold"><i class="bi bi-clock-history me-2 text-primary"></i>Historial de Cajas Cerradas</h3>
    <p class="text-muted mb-0">Auditoría de turnos cerrados, recaudación y arqueos pasados.</p>
  </div>
  <div>
    <a href="<?= BASE_URL ?>Vista/caja/index.php" class="btn btn-primary btn-sm">
      <i class="bi bi-cash-coin me-1"></i>Ir a Caja Actual
    </a>
  </div>
</div>

<div class="card border-0 shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0" style="font-size:0.875rem;">
      <thead class="table-light">
        <tr>
          <th>#Turno</th>
          <th>Apertura</th>
          <th>Cierre</th>
          <th>Responsable</th>
          <th class="text-end">Efectivo Final ($)</th>
          <th class="text-end">Efectivo Final (Bs)</th>
          <th>Diferencias</th>
          <th class="text-center">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($cajas)): ?>
          <tr><td colspan="8" class="text-center text-muted py-4">No hay turnos de caja cerrados registrados</td></tr>
        <?php else: foreach ($cajas as $c):
          $difUsd = (float)$c['diferencia_usd'];
          $difBs  = (float)$c['diferencia_bs'];
        ?>
          <tr>
            <td class="fw-bold text-accent">#<?= $c['id'] ?></td>
            <td>
              <div><?= date('d/m/Y', strtotime($c['fecha_apertura'])) ?></div>
              <small class="text-muted"><?= date('h:i A', strtotime($c['fecha_apertura'])) ?></small>
            </td>
            <td>
              <?php if ($c['fecha_cierre']): ?>
                <div><?= date('d/m/Y', strtotime($c['fecha_cierre'])) ?></div>
                <small class="text-muted"><?= date('h:i A', strtotime($c['fecha_cierre'])) ?></small>
              <?php else: ?>
                <span class="badge bg-warning text-dark">Abierta</span>
              <?php endif; ?>
            </td>
            <td>
              <div class="fw-semi text-dark"><?= htmlspecialchars($c['usuario_cierre'] ?? $c['usuario_apertura'] ?? '') ?></div>
              <small class="text-muted">Apertura: <?= htmlspecialchars($c['usuario_apertura'] ?? '') ?></small>
            </td>
            <td class="text-end fw-bold text-success">
              $ <?= number_format((float)$c['monto_final_usd'], 2, ',', '.') ?>
            </td>
            <td class="text-end fw-bold text-primary">
              Bs. <?= number_format((float)$c['monto_final_bs'], 2, ',', '.') ?>
            </td>
            <td>
              <?php if ($difUsd == 0 && $difBs == 0): ?>
                <span class="badge bg-success-subtle text-success"><i class="bi bi-check2-circle me-1"></i>Cuadre Exacto</span>
              <?php else: ?>
                <?php if ($difUsd != 0): ?>
                  <span class="badge <?= $difUsd > 0 ? 'bg-info-subtle text-info' : 'bg-danger-subtle text-danger' ?>" style="font-size:0.75rem;">
                    <?= $difUsd > 0 ? 'Sobró' : 'Faltó' ?> $ <?= number_format(abs($difUsd), 2) ?>
                  </span>
                <?php endif; ?>
                <?php if ($difBs != 0): ?>
                  <span class="badge <?= $difBs > 0 ? 'bg-info-subtle text-info' : 'bg-danger-subtle text-danger' ?>" style="font-size:0.75rem;">
                    <?= $difBs > 0 ? 'Sobró' : 'Faltó' ?> Bs. <?= number_format(abs($difBs), 2) ?>
                  </span>
                <?php endif; ?>
              <?php endif; ?>
            </td>
            <td class="text-center">
              <a href="<?= BASE_URL ?>Vista/caja/ver.php?id=<?= $c['id'] ?>" class="btn btn-secondary btn-sm" title="Ver Arqueo / Imprimir Corte Z">
                <i class="bi bi-eye me-1"></i>Ver Corte
              </a>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <!-- PAGINACIÓN -->
  <?php if ($paginacion['total'] > 0): ?>
  <div class="card-footer bg-transparent d-flex flex-wrap justify-content-between align-items-center py-3 px-4 border-top gap-2">
    <div class="text-muted small">
      Mostrando <span class="fw-semi text-dark"><?= $paginacion['from'] ?></span> a <span class="fw-semi text-dark"><?= $paginacion['to'] ?></span> de <span class="fw-semi text-dark"><?= $paginacion['total'] ?></span> turnos
    </div>
    <?php if ($paginacion['total_pages'] > 1): ?>
    <nav aria-label="Paginación de cajas">
      <ul class="pagination pagination-sm mb-0">
        <li class="page-item <?= $paginacion['page'] <= 1 ? 'disabled' : '' ?>">
          <a class="page-link" href="?page=<?= $paginacion['page'] - 1 ?>"><i class="bi bi-chevron-left"></i></a>
        </li>
        <?php for ($i = 1; $i <= $paginacion['total_pages']; $i++): ?>
        <li class="page-item <?= $i === $paginacion['page'] ? 'active' : '' ?>">
          <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
        </li>
        <?php endfor; ?>
        <li class="page-item <?= $paginacion['page'] >= $paginacion['total_pages'] ? 'disabled' : '' ?>">
          <a class="page-link" href="?page=<?= $paginacion['page'] + 1 ?>"><i class="bi bi-chevron-right"></i></a>
        </li>
      </ul>
    </nav>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<?php include ROOT . '/Vista/Includes/footer.php'; ?>

