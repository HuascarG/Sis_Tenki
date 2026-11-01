<?php
// Vista/clientes/ver.php
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/Config/database.php';
require_once ROOT . '/Config/config.php';
require_once ROOT . '/Controlador/ClienteController.php';

$ctrl = new ClienteController();
$id   = (int)($_GET['id'] ?? 0);
$cliente = $ctrl->getById($id);

if (!$cliente) {
    setFlash('danger', 'Cliente no encontrado.');
    redirect('Vista/clientes/index.php');
}

$pageTitle = 'Detalle del Cliente';
include ROOT . '/Vista/Includes/header.php';
?>

<div class="row justify-content-center">
<div class="col-12 col-lg-8">
<div class="card">
  <div class="card-header">
    <span class="card-title">
      <i class="bi bi-person-lines-fill me-2"></i>Ficha del Cliente
    </span>
    <div>
      <a href="<?= BASE_URL ?>Vista/clientes/crear.php?edit=<?= $cliente['id'] ?>" class="btn btn-secondary btn-sm me-1">
        <i class="bi bi-pencil me-1"></i>Editar
      </a>
      <a href="<?= BASE_URL ?>Vista/clientes/index.php" class="btn btn-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Volver
      </a>
    </div>
  </div>
  <div class="card-body">
    
    <div class="d-flex align-items-center mb-4 pb-3 border-bottom">
      <div class="stat-icon blue me-3" style="width:60px;height:60px;font-size:30px;">
        <i class="bi bi-person"></i>
      </div>
      <div>
        <h4 class="mb-1 text-primary fw-bold"><?= htmlspecialchars($cliente['nombre']) ?></h4>
        <span class="badge badge-info-c fs-6"><?= htmlspecialchars($cliente['tipo_documento']) ?>-<?= htmlspecialchars($cliente['documento']) ?></span>
      </div>
    </div>

    <div class="row g-4">
      <div class="col-12 col-md-6">
        <div class="fw-semi text-uppercase text-muted" style="font-size: 11px; letter-spacing: 1px;">Contacto Principal</div>
        <div class="fs-5 mt-1"><?= htmlspecialchars($cliente['telefono'] ?: 'No registrado') ?></div>
      </div>
      <div class="col-12 col-md-6">
        <div class="fw-semi text-uppercase text-muted" style="font-size: 11px; letter-spacing: 1px;">Correo Electrónico</div>
        <div class="fs-6 mt-1"><?= htmlspecialchars($cliente['email'] ?: 'No registrado') ?></div>
      </div>

      <div class="col-12">
        <div class="fw-semi text-uppercase text-muted" style="font-size: 11px; letter-spacing: 1px;">Referencia / Notas</div>
        <div class="p-3 mt-2 rounded bg-light" style="min-height:60px; border: 1px solid var(--border);">
          <?= nl2br(htmlspecialchars($cliente['referencia'] ?: 'No hay referencias registradas.')) ?>
        </div>
      </div>

      <div class="col-12">
        <div class="fw-semi text-uppercase text-muted" style="font-size: 11px; letter-spacing: 1px;">Dirección Física</div>
        <div class="mt-1 pb-2">
          <?= nl2br(htmlspecialchars($cliente['direccion'] ?: 'No registrada')) ?>
        </div>
      </div>
      
    </div>

  </div>
  <div class="card-footer bg-white border-top py-3 text-end">
    <small class="text-muted">Registro creado el: <?= date('d/m/Y h:i A', strtotime($cliente['created_at'])) ?></small>
  </div>
</div>
</div>
</div>

<?php include ROOT . '/Vista/Includes/footer.php'; ?>
