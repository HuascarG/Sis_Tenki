<?php
// Vista/ventas/nueva.php
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/Config/database.php';
require_once ROOT . '/Config/config.php';
require_once ROOT . '/Config/auth.php';
requireLogin();

require_once ROOT . '/Config/bcv.php';
require_once ROOT . '/Controlador/VentaController.php';
require_once ROOT . '/Controlador/InventarioController.php';

$ventaCtrl = new VentaController();
$invCtrl   = new InventarioController();
$clientes  = $ventaCtrl->getClientes();
$productos = $invCtrl->listar();
$categorias  = $invCtrl->getCategorias();
$proveedores = $invCtrl->getProveedores();

// Fetch BCV Rate dynamically using official scraping from bcv.php (caches locally)
$tasaBCV = getBcvRate() ?? TASA_BCV;

$errors = [];
$formData = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $cartData = json_decode($_POST['cart_data'] ?? '[]', true);
    $obs = trim($_POST['observaciones'] ?? '');
    $tipoPago = $_POST['tipo_pago'] ?? 'contado';
    $clienteId = (int)($_POST['cliente_id'] ?? 0);
    $moneda = in_array($_POST['moneda'] ?? '', ['USD','BS']) ? $_POST['moneda'] : 'USD';
    $metodoPago = $_POST['metodo_pago'] ?? 'efectivo_usd';
    $fechaLimite = $tipoPago === 'credito' ? ($_POST['fecha_limite'] ?? '') : '';
    $aplicarIva = (int)($_POST['aplicar_iva'] ?? 0);

    // Save state to restore in form if validation fails
    $formData = [
        'cliente_id'    => $clienteId,
        'tipo_pago'     => $tipoPago,
        'moneda'        => $moneda,
        'metodo_pago'   => $metodoPago,
        'fecha_limite'  => $fechaLimite,
        'observaciones' => $obs,
        'aplicar_iva'   => $aplicarIva,
        'cart_data'     => $_POST['cart_data'] ?? '[]'
    ];

    if (empty($cartData)) {
        $errors[] = 'Agrega al menos un producto al carrito.';
    }
    if (strlen($obs) > 255) {
        $errors[] = 'Las observaciones no pueden exceder los 255 caracteres.';
    }
    if ($tipoPago === 'credito' && empty($clienteId)) {
        $errors[] = 'Las ventas a crédito requieren seleccionar un cliente.';
    }

    if (empty($errors)) {
        $items = [];
        foreach ($cartData as $item) {
            $items[] = ['id' => (int)$item['id'], 'qty' => max(1, (int)$item['qty']), 'price' => max(0.01, (float)$item['price'])];
        }

        $result = $ventaCtrl->crear([
            'cliente_id'    => $clienteId,
            'observaciones' => $obs,
            'tipo_pago'     => $tipoPago,
            'moneda'        => $moneda,
            'metodo_pago'   => $metodoPago,
            'fecha_limite'  => $fechaLimite,
            'aplicar_iva'   => $aplicarIva,
        ], $items);

        if ($result !== false) {
            $msg = $tipoPago === 'credito'
                ? 'Venta a crédito registrada. Factura generada.'
                : 'Venta registrada y factura generada automáticamente.';
            setFlash('success', $msg);
            redirect('Vista/facturacion/ver.php?venta_id=' . $result);
        } else {
            $errors[] = 'Error al registrar la venta. Verifique el stock disponible.';
        }
    }
}

$pageTitle = 'Nueva Venta';
include ROOT . '/Vista/Includes/header.php';
?>

