<?php
// index.php — Dashboard (ROOT entry point)
define('ROOT', __DIR__);
require_once ROOT . '/Config/database.php';
require_once ROOT . '/Config/config.php';
require_once ROOT . '/Controlador/InventarioController.php';
require_once ROOT . '/Controlador/VentaController.php';
require_once ROOT . '/Controlador/FacturaController.php';
require_once ROOT . '/Controlador/CompraController.php';
require_once ROOT . '/Controlador/ClienteController.php';
require_once ROOT . '/Controlador/ProveedorController.php';
require_once ROOT . '/Controlador/CreditoController.php';

$invCtrl   = new InventarioController();
$ventaCtrl = new VentaController();
$facCtrl   = new FacturaController();
$compCtrl  = new CompraController();
$cliCtrl   = new ClienteController();
$provCtrl  = new ProveedorController();
$credCtrl  = new CreditoController();

// ── STATS DATA ──
$statsHoy        = $ventaCtrl->estadisticasHoy();
$totalClientes   = $cliCtrl->total();
$stockBajo       = $invCtrl->getStockBajo();
$totalFacMes     = $facCtrl->totalMes();
$totalProductos  = $invCtrl->totalProductos();
$valorInventario = $invCtrl->valorInventario();
$totalProveedores = $provCtrl->total();
$totalVentasMes  = $ventaCtrl->totalVentasMes();
$comprasTotalMes = $compCtrl->totalMes();
$totalFacturasMes = $facCtrl->totalFacturasMes();
$topProductos    = $ventaCtrl->topProductosVendidos(5);
$prodPorCategoria = $invCtrl->productosPorCategoria();
$ventasPorMes    = $ventaCtrl->ventasPorMes(6);
$facturacionHist = $facCtrl->facturacionPorMes(6);
$creditoResumen  = $credCtrl->resumen();
$creditosActivos = $credCtrl->listar(['estado' => '']); // all for dashboard
// Keep only non-pagado for the progress widget (limit 8)
$creditosWidget = array_filter($creditosActivos, fn($c) => $c['estado'] !== 'pagado');
$creditosWidget = array_slice($creditosWidget, 0, 8);

$pdo = db();
$recentVentas = $pdo->query(
    "SELECT v.*, c.nombre AS cliente_nombre FROM ventas v
     LEFT JOIN clientes c ON v.cliente_id = c.id
     ORDER BY v.created_at DESC LIMIT 5"
)->fetchAll();

// Recent compras
$recentCompras = $pdo->query(
    "SELECT c.*, p.nombre AS proveedor_nombre FROM compras c
     LEFT JOIN proveedores p ON c.proveedor_id = p.id
     ORDER BY c.created_at DESC LIMIT 5"
)->fetchAll();

// Spanish month names helper
function mesNombre(string $mesKey): string {
    $meses = [
        '01'=>'Enero','02'=>'Febrero','03'=>'Marzo','04'=>'Abril',
        '05'=>'Mayo','06'=>'Junio','07'=>'Julio','08'=>'Agosto',
        '09'=>'Septiembre','10'=>'Octubre','11'=>'Noviembre','12'=>'Diciembre'
    ];
    $parts = explode('-', $mesKey);
    return ($meses[$parts[1]] ?? $parts[1]) . ' ' . $parts[0];
}

$pageTitle = 'Dashboard';
include ROOT . '/Vista/Includes/header.php';
?>

<!-- ═══════════════ MAIN STATS ROW ═══════════════ -->
<div class="stats-grid mb-4">
  <div class="stat-card">
    <div class="stat-icon gold"><i class="bi bi-cart3"></i></div>
    <div>
      <div class="stat-label">Ventas Hoy</div>
      <div class="stat-value"><?= (int)$statsHoy['total_ventas'] ?></div>
      <div class="stat-sub"><?= formatMoney((float)$statsHoy['monto']) ?> en total</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green"><i class="bi bi-file-earmark-text"></i></div>
    <div>
      <div class="stat-label">Facturación del Mes</div>
      <div class="stat-value"><?= formatMoney($totalFacMes) ?></div>
      <div class="stat-sub"><?= $totalFacturasMes ?> facturas emitidas</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon blue"><i class="bi bi-people"></i></div>
    <div>
      <div class="stat-label">Clientes Activos</div>
      <div class="stat-value"><?= $totalClientes ?></div>
      <div class="stat-sub">registrados</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon yellow"><i class="bi bi-exclamation-triangle"></i></div>
    <div>
      <div class="stat-label">Stock Bajo Mínimo</div>
      <div class="stat-value text-warning"><?= count($stockBajo) ?></div>
      <div class="stat-sub">productos por reponer</div>
    </div>
  </div>
