<?php
// Vista/facturacion/publica.php — Factura pública segura para clientes (sin login, accesible solo con Token único)
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/Config/database.php';
require_once ROOT . '/Config/config.php';
require_once ROOT . '/Config/bcv.php';
require_once ROOT . '/Controlador/FacturaController.php';
require_once ROOT . '/Controlador/EmpresaController.php';

$token = trim($_GET['token'] ?? '');
if (empty($token) || strlen($token) < 20 || !preg_match('/^[a-f0-9]+$/i', $token)) {
    http_response_code(404);
    die('<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Factura no encontrada</title><link rel="stylesheet" href="' . BASE_URL . 'Assets/Css/style.css"></head><body class="d-flex align-items-center justify-content-center" style="min-height:100vh;text-align:center;"><div style="max-width:400px;padding:30px;background:#fff;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,0.08);"><h2>Factura no disponible</h2><p class="text-muted">El enlace proporcionado no es válido o ha expirado.</p></div></body></html>');
}

$ctrl = new FacturaController();
$factura = $ctrl->getByToken($token);

if (!$factura) {
    http_response_code(404);
    die('<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Factura no encontrada</title><link rel="stylesheet" href="' . BASE_URL . 'Assets/Css/style.css"></head><body class="d-flex align-items-center justify-content-center" style="min-height:100vh;text-align:center;"><div style="max-width:400px;padding:30px;background:#fff;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,0.08);"><h2>Factura no encontrada</h2><p class="text-muted">No se localizó ningún comprobante asociado a este enlace.</p></div></body></html>');
}

$empCtrl = new EmpresaController();
$empresa = $empCtrl->getDatos();
$detalle = $ctrl->getDetalleByVenta($factura['venta_id']);