<div class="row g-4">

  <!-- LEFT: PRODUCT SELECTOR -->
  <div class="col-12 col-lg-7 col-xl-8">
    <div class="card mb-0">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span class="card-title"><i class="bi bi-bag-plus me-2"></i>Agregar Productos</span>
        <a href="<?= BASE_URL ?>Vista/ventas/index.php" class="btn btn-secondary btn-sm btn-volver"><i class="bi bi-arrow-left me-1"></i>Volver a Ventas</a>
      </div>
      <div class="card-body">
        
        <!-- Formulario unificado de selección y búsqueda de producto -->
        <div class="row g-2 align-items-end mb-3">
          <div class="col-12 col-md-7 position-relative">
            <label class="form-label d-flex justify-content-between align-items-center">
              <span><i class="bi bi-search me-1 text-primary"></i>Buscar / Seleccionar Producto</span>
              <small class="text-muted" id="prod_count">(<?= count($productos) ?> disponibles)</small>
            </label>
            <div class="input-group">
              <input type="text" id="prod_search" class="form-control" autocomplete="off"
                     placeholder="Escribe el nombre o código del producto..."
                     aria-label="Buscar producto">
              <button type="button" class="btn btn-outline-secondary" id="btn_clear_prod" title="Limpiar búsqueda" style="display:none;" onclick="clearProductSearch()">
                <i class="bi bi-x-lg"></i>
              </button>
              <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalProductoVenta" title="Crear Producto Nuevo">
                <i class="bi bi-plus-lg text-white fw-bold"></i>
              </button>
            </div>

            <!-- Menú flotante de resultados -->
            <div id="prod_dropdown_results" class="shadow rounded-3 border bg-white position-absolute w-100 mt-1"
                 style="display:none; max-height: 280px; overflow-y: auto; z-index: 1050; left: 0;">
            </div>

            <!-- Select oculto sincronizado para compatibilidad con scripts existentes -->
            <select id="prod_select" class="d-none">
              <option value="">— Seleccionar producto —</option>
              <?php foreach ($productos as $p): ?>
              <option value="<?= $p['id'] ?>"
                data-name="<?= htmlspecialchars($p['nombre'], ENT_QUOTES) ?>"
                data-codigo="<?= htmlspecialchars($p['codigo'] ?? '', ENT_QUOTES) ?>"
                data-price="<?= $p['precio_venta'] ?>"
                data-stock="<?= $p['stock'] ?>">
                <?= !empty($p['codigo']) ? '[' . htmlspecialchars($p['codigo']) . '] ' : '' ?><?= htmlspecialchars($p['nombre']) ?> — $<?= number_format($p['precio_venta'],2) ?> (Stk: <?= $p['stock'] ?>)
              </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-6 col-md-2">
            <label class="form-label">Cantidad</label>
            <input type="number" id="prod_qty" class="form-control text-center fw-bold" value="1" min="1">
          </div>

          <div class="col-6 col-md-3">
            <button type="button" id="btn_add_to_cart" onclick="addToCart()" class="btn btn-primary w-100 fw-semi">
              <i class="bi bi-cart-plus me-1"></i>Agregar
            </button>
          </div>
        </div>

        <!-- Indicador de producto actualmente seleccionado -->
        <div id="selected_prod_banner" class="alert alert-light border border-primary-subtle py-2 px-3 mb-3 d-none align-items-center justify-content-between">
          <div class="d-flex align-items-center text-truncate me-2">
            <span class="badge bg-primary me-2"><i class="bi bi-check-lg"></i> Seleccionado</span>
            <span id="selected_prod_text" class="fw-semi text-dark text-truncate"></span>
          </div>
          <button type="button" class="btn btn-sm btn-link text-muted p-0 text-decoration-none" onclick="clearProductSearch()" title="Quitar selección">
            <i class="bi bi-x-circle fs-5"></i>
          </button>
        </div>

        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead>
              <tr>
                <th>Producto</th>
                <th style="width: 80px">Cant.</th>
                <th>Precio</th>
                <th>Subtotal</th>
                <th></th>
              </tr>
            </thead>
            <tbody id="cart-tbody">
              <tr><td colspan="5" class="text-center text-muted py-3">Carrito vacío</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- RIGHT: ORDER SUMMARY -->
  <div class="col-12 col-lg-5 col-xl-4">
    
    <div class="card mb-3 border-info">
      <div class="card-body py-2 px-3 d-flex justify-content-between align-items-center bg-info bg-opacity-10 rounded">
        <div>
          <span class="d-block fw-semi text-info-emphasis" style="font-size:0.85rem">TASA DE CAMBIO BCV</span>
          <small class="text-muted">Aplicada a los totales</small>
        </div>
        <div class="input-group input-group-sm" style="width: 120px;">
          <span class="input-group-text bg-white border-end-0">Bs</span>
          <input type="number" id="tasa_bcv" class="form-control border-start-0 ps-0 fw-bold text-end" value="<?= number_format($tasaBCV, 2, '.', '') ?>" step="0.01" oninput="renderCart()">
        </div>
      </div>
    </div>

    <div class="card mb-0">
      <div class="card-header"><span class="card-title"><i class="bi bi-receipt me-2"></i>Resumen de Venta</span></div>
      <div class="card-body">
        <?php if (!empty($errors)): ?>
        <script>
        document.addEventListener('DOMContentLoaded', () => {
          Swal.fire({
            icon: 'error',
            title: 'No se pudo registrar la venta',
            html: <?= json_encode(implode('<br>', array_map('htmlspecialchars', $errors))) ?>,
            confirmButtonColor: '#f97316'
          });
        });
        </script>
        <?php endif; ?>

        <form method="POST" id="formNuevaVenta">
          <input type="hidden" id="cart-data" name="cart_data" value="<?= htmlspecialchars($formData['cart_data'] ?? '[]', ENT_QUOTES) ?>">
          <input type="hidden" id="aplicar_iva_hidden" name="aplicar_iva" value="<?= (int)($formData['aplicar_iva'] ?? 0) ?>">

          <div class="mb-3">
            <label class="form-label">Cliente</label>
            <div class="input-group">
              <select name="cliente_id" id="venta_cliente_id" class="form-select">
                <option value="">Cliente general / Contado</option>
                <?php foreach ($clientes as $c): ?>
                <option value="<?= $c['id'] ?>" <?= (($formData['cliente_id'] ?? 0) == $c['id']) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($c['nombre']) ?> (<?= htmlspecialchars($c['documento']) ?>)
                </option>
                <?php endforeach; ?>
              </select>
              <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalClienteVenta" title="Registrar Nuevo Cliente">
                <i class="bi bi-plus-lg text-white fw-bold"></i>
              </button>
            </div>
          </div>

          <!-- TIPO DE PAGO -->
          <div class="mb-3">
            <label class="form-label fw-semi">Tipo de Pago</label>
            <div class="d-flex gap-2">
              <div class="form-check form-check-inline flex-fill">
                <input class="form-check-input" type="radio" name="tipo_pago" id="pago_contado" value="contado" <?= (($formData['tipo_pago'] ?? 'contado') === 'contado') ? 'checked' : '' ?> onchange="toggleCreditFields()">
                <label class="form-check-label w-100 text-center py-2 rounded border" for="pago_contado" id="label_contado" style="cursor:pointer;transition:all .2s;background:rgba(22,163,74,.08);border-color:#16a34a !important;color:#16a34a;font-weight:600;">
                  <i class="bi bi-cash-coin me-1"></i>Contado
                </label>
              </div>
              <div class="form-check form-check-inline flex-fill">
                <input class="form-check-input" type="radio" name="tipo_pago" id="pago_credito" value="credito" <?= (($formData['tipo_pago'] ?? '') === 'credito') ? 'checked' : '' ?> onchange="toggleCreditFields()">
                <label class="form-check-label w-100 text-center py-2 rounded border" for="pago_credito" id="label_credito" style="cursor:pointer;transition:all .2s;">
                  <i class="bi bi-credit-card me-1"></i>Crédito
                </label>
              </div>
            </div>
          </div>

          <!-- MONEDA + METODO DE PAGO (solo para contado) -->
          <div id="pago-contado-fields">
            <div class="mb-3">
              <label class="form-label fw-semi">Moneda</label>
              <div class="d-flex gap-2">
                <div class="form-check form-check-inline flex-fill m-0">
                  <input class="form-check-input d-none" type="radio" name="moneda" id="moneda_usd" value="USD" <?= (($formData['moneda'] ?? 'USD') === 'USD') ? 'checked' : '' ?> onchange="toggleMetodoPagoVenta()">
                  <label class="form-check-label w-100 text-center py-2 rounded border" for="moneda_usd" id="label_usd_v" style="cursor:pointer;transition:all .2s;background:rgba(22,163,74,.08);border-color:#16a34a !important;color:#16a34a;font-weight:600;">
                    💵 Dólares (USD)
                  </label>
                </div>
                <div class="form-check form-check-inline flex-fill m-0">
                  <input class="form-check-input d-none" type="radio" name="moneda" id="moneda_bs" value="BS" <?= (($formData['moneda'] ?? '') === 'BS') ? 'checked' : '' ?> onchange="toggleMetodoPagoVenta()">
                  <label class="form-check-label w-100 text-center py-2 rounded border" for="moneda_bs" id="label_bs_v" style="cursor:pointer;transition:all .2s;">
                    🇻🇪 Bolívares (Bs)
                  </label>
                </div>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label">Método de Pago</label>
              <select name="metodo_pago" id="select_metodo_pago_v" class="form-select">
                <?php $selMetodo = $formData['metodo_pago'] ?? 'efectivo_usd'; ?>
                <option value="efectivo_usd" <?= $selMetodo === 'efectivo_usd' ? 'selected' : '' ?>>💵 Efectivo (USD)</option>
                <option value="binance" <?= $selMetodo === 'binance' ? 'selected' : '' ?>>🟡 Binance</option>
                <option value="zelle" <?= $selMetodo === 'zelle' ? 'selected' : '' ?>>🟢 Zelle</option>
                <option value="zinli" <?= $selMetodo === 'zinli' ? 'selected' : '' ?>>🟣 Zinli</option>
              </select>
            </div>
          </div>

          <!-- CREDIT FIELDS (hidden by default) -->
          <div id="credit-fields" style="display:none;">
            <div class="alert alert-warning py-2 mb-3" style="font-size:.85rem">
              <i class="bi bi-info-circle me-1"></i>Venta a crédito: se requiere un <strong>cliente</strong>. El método de pago se registrará al momento de cobrar desde el módulo de <strong>Créditos</strong>.
            </div>
            <div class="mb-3">
              <label class="form-label">Fecha Límite de Pago <small class="text-muted">(Opcional)</small></label>
              <input type="date" name="fecha_limite" id="fecha_limite" class="form-control" min="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($formData['fecha_limite'] ?? '') ?>">
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Observaciones</label>
            <textarea name="observaciones" class="form-control" rows="2" maxlength="255" placeholder="Opcional..."><?= htmlspecialchars($formData['observaciones'] ?? '') ?></textarea>
          </div>

          <div class="totals-box mb-3">
            <div class="row-total"><span class="text-muted">Subtotal:</span><span id="cart-subtotal" class="text-end">$0.00</span></div>
            <div class="row-total d-flex justify-content-between align-items-center">
              <div class="d-flex align-items-center gap-2">
                <div class="form-check form-switch m-0 p-0">
                  <input class="form-check-input ms-0" type="checkbox" id="toggle_iva" <?= !empty($formData['aplicar_iva']) ? 'checked' : '' ?> onchange="toggleIva()" style="cursor:pointer;width:2.5em;height:1.3em;">
                </div>
                <label for="toggle_iva" class="<?= !empty($formData['aplicar_iva']) ? 'text-muted m-0' : 'text-danger m-0 fw-semi' ?>" style="cursor:pointer;font-size:.95rem;" id="label_iva"><?= !empty($formData['aplicar_iva']) ? 'IVA (16%)' : 'Sin IVA' ?></label>
              </div>
              <span id="cart-iva" class="text-end">$0.00</span>
            </div>
            <div class="row-total row-grand border-top pt-2 mt-2">
              <span>TOTAL (USD):</span>
              <span id="cart-total" class="text-end">$0.00</span>
            </div>
            <div class="row-total mt-1">
              <span class="text-muted" style="font-size: 0.95rem;">Total a Pagar (Bs):</span>
              <span id="cart-total-bs" class="fw-bold text-accent text-end" style="font-size: 1.15rem;">Bs 0.00</span>
            </div>
          </div>

          <div class="d-grid gap-2">
            <button type="submit" id="btn-submit-venta" class="btn btn-primary">
              <i class="bi bi-check-circle me-1"></i>Confirmar Venta y Generar Factura
            </button>
            <a href="<?= BASE_URL ?>Vista/ventas/index.php" class="btn btn-secondary text-center">Cancelar</a>
          </div>
        </form>
      </div>
    </div>
  </div>

