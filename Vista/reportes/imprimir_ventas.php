<?php
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/Config/database.php';
require_once ROOT . '/Config/config.php';
require_once ROOT . '/Config/auth.php';
requireAdmin();

require_once ROOT . '/Controlador/ReporteController.php';
require_once ROOT . '/Controlador/EmpresaController.php';

$desde = $_GET['desde'] ?? date('Y-m-01');
$hasta = $_GET['hasta'] ?? date('Y-m-d');
$estado = $_GET['estado'] ?? 'todas';

$repCtrl = new ReporteController();
$empCtrl = new EmpresaController();

$ventas = $repCtrl->getReporteVentas($desde, $hasta, $estado);
$empresa = $empCtrl->getDatos();

// Calculus
$totalVentas = 0;
foreach ($ventas as $v) {
    if ($v['estado'] === 'completada' || $estado === 'todas') { 
        if ($v['estado'] !== 'anulada') {
            $totalVentas += $v['total'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>&nbsp;</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <style>
    @page {
      size: portrait;
      margin: 8mm 12mm;
    }
    body {
      background-color: #fff;
      color: #1e293b;
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      font-size: 13px;
      padding: 0;
      margin: 0;
    }
    .report-container {
      max-width: 100%;
      margin: 0 auto;
      padding: 10px 0;
    }
    .report-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 1.25rem;
      padding-bottom: 0.85rem;
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
    .table th {
      background-color: #f1f5f9 !important;
      color: #334155;
      font-weight: 700;
      font-size: 11px;
      text-transform: uppercase;
      letter-spacing: 0.3px;
      border-color: #94a3b8 !important;
    }
    .table td {
      border-color: #cbd5e1 !important;
      font-size: 11.5px;
      vertical-align: middle;
    }
    .summary-box {
      background: #f8fafc;
      padding: 1rem 1.25rem;
      border-radius: 8px;
      margin-top: 1.25rem;
      text-align: right;
      border: 1px solid #cbd5e1;
    }
    .summary-box h4 {
      margin: 0;
      font-weight: 800;
      font-size: 16px;
      color: #0f172a;
    }
    @media print {
      body { padding: 0; background: #fff !important; }
      .no-print { display: none !important; }
    }
  </style>
</head>
<body>

<div class="report-container">

<div class="no-print d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
    <div class="text-muted small"><i class="bi bi-info-circle me-1"></i>Para quitar la fecha y título del navegador en la hoja, desactiva <strong>"Encabezados y pies de página"</strong> en las opciones de impresión.</div>
    <div class="d-flex gap-2">
        <button onclick="window.close()" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x-lg me-1"></i>Cerrar</button>
        <button onclick="window.print()" class="btn btn-primary btn-sm"><i class="bi bi-printer me-1"></i>Imprimir / Guardar PDF</button>
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
        <div class="report-title-badge">Reporte de Ventas al por Menor</div>
        <div class="report-meta-text">
            <strong>Período:</strong> <?= date('d/m/Y', strtotime($desde)) ?> al <?= date('d/m/Y', strtotime($hasta)) ?><br>
            <strong>Filtro Estado:</strong> <?= strtoupper($estado) ?><br>
            <strong>Emisión:</strong> <?= date('d/m/Y h:i A') ?>
        </div>
    </div>
</div>

<table class="table table-sm table-bordered">
    <thead>
        <tr>
            <th>N° Venta</th>
            <th>Fecha</th>
            <th>Cliente</th>
            <th>Estado</th>
            <th class="text-end">Monto Total</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($ventas)): ?>
            <tr><td colspan="7" class="text-center text-muted">No se encontraron ventas en este período.</td></tr>
        <?php else: ?>
            <?php foreach ($ventas as $v): ?>
            <tr>
                <td class="fw-bold"><?= htmlspecialchars($v['numero_venta']) ?></td>
                <td><?= date('d/m/Y H:i', strtotime($v['fecha'])) ?></td>
                <td><?= htmlspecialchars($v['cliente'] ?? 'Cliente General') ?></td>
                <td>
                    <?php if($v['estado'] === 'completada'): ?>
                        <span class="text-success fw-semi">● Completada</span>
                    <?php else: ?>
                        <span class="text-danger fw-semi">○ Anulada</span>
                    <?php endif; ?>
                </td>
                <td class="text-end"><?= number_format((float)$v['total'], 2, ',', '.') ?></td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<div class="summary-box">
    <div class="mb-1 text-muted">Ventas Netas Efectivas</div>
    <h4>Total Base: <?= number_format($totalVentas, 2, ',', '.') ?></h4>
</div>

</div><!-- /.report-container -->

<script>
    // Auto print on load con breve retardo para cargar imagen
    window.onload = function() {
        setTimeout(() => { window.print(); }, 250);
    }
</script>
</body>
</html>