</div>

<!-- ═══════════════ SECONDARY STATS ROW ═══════════════ -->
<div class="stats-grid mb-4">
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(139,92,246,.12);color:#7c3aed;"><i class="bi bi-box-seam"></i></div>
    <div>
      <div class="stat-label">Productos Registrados</div>
      <div class="stat-value"><?= $totalProductos ?></div>
      <div class="stat-sub">Valor: <?= formatMoney($valorInventario) ?></div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(236,72,153,.12);color:#db2777;"><i class="bi bi-receipt"></i></div>
    <div>
      <div class="stat-label">Ventas del Mes</div>
      <div class="stat-value"><?= $totalVentasMes ?></div>
      <div class="stat-sub"><?= formatMoney($ventaCtrl->totalMes()) ?> facturado</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(14,165,233,.12);color:#0ea5e9;"><i class="bi bi-truck"></i></div>
    <div>
      <div class="stat-label">Compras del Mes</div>
      <div class="stat-value"><?= formatMoney($comprasTotalMes) ?></div>
      <div class="stat-sub">inversión en mercancía</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(124,58,237,.12);color:#7c3aed;"><i class="bi bi-credit-card-2-front"></i></div>
    <div>
      <div class="stat-label">Créditos por Cobrar</div>
      <div class="stat-value" style="color:#7c3aed;"><?= formatMoney($creditoResumen['total_por_cobrar']) ?></div>
      <div class="stat-sub"><?= $creditoResumen['creditos_activos'] ?> activos &middot; <?= $creditoResumen['clientes_con_deuda'] ?> clientes</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:rgba(20,184,166,.12);color:#14b8a6;"><i class="bi bi-building"></i></div>
    <div>
      <div class="stat-label">Proveedores Activos</div>
      <div class="stat-value"><?= $totalProveedores ?></div>
      <div class="stat-sub">en el sistema</div>
    </div>
  </div>
</div>

<!-- ═══════════════ CHARTS / HISTORY ROW ═══════════════ -->
<div class="row g-4 mb-4">
  <!-- VENTAS POR MES (Bar chart visual) -->
  <div class="col-12 col-xl-7">
    <div class="card h-100 mb-0">
      <div class="card-header">
        <span class="card-title"><i class="bi bi-bar-chart me-1"></i>Ventas - Últimos 6 Meses</span>
      </div>
      <div class="card-body">
        <?php if (!empty($ventasPorMes)): ?>
        <?php
          $maxMonto = max(array_column($ventasPorMes, 'monto'));
          $maxMonto = $maxMonto > 0 ? $maxMonto : 1;
        ?>
        <div class="chart-bars">
          <?php foreach ($ventasPorMes as $vm): ?>
          <?php $pct = ($vm['monto'] / $maxMonto) * 100; ?>
          <div class="chart-bar-group">
            <div class="chart-bar-label"><?= mesNombre($vm['mes']) ?></div>
            <div class="chart-bar-track">
              <div class="chart-bar-fill" style="width:<?= number_format($pct,1) ?>%">
                <span class="chart-bar-value"><?= formatMoney((float)$vm['monto']) ?></span>
              </div>
            </div>
            <div class="chart-bar-count"><?= (int)$vm['total_ventas'] ?> ventas</div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="text-center text-muted py-4"><i class="bi bi-graph-up fs-1 d-block mb-2 opacity-25"></i>Sin datos de ventas aún</div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- TOP PRODUCTOS VENDIDOS -->
  <div class="col-12 col-xl-5">
    <div class="card h-100 mb-0">
      <div class="card-header">
        <span class="card-title"><i class="bi bi-trophy me-1"></i>Top Productos (Mes)</span>
        <a href="<?= BASE_URL ?>Vista/inventario/index.php" class="btn btn-secondary btn-sm">Inventario</a>
      </div>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead><tr><th>#</th><th>Producto</th><th>Vendidos</th><th>Monto</th></tr></thead>
          <tbody>
            <?php foreach ($topProductos as $i => $tp): ?>
            <tr>
              <td>
                <span class="top-rank rank-<?= $i+1 ?>"><?= $i+1 ?></span>
              </td>
              <td>
                <div class="fw-semi"><?= htmlspecialchars($tp['nombre']) ?></div>
                <small class="text-muted"><?= htmlspecialchars($tp['codigo']) ?></small>
              </td>
              <td><span class="badge badge-info-c"><?= (int)$tp['total_vendido'] ?> uds</span></td>
              <td class="fw-semi"><?= formatMoney((float)$tp['total_monto']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($topProductos)): ?>
            <tr><td colspan="4" class="text-center text-muted py-4">Sin ventas este mes</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- ═══════════════ BILLING HISTORY ROW ═══════════════ -->