$tasaBCV = getBcvRate() ?? TASA_BCV;
$totalBs = ((float)$factura['total']) * $tasaBCV;
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Comprobante de Factura <?= htmlspecialchars($factura['numero_factura']) ?> | <?= htmlspecialchars($empresa['nombre'] ?? 'TENKI') ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>Assets/Css/style.css">
  <style>
    @media print {
      .no-print { display: none !important; }
      body { background: #fff !important; color: #000 !important; }
      .invoice-wrap { max-width: 100%; box-shadow: none; border: none; padding: 0 !important; margin: 0 !important; }
    }
    body {
      background: #f8fafc;
      color: #1e293b;
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }
    .invoice-wrap {
      max-width: 800px; margin: 24px auto 40px auto; background: #ffffff;
      border: 1px solid #e2e8f0; border-radius: 16px;
      padding: 48px 56px; box-shadow: 0 10px 30px rgba(0,0,0,0.04);
    }
    .invoice-header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:40px; }
    .company-name { font-size:32px; font-weight:800; color:var(--accent, #f97316); letter-spacing:2px; }
    .company-sub  { font-size:12px; color:#64748b; margin-top:4px; line-height: 1.5; }
    .invoice-number { text-align:right; }
    .invoice-number .num { font-size:22px; font-weight:800; color:#0f172a; }
    .invoice-number .date { font-size:13px; color:#64748b; margin-top:4px; }
    .invoice-parties { display:grid; grid-template-columns:1fr 1fr; gap:24px; margin-bottom:32px; }
    .party-label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:#94a3b8; margin-bottom:8px; }
    .invoice-table { width:100%; border-collapse:collapse; margin-bottom:24px; }
    .invoice-table thead th { padding:12px 14px; background:rgba(249,115,22,.08); color:var(--accent, #f97316); font-size:11px; font-weight:700; text-transform:uppercase; text-align:left; }
    .invoice-table tbody td { padding:14px 14px; border-bottom:1px solid #f1f5f9; font-size:14px; }
    .invoice-totals { margin-left:auto; width:280px; }
    .total-row { display:flex; justify-content:space-between; padding:6px 0; font-size:14px; }
    .total-row.grand { border-top:2px solid var(--accent, #f97316); margin-top:8px; padding-top:10px; font-size:19px; font-weight:800; color:var(--accent, #f97316); }
    .invoice-footer { margin-top:40px; text-align:center; font-size:12px; color:#94a3b8; border-top:1px solid #f1f5f9; padding-top:24px; }

    /* Public top bar */
    .public-actions-bar {
      max-width: 800px;
      margin: 24px auto 0 auto;
      padding: 12px 20px;
      background: #ffffff;
      border: 1px solid #e2e8f0;
      border-radius: 12px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      box-shadow: 0 4px 12px rgba(0,0,0,0.03);
    }
    .btn-print-custom {
      background: linear-gradient(135deg, var(--accent, #f97316) 0%, #ea580c 100%);
      color: #fff !important;
      font-weight: 600;
      font-size: 14px;
      padding: 8px 18px;
      border-radius: 8px;
      border: none;
      box-shadow: 0 2px 8px rgba(249, 115, 22, 0.35);
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      transition: all 0.2s ease;
    }
    .btn-print-custom:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 16px rgba(249, 115, 22, 0.45);
    }
  </style>
</head>
<body>

<div class="no-print">
  <div class="public-actions-bar">
    <div class="d-flex align-items-center gap-2">
      <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
        <i class="bi bi-shield-check me-1"></i>Comprobante Verificado
      </span>
    </div>
    <div>
      <button onclick="window.print()" class="btn-print-custom">
        <i class="bi bi-printer-fill"></i> Imprimir / Guardar en PDF
      </button>
    </div>
  </div>
</div>

<div class="invoice-wrap">
  <!-- HEADER -->
  <div class="invoice-header">
    <div class="d-flex align-items-center gap-3">
      <img src="<?= BASE_URL ?>Assets/img/logo_icon.png" alt="Logo" style="width: 58px; height: 58px; object-fit: contain;">
      <div>
        <div class="company-name"><?= htmlspecialchars($empresa['nombre'] ?? 'TENKI') ?></div>
        <div class="company-sub">
          <?= htmlspecialchars($empresa['descripcion'] ?? 'Repuestos Automotrices') ?><br>
          RIF/NIT: <?= htmlspecialchars($empresa['rif'] ?? 'J-000000000-0') ?><br>
          <?= htmlspecialchars($empresa['direccion'] ?? 'Av. Principal, Ciudad') ?>
        </div>
      </div>
    </div>
    <div class="invoice-number">
      <div style="font-size:11px;color:#94a3b8;text-transform:uppercase;letter-spacing:1px;font-weight:600;">Factura de Venta</div>
      <div class="num"><?= htmlspecialchars($factura['numero_factura']) ?></div>
      <div class="date">Fecha: <?= date('d/m/Y', strtotime($factura['fecha'])) ?></div>
    </div>
  </div>

  <!-- PARTIES -->
  <div class="invoice-parties">
    <div>
      <div class="party-label">Emisor</div>
      <div class="fw-bold text-dark"><?= htmlspecialchars($empresa['nombre'] ?? 'TENKI') ?></div>
      <div class="text-muted" style="font-size:13px">RIF: <?= htmlspecialchars($empresa['rif'] ?? 'J-000000000-0') ?></div>
      <div class="text-muted" style="font-size:13px"><?= htmlspecialchars($empresa['direccion'] ?? 'Av. Principal, Ciudad') ?></div>
      <?php if (!empty($empresa['telefono'])): ?>
      <div class="text-muted" style="font-size:13px">Tel: <?= htmlspecialchars($empresa['telefono']) ?></div>
      <?php endif; ?>
    </div>
    <div>
      <div class="party-label">Cliente</div>
      <div class="fw-bold text-dark"><?= htmlspecialchars($factura['cliente_nombre'] ?? 'Cliente General') ?></div>
      <?php if (!empty($factura['cliente_doc'])): ?>
      <div class="text-muted" style="font-size:13px">Doc: <?= htmlspecialchars($factura['cliente_doc']) ?></div>
      <?php endif; ?>
      <?php if (!empty($factura['cliente_tel'])): ?>
      <div class="text-muted" style="font-size:13px">Tel: <?= htmlspecialchars($factura['cliente_tel']) ?></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ITEMS -->
  <table class="invoice-table">
    <thead>
      <tr>
        <th style="width: 15%">Código</th>
        <th>Descripción</th>
        <th style="width: 12%">Cant.</th>
        <th style="width: 20%">Precio Unit.</th>
        <th style="width: 20%; text-align:right">Subtotal</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($detalle as $d): ?>
      <tr>
        <td class="text-muted font-monospace"><?= !empty($d['codigo']) ? htmlspecialchars($d['codigo']) : '—' ?></td>
        <td class="fw-semibold text-dark"><?= htmlspecialchars($d['producto_nombre']) ?></td>
        <td><?= $d['cantidad'] ?></td>
        <td><?= formatMoney((float)$d['precio_unitario']) ?></td>
        <td style="text-align:right;font-weight:700"><?= formatMoney((float)$d['subtotal']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <!-- TOTALS -->
  <div class="invoice-totals">
    <div class="total-row"><span class="text-muted">Subtotal:</span><span><?= formatMoney((float)$factura['subtotal']) ?></span></div>
    <?php if ((float)$factura['iva'] > 0): ?>
    <div class="total-row"><span class="text-muted">IVA (16%):</span><span><?= formatMoney((float)$factura['iva']) ?></span></div>
    <?php else: ?>
    <div class="total-row"><span class="text-muted">IVA:</span><span style="font-style:italic;color:#94a3b8">Exento</span></div>
    <?php endif; ?>
    <div class="total-row grand"><span>TOTAL USD:</span><span><?= formatMoney((float)$factura['total']) ?></span></div>
    <div class="total-row mt-1" style="font-size:13px; color:#64748b;">
      <span>Equiv. BCV:</span>
      <span class="fw-bold text-dark">Bs <?= number_format($totalBs, 2, ',', '.') ?></span>
    </div>
  </div>

  <?php if (!empty($factura['observaciones'])): ?>
  <div style="margin-top:24px;padding:14px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;color:#475569">
    <span class="fw-bold">Observaciones: </span><?= htmlspecialchars($factura['observaciones']) ?>
  </div>
  <?php endif; ?>

  <!-- FOOTER -->
  <div class="invoice-footer">
    <p class="mb-1">Gracias por su compra • <?= htmlspecialchars($empresa['nombre'] ?? 'TENKI') ?></p>
    <p class="mb-0">Este documento es su comprobante digital de compra.</p>
  </div>
</div>

</body>
</html>