</div>

<!-- MODAL: CREAR PRODUCTO RÁPIDO (VENTA) -->
<div class="modal fade" id="modalProductoVenta" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <form id="formAjaxProductoVenta" method="POST">
        <?= csrfField() ?>
        <div class="modal-header bg-success text-white">
          <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Nuevo Producto Rápido</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div id="alertAjaxProductoVenta" class="alert d-none"></div>
          <div class="row g-3">
            <div class="col-12 col-md-3">
              <label class="form-label">Código <small class="text-muted">(opcional)</small></label>
              <input type="text" name="codigo" class="form-control" maxlength="50" placeholder="Ej: FIL-001 (opcional)">
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label">Nombre *</label>
              <input type="text" name="nombre" class="form-control" required maxlength="150">
            </div>
            <div class="col-12 col-md-3">
              <label class="form-label">Marca</label>
              <input type="text" name="marca" class="form-control" maxlength="100">
            </div>
            <div class="col-12 col-md-12">
              <label class="form-label">Descripción</label>
              <textarea name="descripcion" class="form-control" rows="2" maxlength="500"></textarea>
            </div>
            <div class="col-12 col-md-3">
              <label class="form-label">Condición</label>
              <select name="condicion" class="form-select">
                <option value="nuevo">🆕 Nuevo</option>
                <option value="usado">🔄 Usado</option>
              </select>
            </div>
            <div class="col-12 col-md-3">
              <label class="form-label">Categoría</label>
              <select name="categoria_id" class="form-select">
                <option value="">Sin categoría</option>
                <?php foreach ($categorias as $c): ?>
                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12 col-md-3">
              <label class="form-label">Unidad</label>
              <select name="unidad" class="form-select">
                <option value="unidad">Unidad</option><option value="par">Par</option><option value="juego">Juego</option>
                <option value="litro">Litro</option><option value="caja">Caja</option>
              </select>
            </div>
            <div class="col-12 col-md-3">
              <label class="form-label">Proveedor</label>
              <select name="proveedor_id" class="form-select">
                <option value="">Sin proveedor</option>
                <?php foreach ($proveedores as $pr): ?>
                <option value="<?= $pr['id'] ?>"><?= htmlspecialchars($pr['nombre']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12 col-md-3">
              <label class="form-label">P. Compra ($)</label>
              <input type="number" name="precio_compra" class="form-control" step="0.01" min="0" value="0" placeholder="0.00">
            </div>
            <div class="col-12 col-md-3">
              <label class="form-label">P. Venta ($) *</label>
              <input type="number" name="precio_venta" class="form-control" step="0.01" min="0.01" required placeholder="0.00">
            </div>
            <div class="col-12 col-md-3">
              <label class="form-label">Stock Inicial</label>
              <input type="number" name="stock" class="form-control" value="0" min="0">
            </div>
            <div class="col-12 col-md-3">
              <label class="form-label">Stock Mínimo</label>
              <input type="number" name="stock_minimo" class="form-control" value="5" min="0">
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label"><i class="bi bi-geo-alt me-1"></i>Ubicación</label>
              <input type="text" name="ubicacion" class="form-control" placeholder="Ej: Estante A3, Pasillo 2..." maxlength="150">
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label"><i class="bi bi-camera me-1"></i>Foto del Producto <small class="text-muted">(Opcional, máx 2MB)</small></label>
              <input type="file" name="foto" class="form-control" accept="image/jpeg,image/png,image/webp,image/gif">
            </div>
          </div>
        </div>
        <div class="modal-footer d-flex justify-content-between">
          <span class="text-muted small"><i class="bi bi-info-circle me-1"></i>El producto se registrará y podrás agregarlo a la venta inmediatamente.</span>
          <div>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-success" id="btnSaveProductoVenta">Guardar y Añadir</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- MODAL: CREAR CLIENTE RÁPIDO (VENTA) -->
<div class="modal fade" id="modalClienteVenta" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form id="formAjaxClienteVenta" method="POST">
        <?= csrfField() ?>
        <div class="modal-header bg-success text-white">
          <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Nuevo Cliente Rápido</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div id="alertAjaxClienteVenta" class="alert d-none"></div>
          <div class="row g-3">
            <div class="col-12 col-sm-5">
              <label class="form-label">Tipo de Documento</label>
              <select name="tipo_documento" class="form-select">
                <option value="CI">Cédula (CI)</option>
                <option value="RIF">RIF (Empresa)</option>
              </select>
            </div>
            <div class="col-12 col-sm-7">
              <label class="form-label">N° Documento *</label>
              <input type="text" name="documento" class="form-control" required placeholder="Ej: V-12345678"
                     pattern="[a-zA-Z0-9\-]{6,20}" maxlength="20" title="Alfanumérico (letras, números, guiones) entre 6 y 20 caracteres">
            </div>
            <div class="col-12">
              <label class="form-label">Nombre Completo / Razón Social *</label>
              <input type="text" name="nombre" class="form-control" required
                     pattern="[a-zA-ZÀ-ÿ\s]{2,100}" maxlength="100" title="Solo letras y espacios (2 a 100 caracteres)">
            </div>
            <div class="col-12 col-sm-6">
              <label class="form-label">Teléfono</label>
              <input type="tel" name="telefono" class="form-control" placeholder="0414-000-0000"
                     pattern="[0-9\-\+\s]{7,20}" maxlength="20" title="Números, guiones o signos de suma válidos">
            </div>
            <div class="col-12 col-sm-6">
              <label class="form-label">Email</label>
              <input type="email" name="email" class="form-control" placeholder="correo@ejemplo.com" maxlength="150">
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label">Dirección</label>
              <textarea name="direccion" class="form-control" rows="2" maxlength="255"></textarea>
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label">Referencia del Cliente</label>
              <textarea name="referencia" class="form-control" rows="2" maxlength="255" placeholder="Ej: Cliente del honda civic color rojo..."></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer d-flex justify-content-between">
          <span class="text-muted small"><i class="bi bi-info-circle me-1"></i>No recargará la página</span>
          <div>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-success" id="btnSaveClienteVenta">Guardar y Seleccionar</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // ── AJAX Producto — Quick Create from Ventas ──
    const formProd = document.getElementById('formAjaxProductoVenta');
    if (formProd) {
        formProd.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = document.getElementById('btnSaveProductoVenta');
            const alertBox = document.getElementById('alertAjaxProductoVenta');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
            
            try {
                const formData = new FormData(formProd);
                const res = await fetch('<?= BASE_URL ?>Ajax/crear_producto.php', { method: 'POST', body: formData });
                const json = await res.json();
                
                if (json.status === 'success') {
                    const selProd = document.getElementById('prod_select');
                    if (selProd) {
                        const stock = parseInt(json.stock) || 0;
                        const price = parseFloat(json.precio_venta) || 0;
                        const codeLabel = json.codigo ? `[${json.codigo}] ` : '';
                        const optHtml = `<option value="${json.id}" data-name="${json.nombre}" data-codigo="${json.codigo || ''}" data-price="${price}" data-stock="${stock}">${codeLabel}${json.nombre} — $${price.toFixed(2)} (Stk: ${stock})</option>`;
                        selProd.insertAdjacentHTML('beforeend', optHtml);
                        selectProductUnified(json.id, json.nombre, json.codigo || '', price, stock);
                        
                        const countEl = document.getElementById('prod_count');
                        if (countEl) {
                            const count = selProd.options.length - 1;
                            countEl.textContent = `(${count} disponibles)`;
                        }
                    }
                    bootstrap.Modal.getInstance(document.getElementById('modalProductoVenta')).hide();
                    formProd.reset();
                    alertBox.classList.add('d-none');
                    Swal.fire({ icon:'success', title:'¡Producto registrado!', text:`${json.nombre} ha sido seleccionado para la venta.`, confirmButtonColor:'#f97316', timer:3000, timerProgressBar:true });
                } else {
                    alertBox.textContent = json.message;
                    alertBox.className = 'alert alert-danger';
                }
            } catch (err) {
                alertBox.textContent = 'Error de conexión con el servidor.';
                alertBox.className = 'alert alert-danger';
            } finally {
                btn.disabled = false;
                btn.textContent = 'Guardar y Añadir';
            }
        });
    }

    // ── AJAX Cliente — Quick Create from Ventas ──
    const formCli = document.getElementById('formAjaxClienteVenta');
    if (formCli) {
        formCli.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = document.getElementById('btnSaveClienteVenta');
            const alertBox = document.getElementById('alertAjaxClienteVenta');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
            
            try {
                const formData = new FormData(formCli);
                const res = await fetch('<?= BASE_URL ?>Ajax/crear_cliente.php', { method: 'POST', body: formData });
                const json = await res.json();
                
                if (json.status === 'success') {
                    // Inject into client select and auto-select
                    const selCli = document.getElementById('venta_cliente_id');
                    if (selCli) {
                        const optHtml = `<option value="${json.id}">${json.nombre} (${json.documento})</option>`;
                        selCli.insertAdjacentHTML('beforeend', optHtml);
                        selCli.value = json.id;
                    }
                    
                    bootstrap.Modal.getInstance(document.getElementById('modalClienteVenta')).hide();
                    formCli.reset();
                    alertBox.classList.add('d-none');
                    
                    Swal.fire({
                        icon: 'success',
                        title: '¡Cliente registrado!',
                        text: `${json.nombre} ha sido creado y seleccionado para esta venta.`,
                        confirmButtonColor: '#f97316',
                        timer: 3000,
                        timerProgressBar: true
                    });
                } else {
                    alertBox.textContent = json.message;
                    alertBox.className = 'alert alert-danger';
                }
            } catch (err) {
                alertBox.textContent = 'Error de conexión con el servidor.';
                alertBox.className = 'alert alert-danger';
            } finally {
                btn.disabled = false;
                btn.textContent = 'Guardar y Seleccionar';
            }
        });
    }
});

