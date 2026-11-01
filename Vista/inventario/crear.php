<?php
// Vista/inventario/crear.php
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/Config/database.php';
require_once ROOT . '/Config/config.php';
require_once ROOT . '/Config/auth.php';
requireLogin();

require_once ROOT . '/Controlador/InventarioController.php';

$ctrl       = new InventarioController();
$categorias = $ctrl->getCategorias();
$proveedores= $ctrl->getProveedores();
$errors     = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $data = [
        'codigo'        => trim($_POST['codigo'] ?? ''),
        'nombre'        => trim($_POST['nombre'] ?? ''),
        'descripcion'   => trim($_POST['descripcion'] ?? ''),
        'categoria_id'  => (int)($_POST['categoria_id'] ?? 0),
        'proveedor_id'  => (int)($_POST['proveedor_id'] ?? 0),
        'precio_compra' => (float)str_replace(',','.',($_POST['precio_compra'] ?? '0')),
        'precio_venta'  => (float)str_replace(',','.',($_POST['precio_venta']  ?? '0')),
        'stock'         => (int)($_POST['stock'] ?? 0),
        'stock_minimo'  => (int)($_POST['stock_minimo'] ?? 5),
        'unidad'        => trim($_POST['unidad'] ?? 'unidad'),
        'marca'         => trim($_POST['marca'] ?? ''),
        'condicion'     => in_array($_POST['condicion'] ?? '', ['nuevo','usado']) ? $_POST['condicion'] : 'nuevo',
        'ubicacion'     => trim($_POST['ubicacion'] ?? ''),
    ];
    if (!empty($data['codigo'])) {
        if (strlen($data['codigo']) > 50 || !preg_match('/^[a-zA-Z0-9\-\_]+$/', $data['codigo'])) {
            $errors[] = 'El código es inválido o excede los 50 caracteres permitidos (solo letras, números, guiones y guión bajo).';
        }
    } else {
        $data['codigo'] = null;
    }
    if (empty($data['nombre']) || strlen($data['nombre']) > 150) {
        $errors[] = 'El nombre es obligatorio y no puede exceder 150 caracteres.';
    }
    if (!empty($data['descripcion']) && strlen($data['descripcion']) > 255) {
        $errors[] = 'La descripción no puede exceder los 255 caracteres.';
    }
    if (!empty($data['marca']) && strlen($data['marca']) > 100) {
        $errors[] = 'La marca no puede exceder los 100 caracteres.';
    }
    if (!empty($data['ubicacion']) && strlen($data['ubicacion']) > 150) {
        $errors[] = 'La ubicación no puede exceder los 150 caracteres.';
    }
    if ($data['precio_compra'] < 0) {
        $errors[] = 'El precio de compra no puede ser negativo.';
    }
    if ($data['precio_venta'] <= 0) {
        $errors[] = 'El precio de venta debe ser mayor a 0.';
    }
    if ($data['stock'] < 0 || $data['stock_minimo'] < 0) {
        $errors[] = 'El stock no puede ser negativo.';
    }

    if (empty($errors)) {
        // Handle optional photo upload
        if (!empty($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $fotoPath = $ctrl->subirFoto($_FILES['foto']);
            if ($fotoPath) {
                $data['foto'] = $fotoPath;
            }
        }
        $result = $ctrl->crear($data);
        if ($result === true) {
            setFlash('success', 'Producto creado correctamente.');
            redirect('Vista/inventario/index.php');
        } else {
            // $result es un string con el mensaje de error específico
            $errors[] = is_string($result) ? $result : 'Error desconocido al guardar el producto.';
        }
    }
}

$pageTitle = 'Nuevo Producto';
include ROOT . '/Vista/Includes/header.php';
?>

