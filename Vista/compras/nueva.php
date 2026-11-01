<?php
// Vista/compras/nueva.php
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/Config/database.php';
require_once ROOT . '/Config/config.php';
require_once ROOT . '/Config/auth.php';
requireLogin();

require_once ROOT . '/Controlador/CompraController.php';
require_once ROOT . '/Controlador/InventarioController.php';

$compraCtrl  = new CompraController();
$invCtrl     = new InventarioController();
$proveedores = $compraCtrl->getProveedores();
$productos   = $invCtrl->listar();
$categorias  = $invCtrl->getCategorias();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $cartData = json_decode($_POST['compra_data'] ?? '[]', true);
    if (empty($cartData)) {
        setFlash('danger', 'Agrega al menos un producto.'); redirect('Vista/compras/nueva.php');
    }
    $items = array_map(fn($i) => ['id'=>(int)$i['id'],'qty'=>max(1, (int)$i['qty']),'price'=>max(0.01, (float)$i['price'])], $cartData);
    $data  = [
        'proveedor_id'             => (int)($_POST['proveedor_id'] ?? 0),
        'numero_factura_proveedor' => trim($_POST['numero_factura_proveedor'] ?? ''),
        'fecha'                    => $_POST['fecha'] ?? date('Y-m-d'),
        'observaciones'            => trim($_POST['observaciones'] ?? ''),
    ];
    if (!empty($data['numero_factura_proveedor']) && (!preg_match('/^[a-zA-Z0-9\-\_]+$/', $data['numero_factura_proveedor']) || strlen($data['numero_factura_proveedor']) > 50)) {
        setFlash('danger', 'El N° de factura es inválido o demasiado largo.'); redirect('Vista/compras/nueva.php');
    }
    if (strlen($data['observaciones']) > 255) {
        setFlash('danger', 'Las observaciones no pueden exceder los 255 caracteres.'); redirect('Vista/compras/nueva.php');
    }
    $result = $compraCtrl->crear($data, $items);
    if ($result !== false) {
        setFlash('success', 'Compra registrada. Stock actualizado.');
        redirect('Vista/compras/ver.php?id=' . $result);
    } else {
        setFlash('danger', 'Error al registrar la compra.');
        redirect('Vista/compras/nueva.php');
    }
}

$pageTitle = 'Registrar Compra';
include ROOT . '/Vista/Includes/header.php';
?>

