<?php
// Vista/devoluciones/nueva.php
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/Config/database.php';
require_once ROOT . '/Config/config.php';
require_once ROOT . '/Config/auth.php';
requireLogin();

require_once ROOT . '/Controlador/DevolucionController.php';
require_once ROOT . '/Controlador/VentaController.php';

$devCtrl = new DevolucionController();
$ventaCtrl = new VentaController();

$venta_id = (int)($_GET['venta_id'] ?? 0);
$ventaSeleccionada = null;
$itemsVenta = [];

if ($venta_id > 0) {
    $ventaSeleccionada = $ventaCtrl->getById($venta_id);
    if ($ventaSeleccionada) {
        $itemsVenta = $devCtrl->getItemsDisponiblesParaDevolucion($venta_id);
    }
}

// Obtener las ventas recientes para el selector rápido
$pdo = db();
$stmtVentas = $pdo->query("SELECT v.id, v.numero_venta, v.fecha, v.total, c.nombre AS cliente_nombre 
                           FROM ventas v 
                           LEFT JOIN clientes c ON v.cliente_id = c.id 
                           WHERE v.estado != 'anulada' 
                           ORDER BY v.id DESC LIMIT 40");
$ventasRecientes = $stmtVentas->fetchAll(PDO::FETCH_ASSOC);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $vId = (int)($_POST['venta_id'] ?? 0);
    $vActual = $ventaCtrl->getById($vId);

    if (!$vActual) {
        $errors[] = 'Debe seleccionar una venta válida.';
    }

    $motivo = trim($_POST['motivo'] ?? '');
    if (empty($motivo)) {
        $errors[] = 'Debe indicar el motivo principal de la devolución o garantía.';
    }

    $tipoResolucion = $_POST['tipo_resolucion'] ?? 'nota_credito';
    $observaciones = trim($_POST['observaciones'] ?? '');
    $fecha = $_POST['fecha'] ?? date('Y-m-d');

    // Procesar los ítems marcados
    $cantidades = $_POST['cantidades'] ?? [];
    $acciones = $_POST['acciones'] ?? [];
    $motivosItem = $_POST['motivos_item'] ?? [];

    $itemsProcesar = [];
    $montoTotalDevolucion = 0;

    if ($vActual) {
        $itemsDisponibles = $devCtrl->getItemsDisponiblesParaDevolucion($vId);
        $mapDisponibles = [];
        foreach ($itemsDisponibles as $it) {
            $mapDisponibles[$it['producto_id']] = $it;
        }

        foreach ($cantidades as $prodId => $cant) {
            $cant = (int)$cant;
            if ($cant <= 0) continue;

            if (!isset($mapDisponibles[$prodId])) {
                $errors[] = "El producto ID $prodId no pertenece a esta venta.";
                continue;
            }

            $maxDisponible = $mapDisponibles[$prodId]['cantidad_disponible'];
            if ($cant > $maxDisponible) {
                $errors[] = "La cantidad a devolver de '{$mapDisponibles[$prodId]['producto_nombre']}' ($cant) supera el máximo disponible ($maxDisponible).";
                continue;
            }

            $precioUnit = (float)$mapDisponibles[$prodId]['precio_unitario'];
            $subtotalItem = $cant * $precioUnit;
            $montoTotalDevolucion += $subtotalItem;

            $itemsProcesar[] = [
                'producto_id'     => $prodId,
                'cantidad'        => $cant,
                'precio_unitario' => $precioUnit,
                'accion_stock'    => $acciones[$prodId] ?? 'reingresar_stock',
                'motivo_item'     => trim($motivosItem[$prodId] ?? '')
            ];
        }
    }

    if (empty($itemsProcesar) && empty($errors)) {
        $errors[] = 'Debe especificar al menos 1 unidad de repuesto para devolver.';
    }

    if (empty($errors)) {
        $dataDev = [
            'venta_id'        => $vId,
            'cliente_id'      => $vActual['cliente_id'],
            'fecha'           => $fecha,
            'tipo_resolucion' => $tipoResolucion,
            'monto_total'     => $montoTotalDevolucion,
            'motivo'          => $motivo,
            'observaciones'   => $observaciones
        ];

        $nuevoId = $devCtrl->crear($dataDev, $itemsProcesar);
        if ($nuevoId) {
            setFlash('success', 'Devolución/Garantía registrada correctamente.');
            redirect("Vista/devoluciones/ver.php?id=$nuevoId");
        } else {
            $errors[] = 'Ocurrió un error al guardar la devolución en la base de datos.';
        }
    }
}