// ── Toggle Credit Fields ──
function toggleCreditFields() {
    const isCredit = document.getElementById('pago_credito').checked;
    const creditFields = document.getElementById('credit-fields');
    const contadoFields = document.getElementById('pago-contado-fields');
    const btnSubmit = document.getElementById('btn-submit-venta');
    const labelContado = document.getElementById('label_contado');
    const labelCredito = document.getElementById('label_credito');

    creditFields.style.display = isCredit ? 'block' : 'none';
    contadoFields.style.display = isCredit ? 'none' : 'block';
    
    if (isCredit) {
        btnSubmit.innerHTML = '<i class="bi bi-credit-card me-1"></i>Registrar Venta a Crédito';
        btnSubmit.className = 'btn btn-warning w-100';
        labelCredito.style.background = 'rgba(245,158,11,.12)';
        labelCredito.style.borderColor = '#f59e0b';
        labelCredito.style.color = '#d97706';
        labelCredito.style.fontWeight = '600';
        labelContado.style.background = '';
        labelContado.style.borderColor = '';
        labelContado.style.color = '';
        labelContado.style.fontWeight = '';
    } else {
        btnSubmit.innerHTML = '<i class="bi bi-check-circle me-1"></i>Confirmar Venta y Generar Factura';
        btnSubmit.className = 'btn btn-primary w-100';
        labelContado.style.background = 'rgba(22,163,74,.08)';
        labelContado.style.borderColor = '#16a34a';
        labelContado.style.color = '#16a34a';
        labelContado.style.fontWeight = '600';
        labelCredito.style.background = '';
        labelCredito.style.borderColor = '';
        labelCredito.style.color = '';
        labelCredito.style.fontWeight = '';
    }
}