<div class="row justify-content-center">
<div class="col-12 col-xl-10">
<div class="card">
  <div class="card-header">
    <span class="card-title"><i class="bi bi-plus-circle me-2"></i>Nuevo Producto</span>
    <a href="<?= BASE_URL ?>Vista/inventario/index.php" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Volver</a>
  </div>
  <div class="card-body">

    <?php if (!empty($errors)): ?>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
      Swal.fire({
        icon: 'error',
        title: 'Error al registrar',
        html: <?= json_encode(implode('<br>', array_map('htmlspecialchars', $errors))) ?>,
        confirmButtonColor: '#f97316'
      });
    });
    </script>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
      <div class="row g-3">
        <div class="col-12 col-md-4">
          <label class="form-label">Código <small class="text-muted">(opcional)</small></label>
          <input type="text" name="codigo" class="form-control" placeholder="Ej: FIL-001 (opcional)"
                 maxlength="50" title="Opcional. Alfanumérico, guiones y guión bajo permitidos (máx 50 caracteres)"
                 value="<?= htmlspecialchars($_POST['codigo'] ?? '') ?>">
        </div>
        <div class="col-12 col-md-4">
          <label class="form-label">Marca</label>
          <input type="text" name="marca" class="form-control" placeholder="Ej: Bosch, NGK, Monroe..." maxlength="100"
                 value="<?= htmlspecialchars($_POST['marca'] ?? '') ?>">
        </div>
        <div class="col-12 col-md-4">
          <label class="form-label">Condición</label>
          <select name="condicion" class="form-select">
            <option value="nuevo"  <?= (($_POST['condicion'] ?? 'nuevo') === 'nuevo')  ? 'selected' : '' ?>>🆕 Nuevo</option>
            <option value="usado"  <?= (($_POST['condicion'] ?? '') === 'usado')        ? 'selected' : '' ?>>🔄 Usado</option>
          </select>
        </div>
        <div class="col-12">
          <label class="form-label">Nombre *</label>
          <input type="text" name="nombre" class="form-control" required placeholder="Nombre completo del producto" maxlength="150"
                 value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>">
        </div>
        <div class="col-12">
          <label class="form-label">Descripción</label>
          <textarea name="descripcion" class="form-control" rows="2" maxlength="255" placeholder="Descripción opcional..."><?= htmlspecialchars($_POST['descripcion'] ?? '') ?></textarea>
        </div>
        <div class="col-12 col-md-6">
          <label class="form-label">Categoría</label>
          <select name="categoria_id" class="form-select">
            <option value="">Sin categoría</option>
            <?php foreach ($categorias as $c): ?>
            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12 col-md-6">
          <label class="form-label">Unidad de Medida</label>
          <select name="unidad" class="form-select">
            <?php
            $unidades = ['unidad','par','juego','litro','mililitro','kilogramo','gramo','metro','rollo','caja','galón'];
            $selUnidad = $_POST['unidad'] ?? 'unidad';
            foreach ($unidades as $u):
            ?>
            <option value="<?= $u ?>" <?= $selUnidad === $u ? 'selected' : '' ?>><?= ucfirst($u) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12 col-md-6">
          <label class="form-label">Proveedor</label>
          <select name="proveedor_id" class="form-select">
            <option value="">Sin proveedor</option>
            <?php foreach ($proveedores as $pr): ?>
            <option value="<?= $pr['id'] ?>"><?= htmlspecialchars($pr['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12 col-md-6">
          <label class="form-label"><i class="bi bi-geo-alt me-1"></i>Ubicación</label>
          <input type="text" name="ubicacion" class="form-control" placeholder="Ej: Estante A3, Pasillo 2, Almacén Norte..." maxlength="150"
                 value="<?= htmlspecialchars($_POST['ubicacion'] ?? '') ?>">
        </div>
        <div class="col-12 col-sm-6 col-md-3">
          <label class="form-label">Precio de Compra ($)</label>
          <input type="number" name="precio_compra" class="form-control" step="0.01" min="0" placeholder="0.00"
                 value="<?= htmlspecialchars($_POST['precio_compra'] ?? '') ?>">
        </div>
        <div class="col-12 col-sm-6 col-md-3">
          <label class="form-label">Precio de Venta ($) *</label>
          <input type="number" name="precio_venta" class="form-control" step="0.01" min="0.01" required placeholder="0.00"
                 value="<?= htmlspecialchars($_POST['precio_venta'] ?? '') ?>">
        </div>
        <div class="col-12 col-sm-6 col-md-3">
          <label class="form-label">Stock Inicial</label>
          <input type="number" name="stock" class="form-control" min="0" value="<?= $_POST['stock'] ?? 0 ?>">
        </div>
        <div class="col-12 col-sm-6 col-md-3">
          <label class="form-label">Stock Mínimo (Alerta)</label>
          <input type="number" name="stock_minimo" class="form-control" min="0" value="<?= $_POST['stock_minimo'] ?? 5 ?>">
        </div>
        <div class="col-12">
          <label class="form-label"><i class="bi bi-camera me-1"></i>Foto del Producto <small class="text-muted">(Opcional, máx 2MB)</small></label>
          <input type="file" name="foto" class="form-control" accept="image/jpeg,image/png,image/webp,image/gif">
        </div>
        <div class="col-12">
          <div class="d-flex gap-2 flex-wrap">
            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Guardar Producto</button>
            <a href="<?= BASE_URL ?>Vista/inventario/index.php" class="btn btn-secondary">Cancelar</a>
          </div>
        </div>
      </div>
    </form>
  </div>
</div>
</div>
</div>

<?php include ROOT . '/Vista/Includes/footer.php'; ?>