<div class="row g-4 mb-4">
  <!-- FACTURACION HISTORICA -->
  <div class="col-12 col-xl-7">
    <div class="card h-100 mb-0">
      <div class="card-header">
        <span class="card-title"><i class="bi bi-calendar3 me-1"></i>Facturación por Mes</span>
        <div class="d-flex gap-2 align-items-center">
          <select id="facturacion-meses" class="form-select form-select-sm" style="width:auto;">
            <option value="6">Últimos 6 meses</option>
            <option value="12" selected>Últimos 12 meses</option>
            <option value="24">Últimos 24 meses</option>
          </select>
          <button class="btn btn-secondary btn-sm" onclick="cargarFacturacionHistorica()" title="Actualizar">
            <i class="bi bi-arrow-clockwise"></i>
          </button>
        </div>
      </div>
      <div class="table-responsive" id="facturacion-tabla-container">
        <table class="table table-hover mb-0">
          <thead>
            <tr>
              <th>Mes</th>
              <th>Facturas</th>
              <th>Subtotal</th>
              <th>IVA</th>
              <th>Total</th>
            </tr>
          </thead>
          <tbody id="facturacion-tbody">
            <?php foreach ($facturacionHist as $fh): ?>
            <tr>
              <td class="fw-semi"><?= mesNombre($fh['mes']) ?></td>
              <td><span class="badge badge-info-c"><?= (int)$fh['total_facturas'] ?></span></td>
              <td><?= formatMoney((float)$fh['subtotal']) ?></td>
              <td class="text-muted"><?= formatMoney((float)$fh['iva']) ?></td>
              <td class="fw-semi text-accent"><?= formatMoney((float)$fh['total']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($facturacionHist)): ?>
            <tr><td colspan="5" class="text-center text-muted py-4">Sin facturación registrada</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- PRODUCTOS POR CATEGORIA -->
  <div class="col-12 col-xl-5">
    <div class="card h-100 mb-0">
      <div class="card-header">
        <span class="card-title"><i class="bi bi-pie-chart me-1"></i>Productos por Categoría</span>
      </div>
      <div class="card-body">
        <?php if (!empty($prodPorCategoria)):
          $maxCat = max(array_column($prodPorCategoria, 'total'));
          $maxCat = $maxCat > 0 ? $maxCat : 1;
          $catColors = ['#f97316','#16a34a','#2563eb','#7c3aed','#db2777','#0ea5e9'];
        ?>
        <div class="category-bars">
          <?php foreach ($prodPorCategoria as $i => $cat):
            $color = $catColors[$i % count($catColors)];
            $pctCat = ($cat['total'] / $maxCat) * 100;
          ?>
          <div class="cat-bar-row">
            <div class="cat-bar-label"><?= htmlspecialchars($cat['categoria'] ?? 'Sin categoría') ?></div>
            <div class="cat-bar-track">
              <div class="cat-bar-fill" style="width:<?= number_format($pctCat,1) ?>%;background:<?= $color ?>"></div>
            </div>
            <div class="cat-bar-count" style="color:<?= $color ?>"><?= (int)$cat['total'] ?></div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="text-center text-muted py-4"><i class="bi bi-diagram-3 fs-1 d-block mb-2 opacity-25"></i>Sin categorías registradas</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- ═══════════════ CRÉDITOS ACTIVOS + RECENT VENTAS ═══════════════ -->