// ── Toggle IVA ──
function toggleIva() {
    const chk = document.getElementById('toggle_iva');
    const hidden = document.getElementById('aplicar_iva_hidden');
    const label = document.getElementById('label_iva');
    if (hidden) hidden.value = chk.checked ? '1' : '0';
    if (label) {
        label.textContent = chk.checked ? 'IVA (16%)' : 'Sin IVA';
        label.className = chk.checked ? 'text-muted m-0' : 'text-danger m-0 fw-semi';
    }
    renderCart();
}

// ── Toggle Método de Pago por Moneda ──
function toggleMetodoPagoVenta() {
    const isUSD = document.getElementById('moneda_usd').checked;
    const sel = document.getElementById('select_metodo_pago_v');
    const labelUsd = document.getElementById('label_usd_v');
    const labelBs = document.getElementById('label_bs_v');

    sel.innerHTML = '';

    if (isUSD) {
        sel.innerHTML = `
            <option value="efectivo_usd">💵 Efectivo (USD)</option>
            <option value="binance">🟡 Binance</option>
            <option value="zelle">🟢 Zelle</option>
            <option value="zinli">🟣 Zinli</option>
        `;
        labelUsd.style.background = 'rgba(22,163,74,.08)';
        labelUsd.style.borderColor = '#16a34a';
        labelUsd.style.color = '#16a34a';
        labelUsd.style.fontWeight = '600';
        labelBs.style.background = '';
        labelBs.style.borderColor = '';
        labelBs.style.color = '';
        labelBs.style.fontWeight = '';
    } else {
        sel.innerHTML = `
            <option value="efectivo_bs">💵 Efectivo (Bs)</option>
            <option value="transferencia">🏦 Transferencia</option>
            <option value="punto">💳 Punto de Venta</option>
            <option value="pago_movil">📱 Pago Móvil</option>
        `;
        labelBs.style.background = 'rgba(202,138,4,.08)';
        labelBs.style.borderColor = '#ca8a04';
        labelBs.style.color = '#92400e';
        labelBs.style.fontWeight = '600';
        labelUsd.style.background = '';
        labelUsd.style.borderColor = '';
        labelUsd.style.color = '';
        labelUsd.style.fontWeight = '';
    }
}

