<?php
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/Config/database.php';
require_once ROOT . '/Config/config.php';
require_once ROOT . '/Config/auth.php';
require_once ROOT . '/Controlador/EmpresaController.php';

requireAdmin();
$ctrl  = new EmpresaController();
$datos = $ctrl->getDatos();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $data = [
        'nombre'      => trim($_POST['nombre'] ?? ''),
        'rif'         => trim($_POST['rif'] ?? ''),
        'telefono'    => trim($_POST['telefono'] ?? ''),
        'email'       => trim($_POST['email'] ?? ''),
        'direccion'   => trim($_POST['direccion'] ?? ''),
        'descripcion' => trim($_POST['descripcion'] ?? ''),
    ];

    if (empty($data['nombre']) || strlen($data['nombre']) > 150) {
        $errors[] = 'El Nombre comercial es requerido (máx 150 caracteres).';
    }
    if (empty($data['rif']) || strlen($data['rif']) > 30) {
        $errors[] = 'El RIF / NIT es requerido (máx 30 caracteres).';
    }

    if (empty($errors)) {
        if ($ctrl->actualizar($data)) {
            setFlash('success', 'Datos de la Empresa guardados correctamente.');
            redirect('Vista/empresa/index.php');
        } else {
            $errors[] = 'Error al actualizar los datos en la base de datos.';
        }
    }
    // Repopulate on failure
    $datos = array_merge($datos ?? [], $data);
}

$pageTitle = 'Datos de Empresa';
include ROOT . '/Vista/Includes/header.php';
?>

<div class="row justify-content-center">
<div class="col-12 col-xl-8">
<div class="card shadow-sm border-0">
  <div class="card-header bg-dark text-white">
    <span class="card-title fw-semi mb-0"><i class="bi bi-building-gear me-2"></i>Configuración de Empresa</span>
  </div>
  <div class="card-body">
    
    <div class="alert alert-secondary py-2 small mb-4">
      <i class="bi bi-info-circle me-1"></i>
      Estos son los datos maestros oficiales de la entidad comercial. Se reflejarán directamente en el encabezado de las facturas generadas.
    </div>

    <?php foreach ($errors as $e): ?>
      <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-2"></i><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>

    <form method="POST">
      <div class="row g-3">
        <div class="col-12 col-md-8">
          <label class="form-label text-muted fw-semi small mb-1">Nombre Comercial o Razón Social *</label>
          <input type="text" name="nombre" class="form-control form-control-lg fw-semi" required 
                 pattern="[a-zA-ZÀ-ÿ0-9\s\.\,\-]{2,150}" maxlength="150" 
                 value="<?= htmlspecialchars($datos['nombre'] ?? '') ?>">
        </div>
        <div class="col-12 col-md-4">
          <label class="form-label text-muted fw-semi small mb-1">RIF / Solvencia *</label>
          <input type="text" name="rif" class="form-control form-control-lg text-accent fw-bold" required 
                 maxlength="30" value="<?= htmlspecialchars($datos['rif'] ?? '') ?>">
        </div>
        
        <div class="col-12 col-md-6 mt-4">
          <label class="form-label text-muted fw-semi small mb-1"><i class="bi bi-telephone ms-1 me-1"></i>Teléfono Principal</label>
          <input type="text" name="telefono" class="form-control" maxlength="50" placeholder="Ej: +58 000 0000000"
                 value="<?= htmlspecialchars($datos['telefono'] ?? '') ?>">
        </div>
        <div class="col-12 col-md-6 mt-4">
          <label class="form-label text-muted fw-semi small mb-1"><i class="bi bi-envelope ms-1 me-1"></i>Correo Electrónico Oficial</label>
          <input type="email" name="email" class="form-control" maxlength="100" placeholder="contacto@empresa.com"
                 value="<?= htmlspecialchars($datos['email'] ?? '') ?>">
        </div>
        
        <div class="col-12 mt-4">
          <label class="form-label text-muted fw-semi small mb-1"><i class="bi bi-geo-alt ms-1 me-1"></i>Dirección Legal y Fiscal</label>
          <textarea name="direccion" class="form-control" rows="2" maxlength="255"><?= htmlspecialchars($datos['direccion'] ?? '') ?></textarea>
        </div>
        
        <div class="col-12 mt-3">
          <label class="form-label text-muted fw-semi small mb-1">Actividad Comercial / Eslogan (Opcional)</label>
          <input type="text" name="descripcion" class="form-control" maxlength="255" placeholder="Ej: Comercialización de repuestos..."
                 value="<?= htmlspecialchars($datos['descripcion'] ?? '') ?>">
        </div>

        <div class="col-12 mt-4 pt-3 border-top">
          <button type="submit" class="btn btn-primary px-4 shadow-sm">
            <i class="bi bi-save me-2"></i>Guardar Configuración
          </button>
        </div>
      </div>
    </form>
    
  </div>
</div>
</div>
</div>

<?php include ROOT . '/Vista/Includes/footer.php'; ?>
