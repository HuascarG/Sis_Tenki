<?php
// Vista/clientes/crear.php — Create and Edit combined
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/Config/database.php';
require_once ROOT . '/Config/config.php';
require_once ROOT . '/Controlador/ClienteController.php';

$ctrl   = new ClienteController();
$editId = (int)($_GET['edit'] ?? 0);
$cliente = $editId ? $ctrl->getById($editId) : null;
if ($editId && !$cliente) { setFlash('danger', 'Cliente no encontrado.'); redirect('Vista/clientes/index.php'); }
$errors  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'tipo_documento' => $_POST['tipo_documento'] ?? 'CI',
        'documento'      => trim($_POST['documento']  ?? ''),
        'nombre'         => trim($_POST['nombre']      ?? ''),
        'telefono'       => trim($_POST['telefono']    ?? ''),
        'email'          => trim($_POST['email']       ?? ''),
        'direccion'      => trim($_POST['direccion']   ?? ''),
        'referencia'     => trim($_POST['referencia']  ?? ''),
    ];
    // PHP Backend Validations
    if (empty($data['documento']) || !preg_match('/^[a-zA-Z0-9\-]{6,20}$/', $data['documento'])) {
        $errors[] = 'El documento es inválido o excede los caracteres permitidos.';
    }
    if (empty($data['nombre']) || strlen($data['nombre']) > 100 || !preg_match('/^[a-zA-ZÀ-ÿ\s]{2,100}$/', $data['nombre'])) {
        $errors[] = 'El nombre es inválido o excede los 100 caracteres.';
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
    if (!empty($data['referencia']) && strlen($data['referencia']) > 255) {
        $errors[] = 'La referencia no puede exceder los 255 caracteres.';
    }

    if (empty($errors)) {
        $ok = $editId ? $ctrl->actualizar($editId, $data) : $ctrl->crear($data);
        if ($ok) {
            setFlash('success', $editId ? 'Cliente actualizado.' : 'Cliente creado correctamente.');
            redirect('Vista/clientes/index.php');
        } else {
            $errors[] = 'Error al guardar. El documento puede estar duplicado.';
        }
    }
    $cliente = array_merge($cliente ?? [], $data);
}

$pageTitle = $editId ? 'Editar Cliente' : 'Nuevo Cliente';
include ROOT . '/Vista/Includes/header.php';
?>

<div class="row justify-content-center">
<div class="col-12 col-md-8 col-lg-6">
<div class="card">
  <div class="card-header">
    <span class="card-title">
      <i class="bi <?= $editId ? 'bi-person-gear' : 'bi-person-plus' ?> me-2"></i>
      <?= $editId ? 'Editar Cliente' : 'Nuevo Cliente' ?>
    </span>
    <a href="<?= BASE_URL ?>Vista/clientes/index.php" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Volver</a>
  </div>
  <div class="card-body">
    <?php foreach ($errors as $e): ?>
      <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-2"></i><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>
    <form method="POST">
      <div class="row g-3">
        <div class="col-12 col-sm-5">
          <label class="form-label">Tipo de Documento</label>
          <select name="tipo_documento" class="form-select">
            <option value="CI"  <?= ($cliente['tipo_documento']??'CI')==='CI' ?'selected':'' ?>>Cédula (CI)</option>
            <option value="RIF" <?= ($cliente['tipo_documento']??'')==='RIF'  ?'selected':'' ?>>RIF (Empresa)</option>
          </select>
        </div>
        <div class="col-12 col-sm-7">
          <label class="form-label">N° Documento *</label>
          <input type="text" name="documento" class="form-control" required placeholder="Ej: V-12345678"
                 pattern="[a-zA-Z0-9\-]{6,20}" maxlength="20" title="Alfanumérico (letras, números, guiones) entre 6 y 20 caracteres"
                 value="<?= htmlspecialchars($cliente['documento'] ?? '') ?>">
        </div>
        <div class="col-12">
          <label class="form-label">Nombre Completo / Razón Social *</label>
          <input type="text" name="nombre" class="form-control" required
                 pattern="[a-zA-ZÀ-ÿ\s]{2,100}" maxlength="100" title="Solo letras y espacios (2 a 100 caracteres)"
                 value="<?= htmlspecialchars($cliente['nombre'] ?? '') ?>">
        </div>
        <div class="col-12 col-sm-6">
          <label class="form-label">Teléfono</label>
          <input type="tel" name="telefono" class="form-control" placeholder="0414-000-0000"
                 pattern="[0-9\-\+\s]{7,20}" maxlength="20" title="Números, guiones o signos de suma válidos"
                 value="<?= htmlspecialchars($cliente['telefono'] ?? '') ?>">
        </div>
        <div class="col-12 col-sm-6">
          <label class="form-label">Email</label>
          <input type="email" name="email" class="form-control" placeholder="correo@ejemplo.com" maxlength="150"
                 value="<?= htmlspecialchars($cliente['email'] ?? '') ?>">
        </div>
        <div class="col-12 col-md-6">
          <label class="form-label">Dirección</label>
          <textarea name="direccion" class="form-control" rows="2" maxlength="255"><?= htmlspecialchars($cliente['direccion'] ?? '') ?></textarea>
        </div>
        <div class="col-12 col-md-6">
          <label class="form-label">Referencia del Cliente</label>
          <textarea name="referencia" class="form-control" rows="2" maxlength="255" placeholder="Ej: Cliente del honda civic color rojo..."><?= htmlspecialchars($cliente['referencia'] ?? '') ?></textarea>
        </div>
        <div class="col-12">
          <div class="d-flex gap-2 flex-wrap">
            <button type="submit" class="btn btn-primary">
              <i class="bi bi-save me-1"></i><?= $editId ? 'Guardar Cambios' : 'Crear Cliente' ?>
            </button>
            <a href="<?= BASE_URL ?>Vista/clientes/index.php" class="btn btn-secondary">Cancelar</a>
          </div>
        </div>
      </div>
    </form>
  </div>
</div>
</div>
</div>

<?php include ROOT . '/Vista/Includes/footer.php'; ?>