// ── Unified Searchable Product Selector ──
const searchInput = document.getElementById('prod_search');
const dropdownResults = document.getElementById('prod_dropdown_results');
const hiddenSelect = document.getElementById('prod_select');
const clearBtn = document.getElementById('btn_clear_prod');
const banner = document.getElementById('selected_prod_banner');
const bannerText = document.getElementById('selected_prod_text');
const qtyInput = document.getElementById('prod_qty');

function getProductOptions() {
    const list = [];
    if (!hiddenSelect) return list;
    for (let i = 1; i < hiddenSelect.options.length; i++) {
        const opt = hiddenSelect.options[i];
        list.push({
            id: opt.value,
            name: opt.dataset.name || '',
            codigo: opt.dataset.codigo || '',
            price: parseFloat(opt.dataset.price) || 0,
            stock: parseInt(opt.dataset.stock) || 0,
            fullText: (opt.text || '').toLowerCase()
        });
    }
    return list;
}

function renderDropdown(items) {
    if (!dropdownResults) return;
    if (items.length === 0) {
        dropdownResults.innerHTML = `
            <div class="p-3 text-center text-muted small">
                <i class="bi bi-search me-1"></i>No se encontraron productos coincidentes
            </div>`;
        dropdownResults.style.display = 'block';
        return;
    }

    let html = '<div class="list-group list-group-flush">';
    items.forEach((p, idx) => {
        const isOutOfStock = p.stock <= 0;
        const codeBadge = p.codigo ? `<span class="badge bg-secondary me-2 font-monospace">${escapeHtml(p.codigo)}</span>` : '';
        const stockBadge = isOutOfStock 
            ? `<span class="badge bg-danger">Agotado (0)</span>` 
            : `<span class="badge bg-success-subtle text-success border border-success-subtle">Stock: ${p.stock}</span>`;

        html += `
            <button type="button" 
                    class="list-group-item list-group-item-action py-2 px-3 d-flex justify-content-between align-items-center ${isOutOfStock ? 'opacity-75 bg-light' : ''}"
                    onclick="selectProductUnified('${p.id}', '${escapeJs(p.name)}', '${escapeJs(p.codigo)}', ${p.price}, ${p.stock})">
                <div class="text-truncate me-2 text-start">
                    <div class="fw-semi text-dark">${codeBadge}${escapeHtml(p.name)}</div>
                    <small class="text-muted">${stockBadge}</small>
                </div>
                <div class="text-end text-nowrap">
                    <span class="fs-6 fw-bold text-primary">$${p.price.toFixed(2)}</span>
                </div>
            </button>`;
    });
    html += '</div>';
    dropdownResults.innerHTML = html;
    dropdownResults.style.display = 'block';
}

