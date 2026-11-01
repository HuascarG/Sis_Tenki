<?php
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/Config/database.php';
require_once ROOT . '/Config/config.php';
require_once ROOT . '/Config/auth.php';
require_once ROOT . '/Controlador/UsuarioController.php';
require_once ROOT . '/Controlador/AuthController.php';

requireAdmin();
$ctrl = new UsuarioController();

if (isset($_GET['delete'])) {
    requireAdmin();
    if (!$ctrl->eliminar((int)$_GET['delete'])) {
        setFlash('danger', 'No se puede eliminar este usuario.');
    } else {
        setFlash('success', 'Usuario eliminado.');
    }
    redirect('Vista/usuarios/index.php');
}

if (isset($_GET['toggle'])) {
    requireAdmin();
    $u = $ctrl->getById((int)$_GET['toggle']);
    if ($u) {
        $ctrl->cambiarEstado($u['id'], $u['activo'] ? 0 : 1);
        setFlash('success', 'Estado del usuario actualizado.');
    }
    redirect('Vista/usuarios/index.php');
}

$usuarios  = $ctrl->listar();
$pageTitle = 'Usuarios';
include ROOT . '/Vista/Includes/header.php';
?>

<div class="card">
  <div class="card-header">
    <span class="card-title"><i class="bi bi-people me-2"></i>Gestión de Usuarios</span>
    <a href="<?= BASE_URL ?>Vista/usuarios/crear.php" class="btn btn-primary btn-sm">
      <i class="bi bi-person-plus me-1"></i>Nuevo Usuario
    </a>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>Nombre</th>
          <th>Cédula</th>
          <th class="d-none d-md-table-cell">Correo</th>
          <th class="d-none d-sm-table-cell">Teléfono</th>
          <th>Rol</th>
          <th>Estado</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($usuarios)): ?>
          <tr><td colspan="7" class="text-center text-muted py-4">Sin usuarios registrados</td></tr>
        <?php else: foreach ($usuarios as $u):
          $isSelf = ($u['id'] === (int)($_SESSION['user_id'] ?? 0));
        ?>
          <tr>
            <td>
              <div class="fw-semi"><?= htmlspecialchars($u['nombre'] . ' ' . $u['apellido']) ?></div>
              <?php if ($isSelf): ?><small class="text-accent">(tú)</small><?php endif; ?>
            </td>
            <td class="fw-semi text-accent"><?= htmlspecialchars($u['cedula']) ?></td>
            <td class="d-none d-md-table-cell text-muted"><?= htmlspecialchars($u['correo']) ?></td>
            <td class="d-none d-sm-table-cell text-muted"><?= htmlspecialchars($u['telefono'] ?? '—') ?></td>
            <td>
              <span class="badge <?= $u['rol']==='admin' ? 'badge-gold' : 'badge-info-c' ?>">
                <?= $u['rol'] === 'admin' ? '🔑 Admin' : '👤 Usuario' ?>
              </span>
            </td>
            <td>
              <span class="badge <?= $u['activo'] ? 'badge-success-c' : 'badge-danger-c' ?>">
                <?= $u['activo'] ? 'Activo' : 'Inactivo' ?>
              </span>
            </td>
            <td>
              <div class="d-flex gap-1 flex-wrap">
                <a href="<?= BASE_URL ?>Vista/usuarios/editar.php?id=<?= $u['id'] ?>"
                   class="btn btn-secondary btn-sm" title="Editar">
                  <i class="bi bi-pencil"></i>
                </a>
                <?php if (!$isSelf): ?>
                <a href="<?= BASE_URL ?>Vista/usuarios/index.php?toggle=<?= $u['id'] ?>"
                   class="btn btn-sm <?= $u['activo'] ? 'btn-warning' : 'btn-success' ?>"
                   title="<?= $u['activo'] ? 'Desactivar' : 'Activar' ?>">
                  <i class="bi bi-power"></i>
                </a>
                <a href="#"
                   onclick="confirmDelete('<?= BASE_URL ?>Vista/usuarios/index.php?delete=<?= $u['id'] ?>','<?= htmlspecialchars($u['nombre'].' '.$u['apellido'], ENT_QUOTES) ?>')"
                   class="btn btn-danger btn-sm" title="Eliminar">
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
