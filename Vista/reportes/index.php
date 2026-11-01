<?php
// Vista/reportes/index.php
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/Config/database.php';
require_once ROOT . '/Config/config.php';
require_once ROOT . '/Config/auth.php';

// Los reportes financieros deben ser restringidos a administradores en un sistema sano.
requireAdmin();

require_once ROOT . '/Controlador/ProveedorController.php';
require_once ROOT . '/Controlador/InventarioController.php';

$provCtrl = new ProveedorController();
$proveedores = $provCtrl->listar();

$invCtrl = new InventarioController();
$categorias = $invCtrl->getCategorias();

$pageTitle = 'Analíticas y Reportes';
include ROOT . '/Vista/Includes/header.php';
?>

<div class="row align-items-center mb-4">
    <div class="col-12">
        <h3 class="mb-1 text-dark fw-bold"><i class="bi bi-file-earmark-bar-graph me-2"></i>Centro de Reportes</h3>
        <p class="text-muted mb-0">Genera, filtra y exporta la analítica de tu negocio en formato de impresión de alta calidad.</p>
    </div>
</div>

<div class="row g-4">
    <!-- PANEL DE VENTAS -->
    <div class="col-12 col-md-4">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-header" style="background: linear-gradient(135deg, var(--accent), var(--accent-dark));">
                <span class="card-title text-white" style="font-size: 1.25rem;"><i class="bi bi-cart-check me-2"></i>Reporte de Ventas</span>
            </div>
            <div class="card-body">
                <form action="<?= BASE_URL ?>Vista/reportes/imprimir_ventas.php" method="GET" target="_blank">
                    <div class="mb-3">
                        <label class="form-label">Fecha Desde</label>
                        <input type="date" name="desde" class="form-control" required value="<?= date('Y-m-01') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Fecha Hasta</label>
                        <input type="date" name="hasta" class="form-control" required value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Estado</label>
                        <select name="estado" class="form-select">
                            <option value="todas">Todas las Ventas</option>
                            <option value="completada">Completadas (Exitosas)</option>
                            <option value="anulada">Ventas Anuladas</option>
                        </select>
                    </div>
                    <button type="submit" class="btn text-white w-100 mt-2" style="background: linear-gradient(135deg, var(--accent), var(--accent-dark)); border:none;">
                        <i class="bi bi-printer me-2"></i>Imprimir a PDF
                    </button>
                    <small class="d-block mt-2 text-muted text-center">Se abrirá en una nueva pestaña</small>
                </form>
            </div>
        </div>
    </div>

    <!-- PANEL DE COMPRAS -->
    <div class="col-12 col-md-4">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-header" style="background: linear-gradient(135deg, #10b981, #047857);">
                <span class="card-title text-white" style="font-size: 1.25rem;"><i class="bi bi-bag-check me-2"></i>Reporte de Compras</span>
            </div>
            <div class="card-body">
                <form action="<?= BASE_URL ?>Vista/reportes/imprimir_compras.php" method="GET" target="_blank">
                    <div class="mb-3">
                        <label class="form-label">Fecha Desde</label>
                        <input type="date" name="desde" class="form-control" required value="<?= date('Y-m-01') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Fecha Hasta</label>
                        <input type="date" name="hasta" class="form-control" required value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Filtrar Proveedor</label>
                        <select name="proveedor_id" class="form-select">
                            <option value="0">Todos los Proveedores</option>
                            <?php foreach ($proveedores as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn text-white w-100 mt-2" style="background: linear-gradient(135deg, #10b981, #047857); border:none;">
                        <i class="bi bi-printer me-2"></i>Imprimir a PDF
                    </button>
                    <small class="d-block mt-2 text-muted text-center">Se abrirá en una nueva pestaña</small>
                </form>
            </div>
        </div>
    </div>

    <!-- PANEL DE INVENTARIO -->
    <div class="col-12 col-md-4">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-header" style="background: linear-gradient(135deg, var(--gold), #a16207);">
                <span class="card-title text-white" style="font-size: 1.25rem;"><i class="bi bi-box-seam me-2"></i>Reporte de Inventario</span>
            </div>
            <div class="card-body">
                <form action="<?= BASE_URL ?>Vista/reportes/imprimir_inventario.php" method="GET" target="_blank">
                    <div class="mb-4">
                        <p class="text-muted small">Genera una auditoría física de lo que tienes almacenado actualmente en la base de datos.</p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tipo de Auditoría</label>
                        <select name="tipo" class="form-select border-info-c">
                            <option value="todos">Inventario Completo (Valorización de todo el Stock)</option>
                            <option value="bajo_stock">Alerta de Bajo Stock (Repuestos críticos para comprar)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Filtrar Categoría</label>
                        <select name="categoria_id" class="form-select border-info">
                            <option value="0">Todas las Categorías</option>
                            <?php foreach ($categorias as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn text-white w-100 mt-2" style="background: linear-gradient(135deg, var(--gold), #a16207); border:none;">
                        <i class="bi bi-printer me-2"></i>Imprimir a PDF
                    </button>
                    <small class="d-block mt-2 text-muted text-center">Se abrirá en una nueva pestaña</small>
                </form>
            </div>
        </div>
    </div>

</div>

<?php include ROOT . '/Vista/Includes/footer.php'; ?>