<div class="row g-4">

  <!-- PRODUCT SELECTOR -->
  <div class="col-12 col-lg-7 col-xl-8">
    <div class="card mb-0">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span class="card-title"><i class="bi bi-bag-plus me-2"></i>Agregar Productos</span>
        <a href="<?= BASE_URL ?>Vista/compras/index.php" class="btn btn-secondary btn-sm btn-volver"><i class="bi bi-arrow-left me-1"></i>Volver a Compras</a>
      </div>
      <div class="card-body">
        <div class="mb-3">
          <div class="input-group">
            <span class="input-group-text bg-white text-muted"><i class="bi bi-search"></i></span>
            <input type="text" id="comp_prod_search" class="form-control border-start-0 ps-0" placeholder="Buscar producto por código o nombre..." oninput="filterProductSelect('comp_prod_search','comp_prod_select',null)">
          </div>
        </div>

        <div class="row g-2 align-items-end mb-3">
          <div class="col-12 col-sm-5">
            <label class="form-label">Producto</label>
            <div class="input-group">
                <select id="comp_prod_select" class="form-select" onchange="document.getElementById('comp_prod_price').value = this.options[this.selectedIndex].dataset.price || 0;">
                  <option value="" data-price="0">— Seleccionar —</option>
                  <?php foreach ($productos as $p): ?>
                  <option value="<?= $p['id'] ?>" data-name="<?= htmlspecialchars($p['nombre'], ENT_QUOTES) ?>" data-price="<?= $p['precio_compra'] ?>">
                    [<?= htmlspecialchars($p['codigo']) ?>] <?= htmlspecialchars($p['nombre']) ?>
                  </option>
                  <?php endforeach; ?>
                </select>
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalProducto" title="Crear Producto Nuevo">
                    <i class="bi bi-plus-lg text-white fw-bold"></i>
                </button>
            </div>
          </div>
          <div class="col-6 col-sm-2">
            <label class="form-label">Cantidad</label>
            <input type="number" id="comp_prod_qty" class="form-control" value="1" min="1">
          </div>
          <div class="col-6 col-sm-3">
            <label class="form-label">P. Compra ($)</label>
            <input type="number" id="comp_prod_price" class="form-control" value="0" step="0.01" min="0">
          </div>
          <div class="col-12 col-sm-2">
            <button type="button" onclick="addToCartCompra()" class="btn btn-primary w-100">
              <i class="bi bi-plus-lg"></i>
            </button>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead><tr><th>Producto</th><th>Cant.</th><th>P. Compra</th><th>Subtotal</th><th></th></tr></thead>
            <tbody id="compra-tbody">
              <tr><td colspan="5" class="text-center text-muted py-3">Sin productos aún</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- COMPRA INFO -->
  <div class="col-12 col-lg-5 col-xl-4">
    <div class="card mb-0">
      <div class="card-header"><span class="card-title"><i class="bi bi-clipboard2-check me-2"></i>Datos de la Compra</span></div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" id="compra-data" name="compra_data" value="[]">
          <div class="mb-3">
            <label class="form-label">Proveedor</label>
            <div class="input-group">
                <select id="compra_proveedor_id" name="proveedor_id" class="form-select">
                  <option value="">Sin proveedor</option>
                  <?php foreach ($proveedores as $pr): ?>
                  <option value="<?= $pr['id'] ?>"><?= htmlspecialchars($pr['nombre']) ?></option>
                  <?php endforeach; ?>
                </select>
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalProveedor" title="Registrar Proveedor">
                    <i class="bi bi-plus-lg text-white fw-bold"></i>
                </button>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">N° Factura Proveedor</label>
            <input type="text" name="numero_factura_proveedor" class="form-control" placeholder="Ej: F-00001234"
                   pattern="[a-zA-Z0-9\-\_]{1,50}" maxlength="50" title="Alfanumérico y guiones (máx 50)">
          </div>
          <div class="mb-3">
            <label class="form-label">Fecha de Compra</label>
            <input type="date" name="fecha" class="form-control" value="<?= date('Y-m-d') ?>">
          </div>
          <div class="mb-3">
            <label class="form-label">Observaciones</label>
            <textarea name="observaciones" class="form-control" rows="2" maxlength="255"></textarea>
          </div>

          <div class="totals-box mb-3">
            <div class="row-total"><span class="text-muted">Subtotal:</span><span id="comp-subtotal">$0.00</span></div>
            <div class="row-total"><span class="text-muted">IVA (16%):</span><span id="comp-iva">$0.00</span></div>
            <div class="row-total row-grand"><span>TOTAL:</span><span id="comp-total">$0.00</span></div>
          </div>

          <div class="d-grid gap-2">
            <button type="submit" class="btn btn-primary">
              <i class="bi bi-check-circle me-1"></i>Registrar Compra
            </button>
            <a href="<?= BASE_URL ?>Vista/compras/index.php" class="btn btn-secondary text-center">Cancelar</a>
          </div>
        </form>
      </div>
    </div>
  </div>

</div>

<!-- MODAL: CREAR PROVEEDOR -->
<div class="modal fade" id="modalProveedor" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form id="formAjaxProveedor" method="POST">
        <?= csrfField() ?>
        <div class="modal-header bg-success text-white">
          <h5 class="modal-title"><i class="bi bi-building-add me-2"></i>Nuevo Proveedor Rápido</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div id="alertAjaxProveedor" class="alert d-none"></div>
          <div class="row g-3">
            <div class="col-12 col-sm-6">
              <label class="form-label">RIF *</label>
              <input type="text" name="rif" class="form-control" required placeholder="Ej: J-12345678-9" pattern="[a-zA-Z0-9\-]{6,20}" maxlength="20">
            </div>
            <div class="col-12 col-sm-6">
              <label class="form-label">Nombre / Razón Social *</label>
              <input type="text" name="nombre" class="form-control" required pattern="[a-zA-ZÀ-ÿ0-9\s\.\,\-]{2,100}" maxlength="100">
            </div>
            <div class="col-12 col-sm-6">
              <label class="form-label">Teléfono</label>
              <input type="tel" name="telefono" class="form-control" maxlength="20" placeholder="0212-000-0000">
            </div>
            <div class="col-12 col-sm-6">
              <label class="form-label">Email</label>
              <input type="email" name="email" class="form-control" maxlength="150">
            </div>
            <div class="col-12">
              <label class="form-label">Dirección</label>
              <textarea name="direccion" class="form-control" rows="1" maxlength="255"></textarea>
            </div>
            <div class="col-12">
              <label class="form-label">Contacto (Representante)</label>
              <input type="text" name="contacto" class="form-control" maxlength="100">
            </div>
          </div>
        </div>
        <div class="modal-footer d-flex justify-content-between">
          <span class="text-muted small"><i class="bi bi-info-circle me-1"></i>No recargará la página</span>
          <div>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-success" id="btnSaveProveedor">Guardar y Seleccionar</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- MODAL: CREAR PRODUCTO -->
