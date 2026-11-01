<?php
// Vista/compras/editar.php
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/Config/database.php';
require_once ROOT . '/Config/config.php';
require_once ROOT . '/Controlador/CompraController.php';

$ctrl   = new CompraController();
$id     = (int)($_GET['id'] ?? 0);
$compra = $ctrl->getById($id);
if (!$compra) { setFlash('danger', 'Compra no encontrada.'); redirect('Vista/compras/index.php'); }

$proveedores = $ctrl->getProveedores();
$errors      = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'proveedor_id'             => (int)($_POST['proveedor_id'] ?? 0),
        'numero_factura_proveedor' => trim($_POST['numero_factura_proveedor'] ?? ''),
        'fecha'                    => $_POST['fecha'] ?? date('Y-m-d'),
        'observaciones'            => trim($_POST['observaciones'] ?? ''),
    ];
    if (!empty($data['numero_factura_proveedor']) && (!preg_match('/^[a-zA-Z0-9\-\_]+$/', $data['numero_factura_proveedor']) || strlen($data['numero_factura_proveedor']) > 50)) {
        $errors[] = 'El N° de factura es inválido o demasiado largo.';
    }
    if (strlen($data['observaciones']) > 255) {
        $errors[] = 'Las observaciones no pueden exceder los 255 caracteres.';
    }

    if (empty($errors)) {
        if ($ctrl->actualizar($id, $data)) {
            setFlash('success', 'Compra actualizada correctamente.');
            redirect('Vista/compras/index.php');
        } else {
            $errors[] = 'No se pudieron guardar los cambios.';
        }
    }
} else {
    // Pre-rellenar con datos actuales
    $_POST = $compra;
}

$pageTitle = 'Editar Compra';
include ROOT . '/Vista/Includes/header.php';
?>

<div class="row justify-content-center">
<div class="col-12 col-xl-8">
<div class="card">
  <div class="card-header">
    <span class="card-title">
      <i class="bi bi-pencil-square me-2"></i>Editar — <?= htmlspecialchars($compra['numero_compra']) ?>
    </span>
    <a href="<?= BASE_URL ?>Vista/compras/index.php" class="btn btn-secondary btn-sm">
      <i class="bi bi-arrow-left me-1"></i>Volver
    </a>
  </div>
  <div class="card-body">

    <div class="alert alert-secondary py-2 small mb-4">
      <i class="bi bi-info-circle me-1"></i>
      Solo pueden modificarse el proveedor, nº de factura, fecha y observaciones.
      El detalle de productos es inmutable por integridad contable.
    </div>

    <?php foreach ($errors as $e): ?>
      <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-2"></i><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>

    <form method="POST">
      <div class="row g-3">
        <div class="col-12 col-md-6">
          <label class="form-label">Proveedor</label>
          <select name="proveedor_id" class="form-select">
            <option value="">Sin proveedor</option>
            <?php foreach ($proveedores as $p): ?>
            <option value="<?= $p['id'] ?>" <?= (int)($_POST['proveedor_id'] ?? 0) === $p['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($p['nombre']) ?> (<?= $p['rif'] ?>)
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12 col-md-6">
          <label class="form-label">Nº Factura Proveedor</label>
          <input type="text" name="numero_factura_proveedor" class="form-control"
                 value="<?= htmlspecialchars($_POST['numero_factura_proveedor'] ?? '') ?>"
                 pattern="[a-zA-Z0-9\-\_]{1,50}" maxlength="50" title="Alfanumérico y guiones (máx 50)"
                 placeholder="Ej: FAC-2024-001">
        </div>
        <div class="col-12 col-md-6">
          <label class="form-label">Fecha</label>
          <input type="date" name="fecha" class="form-control"
                 value="<?= htmlspecialchars($_POST['fecha'] ?? date('Y-m-d')) ?>">
        </div>
        <div class="col-12">
          <label class="form-label">Observaciones</label>
          <textarea name="observaciones" class="form-control" rows="3" maxlength="255"
                    placeholder="Notas internas..."><?= htmlspecialchars($_POST['observaciones'] ?? '') ?></textarea>
        </div>
        <div class="col-12">
          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Guardar Cambios</button>
            <a href="<?= BASE_URL ?>Vista/compras/ver.php?id=<?= $id ?>" class="btn btn-secondary">Ver Detalle</a>
          </div>
        </div>
      </div>
    </form>
  </div>
</div>
</div>
</div>

<?php include ROOT . '/Vista/Includes/footer.php'; ?>