$pageTitle = 'Registrar Devolución o Garantía';
include ROOT . '/Vista/Includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h1 class="page-title mb-0"><i class="bi bi-shield-check me-2 text-warning"></i>Registrar Garantía / Devolución</h1>
    <p class="text-muted small mb-0">Seleccione la venta de origen para cargar los repuestos facturados</p>
  </div>
  <a href="<?= BASE_URL ?>Vista/devoluciones/index.php" class="btn btn-secondary btn-sm btn-volver">
    <i class="bi bi-arrow-left me-1"></i>Volver
  </a>
</div>

<?php if (!empty($errors)): ?>
  <div class="alert alert-danger mb-4">
    <div class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-2"></i>Por favor corrija los siguientes inconvenientes:</div>
    <ul class="mb-0 ps-3">
      <?php foreach ($errors as $err): ?>
        <li><?= htmlspecialchars($err) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<!-- Paso 1: Seleccionar Venta -->
<div class="card mb-4 border-0 shadow-sm">
  <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
    <span class="card-title mb-0"><i class="bi bi-search me-2"></i>Paso 1: Localizar Venta de Origen</span>
  </div>
  <div class="card-body">
    <form method="GET" class="row g-3 align-items-end">
      <div class="col-12 col-md-8">
        <label class="form-label fw-semi small text-muted">Seleccionar Venta o Factura Previa</label>
        <select name="venta_id" class="form-select form-select-lg" onchange="this.form.submit()">
          <option value="">-- Seleccionar venta para devolver repuestos --</option>
          <?php foreach ($ventasRecientes as $vr): ?>
            <option value="<?= $vr['id'] ?>" <?= $venta_id == $vr['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($vr['numero_venta']) ?> — <?= htmlspecialchars($vr['cliente_nombre'] ?? 'Cliente General') ?> (<?= date('d/m/Y', strtotime($vr['fecha'])) ?>) — $<?= number_format((float)$vr['total'], 2) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 col-md-4">
        <button type="submit" class="btn btn-primary btn-lg w-100">
          <i class="bi bi-box-arrow-in-down me-1"></i>Cargar Repuestos
        </button>
      </div>
    </form>
  </div>
</div>

<?php if ($ventaSeleccionada): ?>
<!-- Paso 2: Formulario de Reclamo y Repuestos -->
<form method="POST" id="formDevolucion">
  <input type="hidden" name="venta_id" value="<?= $ventaSeleccionada['id'] ?>">

  <div class="row g-4">
    <!-- Columna Izquierda: Datos del reclamo -->
    <div class="col-12 col-lg-4">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-header bg-light">
          <span class="card-title text-dark mb-0"><i class="bi bi-file-text me-2"></i>Datos de la Garantía</span>
        </div>
        <div class="card-body">
          <div class="p-3 bg-light rounded mb-3 border">
            <div class="small text-muted">Venta: <strong><?= htmlspecialchars($ventaSeleccionada['numero_venta']) ?></strong></div>
            <div class="small text-muted">Cliente: <strong><?= htmlspecialchars($ventaSeleccionada['cliente_nombre'] ?? 'Cliente General') ?></strong></div>
            <div class="small text-muted">Fecha Venta: <strong><?= date('d/m/Y', strtotime($ventaSeleccionada['fecha'])) ?></strong></div>
            <div class="small text-muted">Total Venta: <strong>$<?= number_format((float)$ventaSeleccionada['total'], 2) ?></strong></div>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semi small">Fecha de Recepción *</label>
            <input type="date" name="fecha" class="form-control" value="<?= date('Y-m-d') ?>" required>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semi small">Tipo de Resolución *</label>
            <select name="tipo_resolucion" class="form-select fw-semi" required>
              <option value="nota_credito">📄 Nota de Crédito (Saldo a favor para otra compra)</option>
              <option value="cambio_producto">🔄 Cambio por el Mismo u Otro Repuesto</option>
              <option value="reembolso_dinero">💵 Reembolso de Dinero al Cliente</option>
              <option value="solo_ingreso_garantia">🔧 Enviar a Proveedor / Garantía de Fábrica</option>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semi small">Motivo Principal del Reclamo *</label>
            <input type="text" name="motivo" class="form-control" placeholder="Ej: Defecto de fábrica, pieza incorrecta, etc." required>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semi small">Observaciones Técnicas Adicionales</label>
            <textarea name="observaciones" class="form-control" rows="3" placeholder="Detalles de la falla, estado del empaque original, notas del mecánico..."></textarea>
          </div>

          <div class="p-3 bg-primary bg-opacity-10 border border-primary-subtle rounded text-center">
            <div class="small text-muted text-uppercase fw-bold">Monto Total a Reintegrar</div>
            <div class="fs-3 fw-bold text-primary" id="txtTotalDevolucion">$0.00</div>
          </div>

          <div class="mt-4">
            <button type="submit" class="btn btn-success btn-lg w-100 fw-bold shadow-sm">
              <i class="bi bi-check2-circle me-1"></i>Procesar Devolución
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Columna Derecha: Selección de Repuestos -->
    <div class="col-12 col-lg-8">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
          <span class="card-title text-dark mb-0"><i class="bi bi-box-seam me-2"></i>Repuestos de la Venta</span>
          <span class="badge bg-secondary">Indique la cantidad a devolver</span>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Repuesto</th>
                  <th class="text-center">Comprado</th>
                  <th class="text-center">Disp.</th>
                  <th class="text-center" style="width: 100px;">Cant. Dev.</th>
                  <th class="text-end">PVP Unit.</th>
                  <th>Destino de Stock</th>
                  <th class="text-end">Subtotal</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($itemsVenta)): ?>
                  <tr>
                    <td colspan="7" class="text-center py-4 text-muted">
                      No se encontraron repuestos disponibles para devolver en esta venta.
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($itemsVenta as $it): ?>
                    <?php $disabled = $it['cantidad_disponible'] <= 0; ?>
                    <tr class="<?= $disabled ? 'table-secondary opacity-75' : '' ?>">
                      <td>
                        <div class="fw-bold text-dark"><?= htmlspecialchars($it['producto_nombre']) ?></div>
                        <small class="text-muted">
                          Cód: <?= !empty($it['producto_codigo']) ? htmlspecialchars($it['producto_codigo']) : 'S/C' ?>
                          <?= !empty($it['marca']) ? ' | ' . htmlspecialchars($it['marca']) : '' ?>
                        </small>
                      </td>
                      <td class="text-center fw-semi"><?= $it['cantidad_vendida'] ?></td>
                      <td class="text-center">
                        <span class="badge <?= $it['cantidad_disponible'] > 0 ? 'bg-success' : 'bg-danger' ?>">
                          <?= $it['cantidad_disponible'] ?>
                        </span>
                      </td>
                      <td class="text-center">
                        <input type="number" 
                               name="cantidades[<?= $it['producto_id'] ?>]" 
                               class="form-control form-control-sm text-center input-cant-dev" 
                               min="0" 
                               max="<?= $it['cantidad_disponible'] ?>" 
                               value="0" 
                               data-precio="<?= $it['precio_unitario'] ?>"
                               data-id="<?= $it['producto_id'] ?>"
                               <?= $disabled ? 'disabled' : '' ?>
                               oninput="recalcularTotalDev()">
                      </td>
                      <td class="text-end fw-semi">
                        $<?= number_format((float)$it['precio_unitario'], 2) ?>
                      </td>
                      <td>
                        <select name="acciones[<?= $it['producto_id'] ?>]" class="form-select form-select-sm" <?= $disabled ? 'disabled' : '' ?>>
                          <option value="reingresar_stock">🟢 Reingresar a Stock (Apto)</option>
                          <option value="garantia_proveedor">🟡 Defectuoso / Proveedor</option>
                          <option value="desecho_defectuoso">🔴 Desecho / Inutilizable</option>
                        </select>
                      </td>
                      <td class="text-end fw-bold text-dark item-subtotal" id="subtotal_<?= $it['producto_id'] ?>">
                        $0.00
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</form>

<script>
function recalcularTotalDev() {
  let total = 0;
  const inputs = document.querySelectorAll('.input-cant-dev');
  inputs.forEach(input => {
    const cant = parseInt(input.value) || 0;
    const precio = parseFloat(input.dataset.precio) || 0;
    const id = input.dataset.id;
    const subtotal = cant * precio;
    total += subtotal;

    const elSub = document.getElementById('subtotal_' + id);
    if (elSub) {
      elSub.textContent = '$' + subtotal.toLocaleString('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
  });

  const elTotal = document.getElementById('txtTotalDevolucion');
  if (elTotal) {
    elTotal.textContent = '$' + total.toLocaleString('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }
}
</script>
<?php endif; ?>

<?php include ROOT . '/Vista/Includes/footer.php'; ?>
