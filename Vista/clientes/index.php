<?php
// Vista/clientes/index.php
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/Config/database.php';
require_once ROOT . '/Config/config.php';
require_once ROOT . '/Config/auth.php';
require_once ROOT . '/Controlador/ClienteController.php';

$ctrl = new ClienteController();

// Handle delete BEFORE output
if (isset($_GET['delete'])) {
    requireAdmin();
    $ctrl->eliminar((int)$_GET['delete']);
    setFlash('success', 'Cliente eliminado.');
    redirect('Vista/clientes/index.php');
}

$clientes  = $ctrl->listar();
$pageTitle = 'Clientes';
include ROOT . '/Vista/Includes/header.php';
?>

<div class="card">
  <div class="card-header">
    <span class="card-title"><i class="bi bi-people me-2"></i>Clientes</span>
    <a href="<?= BASE_URL ?>Vista/clientes/crear.php" class="btn btn-primary btn-sm">
      <i class="bi bi-plus-lg me-1"></i>Nuevo Cliente
    </a>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>Tipo</th><th>Documento</th><th>Nombre</th>
          <th class="d-none d-md-table-cell">Teléfono</th>
          <th class="d-none d-lg-table-cell">Email</th>
          <th class="d-none d-xl-table-cell">Referencia</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($clientes)): ?>
          <tr><td colspan="6" class="text-center text-muted py-4">Sin clientes registrados</td></tr>
        <?php else: foreach ($clientes as $c): ?>
          <tr>
            <td><span class="badge badge-info-c"><?= htmlspecialchars($c['tipo_documento']) ?></span></td>
            <td class="fw-semi"><?= htmlspecialchars($c['documento']) ?></td>
            <td><?= htmlspecialchars($c['nombre']) ?></td>
            <td class="d-none d-md-table-cell text-muted"><?= htmlspecialchars($c['telefono'] ?? '—') ?></td>
            <td class="d-none d-lg-table-cell text-muted"><?= htmlspecialchars($c['email'] ?? '—') ?></td>
            <td class="d-none d-xl-table-cell text-muted small">
              <?= htmlspecialchars($c['referencia'] ? (strlen($c['referencia']) > 30 ? substr($c['referencia'],0,28).'...' : $c['referencia']) : '—') ?>
            </td>
            <td>
              <div class="d-flex gap-1">
                <a href="<?= BASE_URL ?>Vista/clientes/ver.php?id=<?= $c['id'] ?>" class="btn btn-info btn-sm text-white" title="Ver Detalles">
                  <i class="bi bi-eye"></i>
                </a>
                <a href="<?= BASE_URL ?>Vista/clientes/crear.php?edit=<?= $c['id'] ?>" class="btn btn-secondary btn-sm" title="Editar">
                  <i class="bi bi-pencil"></i>
                </a>
              <?php if (isAdmin()): ?>
                <a href="#" onclick="confirmDelete('<?= BASE_URL ?>Vista/clientes/index.php?delete=<?= $c['id'] ?>','<?= htmlspecialchars($c['nombre'], ENT_QUOTES) ?>')" class="btn btn-danger btn-sm" title="Eliminar">
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

<?php include ROOT . '/Vista/Includes/footer.php'; ?>