<div class="row g-4 mb-4">
  <!-- CRÉDITOS ACTIVOS -->
  <div class="col-12 col-xl-5">
    <div class="card h-100 mb-0">
      <div class="card-header">
        <span class="card-title"><i class="bi bi-credit-card-2-front me-1"></i>Créditos Activos</span>
        <a href="<?= BASE_URL ?>Vista/creditos/index.php" class="btn btn-sm" style="background:rgba(124,58,237,.1);color:#7c3aed;">Ver todos</a>
      </div>
      <div class="card-body">
        <?php if (!empty($creditosWidget)): ?>
        <div class="d-flex flex-column gap-3">
          <?php foreach ($creditosWidget as $cw):
            $pctCw = $cw['monto_total'] > 0 ? round(($cw['monto_pagado'] / $cw['monto_total']) * 100) : 0;
            $vencido = !empty($cw['fecha_limite']) && $cw['fecha_limite'] < date('Y-m-d') && $cw['estado'] !== 'pagado';
            $barColor = $pctCw >= 100 ? '#16a34a' : ($pctCw > 0 ? '#7c3aed' : '#e5e7eb');
          ?>
          <div>
            <div class="d-flex justify-content-between align-items-center mb-1">
              <div>
                <span class="fw-semi" style="font-size:.9rem;"><?= htmlspecialchars($cw['cliente_nombre']) ?></span>
                <span class="text-muted ms-1" style="font-size:.75rem;"><?= htmlspecialchars($cw['numero_venta']) ?></span>
              </div>
              <div class="text-end">
                <?php if ($vencido): ?>
                  <span class="badge badge-danger-c" style="font-size:.65rem;"><i class="bi bi-exclamation-triangle me-1"></i>Vencido</span>
                <?php else: ?>
                  <span class="badge <?= $cw['estado']==='parcial' ? 'badge-credit-c' : 'badge-warning-c' ?>" style="font-size:.65rem;"><?= ucfirst($cw['estado']) ?></span>
                <?php endif; ?>
              </div>
            </div>
            <div class="credit-progress-bar" style="height:8px;border-radius:4px;">
              <div class="credit-progress-fill" style="width:<?= $pctCw ?>%;height:100%;border-radius:4px;background:<?= $barColor ?>;"></div>
            </div>
            <div class="d-flex justify-content-between mt-1" style="font-size:.75rem;">
              <span class="text-success"><?= formatMoney((float)$cw['monto_pagado']) ?> pagado</span>
              <span class="fw-semi <?= $vencido ? 'text-danger' : '' ?>"><?= formatMoney((float)$cw['monto_pendiente']) ?> pendiente</span>
              <span class="text-muted"><?= $pctCw ?>%</span>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="text-center text-muted py-4">
          <i class="bi bi-check-circle fs-1 d-block mb-2" style="opacity:.25;"></i>
          Sin créditos pendientes
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- RECENT VENTAS -->
  <div class="col-12 col-xl-7">
    <div class="card h-100 mb-0">
      <div class="card-header">
        <span class="card-title"><i class="bi bi-clock-history me-1"></i>Últimas Ventas</span>
        <a href="<?= BASE_URL ?>Vista/ventas/index.php" class="btn btn-secondary btn-sm">Ver todas</a>
      </div>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead><tr><th>#</th><th>Cliente</th><th>Total</th><th>Estado</th></tr></thead>
          <tbody>
            <?php foreach ($recentVentas as $v): ?>
            <tr>
              <td class="fw-semi text-accent"><?= htmlspecialchars($v['numero_venta']) ?></td>
              <td><?= htmlspecialchars($v['cliente_nombre'] ?? 'Sin cliente') ?></td>
              <td><?= formatMoney((float)$v['total']) ?></td>
              <td>
                <span class="badge <?= $v['estado']==='completada'?'badge-success-c':($v['estado']==='anulada'?'badge-danger-c':($v['estado']==='credito'?'badge-credit-c':'badge-warning-c')) ?>">
                  <?= $v['estado']==='credito' ? '<i class="bi bi-credit-card me-1"></i>' : '' ?><?= ucfirst($v['estado']) ?>
                </span>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($recentVentas)): ?>
            <tr><td colspan="4" class="text-center text-muted py-4">Sin ventas registradas</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- ═══════════════ STOCK CRÍTICO + ÚLTIMAS COMPRAS ═══════════════ -->