<div class="modal fade" id="modalProducto" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <form id="formAjaxProducto" method="POST">
        <?= csrfField() ?>
        <div class="modal-header bg-success text-white">
          <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Nuevo Producto Rápido</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div id="alertAjaxProducto" class="alert d-none"></div>
          <div class="row g-3">
            <div class="col-12 col-md-3">
              <label class="form-label">Código *</label>
              <input type="text" name="codigo" class="form-control" required pattern="[a-zA-Z0-9\-\_]{2,50}" maxlength="50" placeholder="Ej: FIL-001">
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label">Nombre *</label>
              <input type="text" name="nombre" class="form-control" required maxlength="150">
            </div>
            <div class="col-12 col-md-12">
              <label class="form-label">Descripción</label>
              <textarea name="descripcion" class="form-control" rows="2" maxlength="500"></textarea>
            </div>
            <div class="col-12 col-md-3">
              <label class="form-label">Marca</label>
              <input type="text" name="marca" class="form-control" maxlength="100">
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
              <!-- It should use the same supplier dropdown but maybe it's missing the new one. Best to leave blank for now so it saves correctly. -->
              <select name="proveedor_id" class="form-select" id="ajax_prod_proveedor_id">
                <option value="">Sin proveedor</option>
                <?php foreach ($proveedores as $pr): ?>
                <option value="<?= $pr['id'] ?>"><?= htmlspecialchars($pr['nombre']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12 col-md-3">
              <label class="form-label">P. Venta ($) *</label>
              <input type="number" name="precio_venta" class="form-control" step="0.01" min="0.01" required placeholder="0.00">
            </div>
            <div class="col-12 col-md-3">
              <label class="form-label">Stock Mínimo</label>
              <input type="number" name="stock_minimo" class="form-control" value="5" min="0">
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label"><i class="bi bi-geo-alt me-1"></i>Ubicación</label>
              <input type="text" name="ubicacion" class="form-control" placeholder="Ej: Estante A3, Pasillo 2..." maxlength="150">
            </div>
            <div class="col-12">
              <label class="form-label"><i class="bi bi-camera me-1"></i>Foto del Producto <small class="text-muted">(Opcional, máx 2MB)</small></label>
              <input type="file" name="foto" class="form-control" accept="image/jpeg,image/png,image/webp,image/gif">
            </div>
          </div>
        </div>
        <div class="modal-footer d-flex justify-content-between">
          <span class="text-muted small"><i class="bi bi-info-circle me-1"></i>Precio de Compra será $0 y Stock será 0 por defecto (se rellena arriba al cargar).</span>
          <div>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-success" id="btnSaveProducto">Guardar y Añadir</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    
    // AJAX Proveedor
    const formProv = document.getElementById('formAjaxProveedor');
    if (formProv) {
        formProv.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = document.getElementById('btnSaveProveedor');
            const alertBox = document.getElementById('alertAjaxProveedor');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
            
            try {
                const formData = new FormData(formProv);
                const res = await fetch('<?= BASE_URL ?>Ajax/crear_proveedor.php', { method: 'POST', body: formData });
                const json = await res.json();
                
                if (json.status === 'success') {
                    // Inject into main select
                    const selMain = document.getElementById('compra_proveedor_id');
                    if (selMain) {
                        selMain.insertAdjacentHTML('beforeend', `<option value="${json.id}">${json.nombre}</option>`);
                        selMain.value = json.id; // Auto-select
                    }
                    // Inject into inner modal select (Producto) 
                    const selInner = document.getElementById('ajax_prod_proveedor_id');
                    if (selInner) selInner.insertAdjacentHTML('beforeend', `<option value="${json.id}">${json.nombre}</option>`);
                    
                    bootstrap.Modal.getInstance(document.getElementById('modalProveedor')).hide();
                    formProv.reset();
                    alertBox.classList.add('d-none');
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

    // AJAX Producto
    const formProd = document.getElementById('formAjaxProducto');
    if (formProd) {
        formProd.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = document.getElementById('btnSaveProducto');
            const alertBox = document.getElementById('alertAjaxProducto');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
            
            try {
                const formData = new FormData(formProd);
                const res = await fetch('<?= BASE_URL ?>Ajax/crear_producto.php', { method: 'POST', body: formData });
                const json = await res.json();
                
                if (json.status === 'success') {
                    const selProd = document.getElementById('comp_prod_select');
                    if (selProd) {
                        const optHtml = `<option value="${json.id}" data-name="${json.nombre}" data-price="${json.precio_compra}">[${json.codigo}] ${json.nombre}</option>`;
                        selProd.insertAdjacentHTML('beforeend', optHtml);
                        selProd.value = json.id; // Auto-select
                        // Trigger price load
                        document.getElementById('comp_prod_price').value = json.precio_compra || 0;
                        document.getElementById('comp_prod_search').value = json.codigo;
                        // Run visual filter
                        filterProductSelect('comp_prod_search','comp_prod_select',null);
                    }
                    
                    bootstrap.Modal.getInstance(document.getElementById('modalProducto')).hide();
                    formProd.reset();
                    alertBox.classList.add('d-none');
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
});
</script>

<?php include ROOT . '/Vista/Includes/footer.php'; ?>
