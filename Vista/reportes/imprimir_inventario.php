<?php
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/Config/database.php';
require_once ROOT . '/Config/config.php';
require_once ROOT . '/Config/auth.php';
requireAdmin();

require_once ROOT . '/Controlador/ReporteController.php';
require_once ROOT . '/Controlador/EmpresaController.php';

$tipo = $_GET['tipo'] ?? 'todos'; // 'todos' or 'bajo_stock'
$categoria_id = (int)($_GET['categoria_id'] ?? 0);

$repCtrl = new ReporteController();
$empCtrl = new EmpresaController();

$productos = $repCtrl->getReporteInventario($tipo, $categoria_id);
$empresa = $empCtrl->getDatos();

// Calculus
$totalCosto = 0;
$totalVenta = 0;
$totalPiezas = 0;
foreach ($productos as $p) {
    if ($p['stock'] > 0) {
        $totalCosto += ($p['precio_compra'] * $p['stock']);
        $totalVenta += ($p['precio_venta'] * $p['stock']);
        $totalPiezas += $p['stock'];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>&nbsp;</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <style>
    @page {
      size: portrait;
      margin: 8mm 10mm;
    }
    *, *::before, *::after {
      box-sizing: border-box;
      -webkit-print-color-adjust: exact !important;
      print-color-adjust: exact !important;
    }
    body {
      background-color: #ffffff !important;
      color: #0f172a;
      font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
      font-size: 11px;
      line-height: 1.35;
      padding: 0;
      margin: 0;
      -webkit-font-smoothing: antialiased;
      -moz-osx-font-smoothing: grayscale;
      text-rendering: optimizeLegibility;
    }
    .report-container {
      max-width: 100%;
      margin: 0 auto;
      padding: 0;
    }
    .report-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 0.85rem;
      padding-bottom: 0.75rem;
      border-bottom: 2px solid #0f172a;
    }
    .report-brand {
      display: flex;
      align-items: center;
      gap: 14px;
    }
    .report-logo {
      width: 48px;
      height: 48px;
      object-fit: contain;
    }
    .company-title {
      font-size: 17px;
      font-weight: 800;
      color: #0f172a;
      letter-spacing: 1px;
      line-height: 1.1;
      margin-bottom: 2px;
    }
    .company-sub {
      font-size: 10px;
      color: #64748b;
      line-height: 1.3;
    }
    .report-info {
      text-align: right;
    }
    .report-title-badge {
      display: inline-block;
      font-size: 12px;
      font-weight: 800;
      letter-spacing: 0.5px;
      color: #0f172a;
      text-transform: uppercase;
      background: #f1f5f9;
      border: 1px solid #cbd5e1;
      padding: 3px 10px;
      border-radius: 4px;
      margin-bottom: 4px;
    }
    .report-meta-text {
      font-size: 10px;
      color: #475569;
      line-height: 1.35;
    }
    .table-report {
      width: 100%;
      border-collapse: collapse;
      margin-top: 0.5rem;
    }
    .table-report th {
      background-color: #f1f5f9 !important;
      color: #0f172a !important;
      font-weight: 700;
      font-size: 10.5px;
      text-transform: uppercase;
      letter-spacing: 0.3px;
      border: 1px solid #94a3b8 !important;
      padding: 5px 7px;
    }
    .table-report td {
      border: 1px solid #cbd5e1 !important;
      font-size: 11px;
      vertical-align: middle;
      padding: 5px 7px;
      color: #1e293b;
    }
    .table-report tbody tr:nth-child(even) {
      background-color: #f8fafc;
    }
    .col-codigo {
      width: 14%;
      font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
      font-size: 10.5px;
      font-weight: 600;
      word-break: break-all;
    }
    .col-producto {
      width: 33%;
    }
    .col-categoria {
      width: 14%;
    }
    .col-estado {
      width: 9%;
    }
    .col-stock {
      width: 10%;
    }
    .col-costo {
      width: 10%;
    }
    .col-pvp {
      width: 10%;
    }
    .summary-box {
      background: #f8fafc;
      padding: 0.85rem 1.25rem;
      border-radius: 6px;
      margin-top: 1rem;
      text-align: right;
      border: 1px solid #cbd5e1;
      page-break-inside: avoid;
    }
    .summary-box h5 {
      font-size: 11.5px;
      font-weight: 600;
      color: #475569;
      margin-bottom: 2px;
    }
    .summary-box h4 {
      margin: 4px 0 0 0;
      font-weight: 800;
      font-size: 14.5px;
      color: #0f172a;
    }
    @media print {
      body { background: #fff !important; }
      .no-print { display: none !important; }
      .table-report { page-break-inside: auto; }
      .table-report tr { page-break-inside: avoid; page-break-after: auto; }
      .table-report thead { display: table-header-group; }
    }
  </style>
</head>
<body>

<div class="report-container">

<div class="no-print d-flex justify-content-between align-items-center mb-3 p-3 bg-light border rounded">
    <div>
        <div class="fw-bold text-dark"><i class="bi bi-file-earmark-pdf me-1 text-danger"></i>Vista Previa de Impresión / Guardar PDF</div>
        <small class="text-muted">Asegúrate de tener activada la opción <strong>"Gráficos de fondo"</strong> en los ajustes de impresión para máxima nitidez.</small>
    </div>
    <div class="d-flex gap-2">
        <button onclick="window.close()" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x-lg me-1"></i>Cerrar</button>
        <button onclick="window.print()" class="btn btn-primary btn-sm px-3 fw-bold"><i class="bi bi-printer me-1"></i>Imprimir / Guardar PDF</button>
    </div>
</div>

<div class="report-header">
    <div class="report-brand">
        <img src="<?= BASE_URL ?>Assets/img/logo_icon.png" alt="Logo" class="report-logo">
        <div>
            <div class="company-title"><?= htmlspecialchars($empresa['nombre'] ?? 'TENKI') ?></div>
            <div class="company-sub">
                <?= htmlspecialchars($empresa['descripcion'] ?? 'Repuestos Automotrices') ?><br>
                RIF: <?= htmlspecialchars($empresa['rif'] ?? 'J-000000000-0') ?> | <?= htmlspecialchars($empresa['direccion'] ?? 'Av. Principal') ?>
            </div>
        </div>
    </div>
    <div class="report-info">
        <div class="report-title-badge">Auditoría Física de Inventario</div>
        <div class="report-meta-text">
            <strong>Tipo:</strong> <?= $tipo === 'bajo_stock' ? 'Bajo Stock (Crítico)' : 'Inventario General Valorado' ?><br>
            <strong>Filtro Categoría:</strong> <?= $categoria_id > 0 ? "Cat. #$categoria_id" : 'Todas las categorías' ?><br>
            <strong>Emisión:</strong> <?= date('d/m/Y h:i A') ?>
        </div>
    </div>
</div>

<table class="table-report">
    <thead>
        <tr>
            <th class="col-codigo">Código</th>
            <th class="col-producto">Producto</th>
            <th class="col-categoria">Categoría</th>
            <th class="col-estado">Condición</th>
            <th class="text-center col-stock">Stock</th>
            <th class="text-end col-costo">Costo</th>
            <th class="text-end col-pvp">PVP Unit.</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($productos)): ?>
            <tr><td colspan="7" class="text-center text-muted py-3">No existen registros para los criterios evaluados.</td></tr>
        <?php else: ?>
            <?php foreach ($productos as $p): ?>
            <tr>
                <td class="col-codigo fw-bold text-dark"><?= !empty($p['codigo']) ? htmlspecialchars($p['codigo']) : '<span class="text-muted fw-normal">S/C</span>' ?></td>
                <td class="col-producto"><?= htmlspecialchars($p['nombre'] ?? '') ?><?= !empty($p['marca']) ? ' - <span class="text-muted">' . htmlspecialchars($p['marca']) . '</span>' : '' ?></td>
                <td class="col-categoria text-muted"><?= htmlspecialchars($p['categoria'] ?? 'Sin categoría') ?></td>
                <td class="col-estado"><?= ucfirst($p['condicion'] ?? 'Nuevo') ?></td>
                <td class="text-center fw-bold col-stock <?= ($p['stock'] ?? 0) <= ($p['stock_minimo'] ?? 0) ? 'text-danger' : 'text-success' ?>">
                    <?= $p['stock'] ?? 0 ?> <small class="text-muted fw-normal"><?= htmlspecialchars($p['unidad'] ?? 'und') ?></small>
                </td>
                <td class="text-end col-costo">$<?= number_format((float)($p['precio_compra'] ?? 0), 2, ',', '.') ?></td>
                <td class="text-end fw-semi col-pvp">$<?= number_format((float)($p['precio_venta'] ?? 0), 2, ',', '.') ?></td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<div class="summary-box">
    <div class="mb-1 text-muted">Métricas Generales del Almacén</div>
    <h5 class="text-muted">Total Unidades Físicas: <?= number_format($totalPiezas) ?></h5>
    <h5 class="text-muted">Costo Patrimonial (Inversión Estática): $<?= number_format($totalCosto, 2, ',', '.') ?></h5>
    <h4 class="mt-2 text-primary">Valorización Esperada de Venta (PVP): $<?= number_format($totalVenta, 2, ',', '.') ?></h4>
</div>

<div class="mt-5 text-center text-muted" style="font-size:12px;">
    Documento generado automáticamente por el sistema TENKI bajo el esquema de seguridad administrativa.
</div><!-- /.report-container -->

<script>
    // Esperar a que el logo y las fuentes vectoriales carguen completamente para renderizar en alta resolución
    window.addEventListener('load', () => {
        if (document.fonts) {
            document.fonts.ready.then(() => {
                setTimeout(() => { window.print(); }, 150);
            });
        } else {
            setTimeout(() => { window.print(); }, 200);
        }
    });
</script>
</body>
</html>