<div class="row g-4 mb-4">
  <!-- STOCK CRITICO -->
  <div class="col-12 col-xl-5">
    <div class="card h-100 mb-0">
      <div class="card-header">
        <span class="card-title"><i class="bi bi-exclamation-triangle text-warning me-1"></i> Stock Crítico</span>
        <a href="<?= BASE_URL ?>Vista/inventario/index.php" class="btn btn-secondary btn-sm">Inventario</a>
      </div>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead><tr><th>Producto</th><th>Stock</th><th>Mínimo</th></tr></thead>
          <tbody>
            <?php foreach (array_slice($stockBajo, 0, 8) as $p): ?>
            <tr>
              <td><?= htmlspecialchars($p['nombre']) ?></td>
              <td><span class="badge badge-danger-c"><?= $p['stock'] ?></span></td>
              <td class="text-muted"><?= $p['stock_minimo'] ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($stockBajo)): ?>
            <tr><td colspan="3" class="text-center py-4 text-muted">✅ Sin alertas de stock</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- RECENT COMPRAS -->
  <div class="col-12 col-xl-7">
    <div class="card h-100 mb-0">
      <div class="card-header">
        <span class="card-title"><i class="bi bi-truck me-1"></i>Últimas Compras</span>
        <a href="<?= BASE_URL ?>Vista/compras/index.php" class="btn btn-secondary btn-sm">Ver todas</a>
      </div>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead><tr><th>#</th><th>Proveedor</th><th>Fecha</th><th>Total</th></tr></thead>
          <tbody>
            <?php foreach ($recentCompras as $c): ?>
            <tr>
              <td class="fw-semi text-accent"><?= htmlspecialchars($c['numero_compra']) ?></td>
              <td><?= htmlspecialchars($c['proveedor_nombre'] ?? 'Sin proveedor') ?></td>
              <td class="text-muted"><?= date('d/m/Y', strtotime($c['fecha'])) ?></td>
              <td class="fw-semi"><?= formatMoney((float)$c['total']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($recentCompras)): ?>
            <tr><td colspan="4" class="text-center text-muted py-4">Sin compras registradas</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    </div>
  </div>
</div>