function filterProducts(query) {
    const products = getProductOptions();
    const cleanQuery = query.toLowerCase().trim();
    if (!cleanQuery) {
        renderDropdown(products.slice(0, 30));
        return;
    }
    const filtered = products.filter(p => {
        return p.fullText.includes(cleanQuery) || 
               p.name.toLowerCase().includes(cleanQuery) || 
               p.codigo.toLowerCase().includes(cleanQuery);
    });
    renderDropdown(filtered.slice(0, 30));
}

function selectProductUnified(id, name, codigo, price, stock) {
    if (hiddenSelect) hiddenSelect.value = id;
    if (searchInput) {
        const codePrefix = codigo ? `[${codigo}] ` : '';
        searchInput.value = `${codePrefix}${name}`;
    }
    if (clearBtn) clearBtn.style.display = 'inline-block';
    if (dropdownResults) dropdownResults.style.display = 'none';

    if (banner && bannerText) {
        const codePrefix = codigo ? `<strong>[${escapeHtml(codigo)}]</strong> ` : '';
        bannerText.innerHTML = `${codePrefix}${escapeHtml(name)} &mdash; <span class="text-primary fw-bold">$${price.toFixed(2)}</span> <span class="text-muted">(Stock disponible: ${stock})</span>`;
        banner.classList.remove('d-none');
        banner.classList.add('d-flex');
    }

    if (qtyInput) {
        qtyInput.max = stock > 0 ? stock : 1;
        qtyInput.focus();
        qtyInput.select();
    }
}

