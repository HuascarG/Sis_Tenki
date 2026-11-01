<?php
// Vista/proveedores/index.php
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/Config/database.php';
require_once ROOT . '/Config/config.php';
require_once ROOT . '/Config/auth.php';
require_once ROOT . '/Controlador/ProveedorController.php';

$ctrl = new ProveedorController();

// Handle delete BEFORE output
if (isset($_GET['delete'])) {
    requireAdmin();
    $ctrl->eliminar((int)$_GET['delete']);
    setFlash('success', 'Proveedor eliminado.');
    redirect('Vista/proveedores/index.php');
}

$proveedores = $ctrl->listar();
$pageTitle   = 'Proveedores';
include ROOT . '/Vista/Includes/header.php';
?>

<div class="card">
  <div class="card-header">
    <span class="card-title"><i class="bi bi-building me-2"></i>Proveedores</span>
    <a href="<?= BASE_URL ?>Vista/proveedores/crear.php" class="btn btn-primary btn-sm">
      <i class="bi bi-plus-lg me-1"></i>Nuevo Proveedor
    </a>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>RIF</th><th>Nombre</th>
          <th class="d-none d-md-table-cell">Teléfono</th>
          <th class="d-none d-lg-table-cell">Email</th>
          <th class="d-none d-lg-table-cell">Contacto</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($proveedores)): ?>
          <tr><td colspan="6" class="text-center text-muted py-4">Sin proveedores registrados</td></tr>
        <?php else: foreach ($proveedores as $p): ?>
          <tr>
            <td class="fw-semi text-accent"><?= htmlspecialchars($p['rif']) ?></td>
            <td><?= htmlspecialchars($p['nombre']) ?></td>
            <td class="d-none d-md-table-cell text-muted"><?= htmlspecialchars($p['telefono'] ?? '—') ?></td>
            <td class="d-none d-lg-table-cell text-muted"><?= htmlspecialchars($p['email'] ?? '—') ?></td>
            <td class="d-none d-lg-table-cell text-muted"><?= htmlspecialchars($p['contacto'] ?? '—') ?></td>
            <td>
              <div class="d-flex gap-1">
                <a href="<?= BASE_URL ?>Vista/proveedores/crear.php?edit=<?= $p['id'] ?>" class="btn btn-secondary btn-sm">
                  <i class="bi bi-pencil"></i>
                </a>
              <?php if (isAdmin()): ?>
                <a href="#" onclick="confirmDelete('<?= BASE_URL ?>Vista/proveedores/index.php?delete=<?= $p['id'] ?>','<?= htmlspecialchars($p['nombre'], ENT_QUOTES) ?>')" class="btn btn-danger btn-sm">
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