<!-- ═══════════════ ACCIONES RÁPIDAS ═══════════════ -->
<div class="row g-4 mb-4">
  <div class="col-12">
    <div class="card mb-0">
      <div class="card-header"><span class="card-title"><i class="bi bi-lightning me-1"></i>Acciones Rápidas</span></div>
      <div class="card-body">
        <div class="quick-actions-grid">
          <a href="<?= BASE_URL ?>Vista/ventas/nueva.php" class="quick-action-btn qa-gold">
            <i class="bi bi-plus-circle"></i>
            <span>Nueva Venta</span>
          </a>
          <a href="<?= BASE_URL ?>Vista/inventario/crear.php" class="quick-action-btn qa-purple">
            <i class="bi bi-box-seam"></i>
            <span>Nuevo Producto</span>
          </a>
          <a href="<?= BASE_URL ?>Vista/compras/nueva.php" class="quick-action-btn qa-sky">
            <i class="bi bi-truck"></i>
            <span>Registrar Compra</span>
          </a>
          <a href="<?= BASE_URL ?>Vista/clientes/crear.php" class="quick-action-btn qa-emerald">
            <i class="bi bi-person-plus"></i>
            <span>Nuevo Cliente</span>
          </a>
          <a href="<?= BASE_URL ?>Vista/creditos/index.php" class="quick-action-btn" style="background:rgba(124,58,237,.08);color:#7c3aed;">
            <i class="bi bi-credit-card-2-front"></i>
            <span>Créditos</span>
          </a>
          <a href="<?= BASE_URL ?>Vista/reportes/index.php" class="quick-action-btn qa-amber">
            <i class="bi bi-file-earmark-bar-graph"></i>
            <span>Reportes</span>
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ═══════════════ RESUMEN FINANCIERO (pie de dashboard) ═══════════════ -->
<div class="card mt-4">
  <div class="card-header">
    <span class="card-title"><i class="bi bi-graph-up-arrow me-1"></i>Resumen del Mes — <?= date('F Y') ?></span>
  </div>
  <div class="card-body">
    <div class="summary-metrics">
      <div class="summary-item">
        <div class="summary-icon" style="background:rgba(22,163,74,.1);color:#16a34a;"><i class="bi bi-arrow-up-circle"></i></div>
        <div>
          <div class="summary-label">Ingresos (Ventas)</div>
          <div class="summary-value text-success-c"><?= formatMoney($ventaCtrl->totalMes()) ?></div>
        </div>
      </div>
      <div class="summary-divider"></div>
      <div class="summary-item">
        <div class="summary-icon" style="background:rgba(220,38,38,.1);color:#dc2626;"><i class="bi bi-arrow-down-circle"></i></div>
        <div>
          <div class="summary-label">Egresos (Compras)</div>
          <div class="summary-value text-danger-c"><?= formatMoney($comprasTotalMes) ?></div>
        </div>
      </div>
      <div class="summary-divider"></div>
      <div class="summary-item">
        <div class="summary-icon" style="background:rgba(249,115,22,.1);color:#f97316;"><i class="bi bi-cash-stack"></i></div>
        <div>
          <div class="summary-label">Margen del Mes</div>
          <?php $margen = $ventaCtrl->totalMes() - $comprasTotalMes; ?>
          <div class="summary-value <?= $margen >= 0 ? 'text-success-c' : 'text-danger-c' ?>">
            <?= formatMoney($margen) ?>
          </div>
        </div>
      </div>
      <div class="summary-divider"></div>
      <div class="summary-item">
        <div class="summary-icon" style="background:rgba(124,58,237,.1);color:#7c3aed;"><i class="bi bi-credit-card-2-front"></i></div>
        <div>
          <div class="summary-label">Por Cobrar (Créditos)</div>
          <div class="summary-value" style="color:#7c3aed;"><?= formatMoney($creditoResumen['total_por_cobrar']) ?></div>
        </div>
      </div>
      <div class="summary-divider"></div>
      <div class="summary-item">
        <div class="summary-icon" style="background:rgba(22,163,74,.1);color:#16a34a;"><i class="bi bi-coin"></i></div>
        <div>
          <div class="summary-label">Cobrado este Mes</div>
          <div class="summary-value text-success-c"><?= formatMoney($creditoResumen['cobrado_mes']) ?></div>
        </div>
      </div>
      <div class="summary-divider"></div>
      <div class="summary-item">
        <div class="summary-icon" style="background:rgba(139,92,246,.1);color:#7c3aed;"><i class="bi bi-boxes"></i></div>
        <div>
          <div class="summary-label">Valor del Inventario</div>
          <div class="summary-value"><?= formatMoney($valorInventario) ?></div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// ── Facturación Histórica — AJAX Loader ──
const BASE = '<?= BASE_URL ?>';
const mesesNombres = {
  '01':'Enero','02':'Febrero','03':'Marzo','04':'Abril',
  '05':'Mayo','06':'Junio','07':'Julio','08':'Agosto',
  '09':'Septiembre','10':'Octubre','11':'Noviembre','12':'Diciembre'
};

function mesLabel(key) {
  const p = key.split('-');
  return (mesesNombres[p[1]] || p[1]) + ' ' + p[0];
}

function formatCurrency(val) {
  return '$' + parseFloat(val).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});
}

function cargarFacturacionHistorica() {
  const meses = document.getElementById('facturacion-meses').value;
  const tbody = document.getElementById('facturacion-tbody');
  tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4"><div class="spinner-border spinner-border-sm text-muted" role="status"></div> Cargando...</td></tr>';
  
  fetch(BASE + 'Ajax/dashboard_stats.php?action=facturacion_historica&meses=' + meses)
    .then(r => r.json())
    .then(res => {
      if (!res.success || !res.data.length) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">Sin datos para el período seleccionado</td></tr>';
        return;
      }
      tbody.innerHTML = '';
      res.data.forEach(row => {
        tbody.innerHTML += `<tr>
          <td class="fw-semi">${mesLabel(row.mes)}</td>
          <td><span class="badge badge-info-c">${parseInt(row.total_facturas)}</span></td>
          <td>${formatCurrency(row.subtotal)}</td>
          <td class="text-muted">${formatCurrency(row.iva)}</td>
          <td class="fw-semi text-accent">${formatCurrency(row.total)}</td>
        </tr>`;
      });
    })
    .catch(() => {
      tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger py-4">Error al cargar datos</td></tr>';
    });
}

// Load historical billing when the period changes
document.getElementById('facturacion-meses')?.addEventListener('change', cargarFacturacionHistorica);
</script>

<?php include ROOT . '/Vista/Includes/footer.php'; ?>
