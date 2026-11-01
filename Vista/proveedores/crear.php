<?php
// Vista/proveedores/crear.php — Create and Edit combined
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/Config/database.php';
require_once ROOT . '/Config/config.php';
require_once ROOT . '/Controlador/ProveedorController.php';

$ctrl   = new ProveedorController();
$editId = (int)($_GET['edit'] ?? 0);
$prov   = $editId ? $ctrl->getById($editId) : null;
if ($editId && !$prov) { setFlash('danger', 'Proveedor no encontrado.'); redirect('Vista/proveedores/index.php'); }
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'rif'      => trim($_POST['rif']      ?? ''),
        'nombre'   => trim($_POST['nombre']   ?? ''),
        'telefono' => trim($_POST['telefono'] ?? ''),
        'email'    => trim($_POST['email']    ?? ''),
        'direccion'=> trim($_POST['direccion']?? ''),
        'contacto' => trim($_POST['contacto'] ?? ''),
    ];
    if (empty($data['rif']) || !preg_match('/^[a-zA-Z0-9\-]{6,20}$/', $data['rif'])) {
        $errors[] = 'El RIF es inválido o excede los caracteres permitidos.';
    }
    if (empty($data['nombre']) || strlen($data['nombre']) > 100 || !preg_match('/^[a-zA-ZÀ-ÿ0-9\s\.\,\-]{2,100}$/', $data['nombre'])) {
        $errors[] = 'El nombre/razón social es inválido o excede los 100 caracteres.';
    }
    if (!empty($data['contacto']) && (!preg_match('/^[a-zA-ZÀ-ÿ\s]{2,100}$/', $data['contacto']) || strlen($data['contacto']) > 100)) {
        $errors[] = 'El contacto es inválido (solo letras y espacios permitidos).';
    }
    if (!empty($data['telefono']) && !preg_match('/^[0-9\-\+\s]{7,20}$/', $data['telefono'])) {
        $errors[] = 'El formato del teléfono es inválido.';
    }
    if (!empty($data['email']) && (!filter_var($data['email'], FILTER_VALIDATE_EMAIL) || strlen($data['email']) > 150)) {
        $errors[] = 'El correo es inválido o demasiado largo.';
    }
    if (!empty($data['direccion']) && strlen($data['direccion']) > 255) {
        $errors[] = 'La dirección no puede exceder los 255 caracteres.';
    }

    if (empty($errors)) {
        $ok = $editId ? $ctrl->actualizar($editId, $data) : $ctrl->crear($data);
        if ($ok) {
            setFlash('success', $editId ? 'Proveedor actualizado.' : 'Proveedor creado correctamente.');
            redirect('Vista/proveedores/index.php');
        } else {
            $errors[] = 'Error al guardar. El RIF puede estar duplicado.';
        }
    }
    $prov = array_merge($prov ?? [], $data);
}

$pageTitle = $editId ? 'Editar Proveedor' : 'Nuevo Proveedor';
include ROOT . '/Vista/Includes/header.php';
?>

<div class="row justify-content-center">
<div class="col-12 col-md-8 col-lg-7">
<div class="card">
  <div class="card-header">
    <span class="card-title">
      <i class="bi <?= $editId ? 'bi-building-gear' : 'bi-building-add' ?> me-2"></i>
      <?= $editId ? 'Editar Proveedor' : 'Nuevo Proveedor' ?>
    </span>
    <a href="<?= BASE_URL ?>Vista/proveedores/index.php" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Volver</a>
  </div>
  <div class="card-body">
    <?php foreach ($errors as $e): ?>
      <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-2"></i><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>
    <form method="POST">
      <div class="row g-3">
        <div class="col-12 col-sm-6">
          <label class="form-label">RIF *</label>
          <input type="text" name="rif" class="form-control" required placeholder="Ej: J-12345678-9"
                 pattern="[a-zA-Z0-9\-]{6,20}" maxlength="20" title="Alfanumérico (letras, números, guiones) entre 6 y 20 caracteres"
                 value="<?= htmlspecialchars($prov['rif'] ?? '') ?>">
        </div>
        <div class="col-12 col-sm-6">
          <label class="form-label">Persona de Contacto</label>
          <input type="text" name="contacto" class="form-control" placeholder="Nombre del representante"
                 pattern="[a-zA-ZÀ-ÿ\s]{2,100}" maxlength="100" title="Solo letras y espacios"
                 value="<?= htmlspecialchars($prov['contacto'] ?? '') ?>">
        </div>
        <div class="col-12">
          <label class="form-label">Nombre / Razón Social *</label>
          <input type="text" name="nombre" class="form-control" required
                 pattern="[a-zA-ZÀ-ÿ0-9\s\.\,\-]{2,100}" maxlength="100" title="Nombre de compañía válido"
                 value="<?= htmlspecialchars($prov['nombre'] ?? '') ?>">
        </div>
        <div class="col-12 col-sm-6">
          <label class="form-label">Teléfono</label>
          <input type="tel" name="telefono" class="form-control" placeholder="0212-000-0000"
                 pattern="[0-9\-\+\s]{7,20}" maxlength="20" title="Números, guiones o signos de suma válidos"
                 value="<?= htmlspecialchars($prov['telefono'] ?? '') ?>">
        </div>
        <div class="col-12 col-sm-6">
          <label class="form-label">Email</label>
          <input type="email" name="email" class="form-control" placeholder="correo@proveedor.com" maxlength="150"
                 value="<?= htmlspecialchars($prov['email'] ?? '') ?>">
        </div>
        <div class="col-12">
          <label class="form-label">Dirección</label>
          <textarea name="direccion" class="form-control" rows="2" maxlength="255"><?= htmlspecialchars($prov['direccion'] ?? '') ?></textarea>
        </div>
        <div class="col-12">
          <div class="d-flex gap-2 flex-wrap">
            <button type="submit" class="btn btn-primary">
              <i class="bi bi-save me-1"></i><?= $editId ? 'Guardar Cambios' : 'Crear Proveedor' ?>
            </button>
            <a href="<?= BASE_URL ?>Vista/proveedores/index.php" class="btn btn-secondary">Cancelar</a>
          </div>
        </div>
      </div>
    </form>
  </div>
</div>
</div>
</div>

<?php include ROOT . '/Vista/Includes/footer.php'; ?>