function clearProductSearch() {
    if (hiddenSelect) hiddenSelect.value = '';
    if (searchInput) {
        searchInput.value = '';
        searchInput.focus();
    }
    if (clearBtn) clearBtn.style.display = 'none';
    if (dropdownResults) dropdownResults.style.display = 'none';
    if (banner) {
        banner.classList.remove('d-flex');
        banner.classList.add('d-none');
    }
    if (qtyInput) qtyInput.value = 1;
}

function escapeHtml(str) {
    return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function escapeJs(str) {
    return String(str || '').replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '\\"');
}

if (searchInput) {
    searchInput.addEventListener('focus', function() {
        filterProducts(this.value);
    });

    searchInput.addEventListener('input', function() {
        if (hiddenSelect && hiddenSelect.value) {
            hiddenSelect.value = '';
            if (banner) {
                banner.classList.remove('d-flex');
                banner.classList.add('d-none');
            }
        }
        if (clearBtn) {
            clearBtn.style.display = this.value ? 'inline-block' : 'none';
        }
        filterProducts(this.value);
    });

    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const firstBtn = dropdownResults?.querySelector('button.list-group-item');
            if (firstBtn) {
                firstBtn.click();
            } else if (hiddenSelect?.value) {
                addToCart();
            }
        } else if (e.key === 'Escape') {
            if (dropdownResults) dropdownResults.style.display = 'none';
        }
    });

    // Close dropdown on click outside
    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !dropdownResults?.contains(e.target)) {
            if (dropdownResults) dropdownResults.style.display = 'none';
        }
    });
}

if (qtyInput) {
    qtyInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            addToCart();
        }
    });
}

// ── Validación antes de enviar para evitar recargar si faltan datos ──
const formNuevaVenta = document.getElementById('formNuevaVenta');
if (formNuevaVenta) {
    formNuevaVenta.addEventListener('submit', function(e) {
        const isCredito = document.getElementById('pago_credito')?.checked;
        const clienteSelect = document.getElementById('venta_cliente_id');
        const cartItemsCount = Object.keys(cart || {}).length;

        if (cartItemsCount === 0) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Carrito vacío',
                text: 'Debes agregar al menos un producto a la venta.',
                confirmButtonColor: '#f97316'
            });
            return false;
        }

        if (isCredito && (!clienteSelect || !clienteSelect.value)) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Cliente requerido',
                text: 'Las ventas a crédito requieren seleccionar un cliente obligatoriamente.',
                confirmButtonColor: '#f97316'
            }).then(() => {
                if (clienteSelect) clienteSelect.focus();
            });
            return false;
        }
    });
}

// ── Restaurar estado del carrito si hubo envío previo con error ──
document.addEventListener('DOMContentLoaded', () => {
    const rawCart = document.getElementById('cart-data')?.value;
    if (rawCart && rawCart !== '[]') {
        try {
            const parsed = JSON.parse(rawCart);
            if (Array.isArray(parsed) && parsed.length > 0) {
                cart = {};
                parsed.forEach(item => {
                    cart[item.id] = item;
                });
                renderCart();
            }
        } catch (err) {
            console.error('Error restaurando carrito:', err);
        }
    }
    toggleCreditFields();
});
</script>

<?php include ROOT . '/Vista/Includes/footer.php'; ?>
