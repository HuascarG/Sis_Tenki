<?php
// Vista/facturacion/ver.php — Printable invoice (Panel Interno de Empleados)
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/Config/database.php';
require_once ROOT . '/Config/config.php';
require_once ROOT . '/Config/auth.php';
requireLogin();

require_once ROOT . '/Config/bcv.php';
require_once ROOT . '/Controlador/FacturaController.php';
require_once ROOT . '/Controlador/EmpresaController.php';

$ctrl = new FacturaController();
$empCtrl = new EmpresaController();
$empresa = $empCtrl->getDatos();

// Accept both id (factura direct) and venta_id
if (isset($_GET['id'])) {
    $factura = $ctrl->getById((int)$_GET['id']);
} elseif (isset($_GET['venta_id'])) {
    $pdo  = db();
    $stmt = $pdo->prepare("SELECT fv.id FROM facturas_venta fv WHERE fv.venta_id=?");
    $stmt->execute([(int)$_GET['venta_id']]);
    $row = $stmt->fetch();
    $factura = $row ? $ctrl->getById($row['id']) : null;
} else {
    $factura = null;
}

if (!$factura) { setFlash('danger', 'Factura no encontrada.'); redirect('Vista/facturacion/index.php'); }

// Si no tiene token asignado por ser registro antiguo, asignarlo al momento
if (empty($factura['token'])) {
    $nuevoToken = bin2hex(random_bytes(24));
    db()->prepare("UPDATE facturas_venta SET token = ? WHERE id = ?")->execute([$nuevoToken, $factura['id']]);
    $factura['token'] = $nuevoToken;
}

$detalle = $ctrl->getDetalleByVenta($factura['venta_id']);

// Tasa BCV y totales
$tasaBCV = getBcvRate() ?? TASA_BCV;
$totalBs = ((float)$factura['total']) * $tasaBCV;

// Preparar teléfono de WhatsApp (normalizar indicativo Venezuela 58 si es necesario)
$rawTel = trim($factura['cliente_tel'] ?? '');
$cleanTel = preg_replace('/[^0-9]/', '', $rawTel);
if (!empty($cleanTel)) {
    if (str_starts_with($cleanTel, '0')) {
        $cleanTel = '58' . substr($cleanTel, 1);
    } elseif (strlen($cleanTel) === 10 && !str_starts_with($cleanTel, '58')) {
        $cleanTel = '58' . $cleanTel;
    }
}

// Redactar mensaje predeterminado con el enlace seguro con Token
$empresaNombre = $empresa['nombre'] ?? 'TENKI';
$clienteNombre = !empty($factura['cliente_nombre']) ? $factura['cliente_nombre'] : 'Estimado cliente';
$urlFacturaPublica = BASE_URL . "Vista/facturacion/publica.php?token=" . $factura['token'];

$waTexto = "¡Hola, {$clienteNombre}! 👋\n\n"
         . "Le enviamos el comprobante de su compra en *{$empresaNombre}*:\n"
         . "📄 *Factura:* {$factura['numero_factura']}\n"
         . "🏷 *N° de Venta:* {$factura['numero_venta']}\n"
         . "📅 *Fecha:* " . date('d/m/Y', strtotime($factura['fecha'])) . "\n"
         . "💵 *Total USD:* $" . number_format((float)$factura['total'], 2) . "\n"
         . "🇻🇪 *Total Bs:* Bs " . number_format($totalBs, 2, ',', '.') . " (Tasa BCV)\n\n"
         . "Puede ver y descargar su factura completa en el siguiente enlace:\n"
         . "🔗 {$urlFacturaPublica}\n\n"
         . "¡Muchas gracias por su preferencia!";

$waUrl = !empty($cleanTel)
    ? "https://api.whatsapp.com/send?phone=" . urlencode($cleanTel) . "&text=" . urlencode($waTexto)
    : "";
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Factura <?= htmlspecialchars($factura['numero_factura']) ?> | <?= htmlspecialchars($empresa['nombre'] ?? 'TENKI') ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>Assets/Css/style.css">
  <style>
    @media print {
      .no-print { display: none !important; }
      body { background: #fff !important; color: #000 !important; }
      .invoice-wrap { max-width: 100%; box-shadow: none; border: none; }
    }
    .invoice-wrap {
      max-width: 800px; margin: 24px auto 40px auto; background: var(--bg-card);
      border: 1px solid var(--border); border-radius: var(--radius-lg);
      padding: 48px 56px; box-shadow: var(--shadow-card);
    }
    .invoice-header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:40px; }
    .company-name { font-size:32px; font-weight:800; color:var(--accent); letter-spacing:3px; }
    .company-sub  { font-size:12px; color:var(--text-secondary); margin-top:4px; }
    .invoice-number { text-align:right; }
    .invoice-number .num { font-size:20px; font-weight:700; color:var(--text-primary); }
    .invoice-number .date { font-size:13px; color:var(--text-muted); margin-top:6px; }
    .invoice-parties { display:grid; grid-template-columns:1fr 1fr; gap:24px; margin-bottom:32px; }
    .party-label { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.5px; color:var(--text-muted); margin-bottom:8px; }
    .invoice-table { width:100%; border-collapse:collapse; margin-bottom:24px; }
    .invoice-table thead th { padding:10px 12px; background:rgba(249,115,22,.12); color:var(--accent); font-size:11px; font-weight:700; text-transform:uppercase; text-align:left; }
    .invoice-table tbody td { padding:12px 12px; border-bottom:1px solid var(--border); font-size:13.5px; }
    .invoice-totals { margin-left:auto; width:260px; }
    .total-row { display:flex; justify-content:space-between; padding:6px 0; font-size:14px; }
    .total-row.grand { border-top:2px solid var(--accent); margin-top:8px; padding-top:10px; font-size:18px; font-weight:800; color:var(--accent); }
    .invoice-footer { margin-top:40px; text-align:center; font-size:12px; color:var(--text-muted); border-top:1px solid var(--border); padding-top:20px; }

    /* Action bar styles */
    .invoice-actions-bar {
      max-width: 800px;
      margin: 24px auto 0 auto;
      padding: 14px 20px;
      background: rgba(255, 255, 255, 0.03);
      backdrop-filter: blur(8px);
      border: 1px solid var(--border);
      border-radius: var(--radius-lg);
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 12px;
      flex-wrap: wrap;
      box-shadow: 0 4px 14px rgba(0,0,0,0.06);
    }
    .btn-action-custom {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 9px 18px;
      font-weight: 600;
      font-size: 14px;
      border-radius: 8px;
      transition: all 0.25s ease;
      text-decoration: none;
      border: none;
      cursor: pointer;
    }
    .btn-action-custom:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    .btn-print-custom {
      background: linear-gradient(135deg, var(--accent, #f97316) 0%, #ea580c 100%);
      color: #fff !important;
      box-shadow: 0 2px 8px rgba(249, 115, 22, 0.35);
    }
    .btn-print-custom:hover {
      background: linear-gradient(135deg, #fb923c 0%, #ea580c 100%);
      color: #fff !important;
      box-shadow: 0 6px 16px rgba(249, 115, 22, 0.45);
    }
    .btn-view-custom {
      background: rgba(14, 165, 233, 0.12);
      color: #0284c7 !important;
      border: 1px solid rgba(14, 165, 233, 0.35);
    }
    .btn-view-custom:hover {
      background: #0284c7;
      color: #fff !important;
      border-color: #0284c7;
    }
    .btn-whatsapp-custom {
      background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
      color: #fff !important;
      box-shadow: 0 2px 8px rgba(37, 211, 102, 0.35);
    }
    .btn-whatsapp-custom:hover {
      background: linear-gradient(135deg, #2ee06e 0%, #0d6e62 100%);
      color: #fff !important;
      box-shadow: 0 6px 16px rgba(37, 211, 102, 0.45);
    }
    .btn-back-custom {
      background: rgba(100, 116, 139, 0.1);
      color: var(--text-secondary, #64748b) !important;
      border: 1px solid var(--border);
    }
    .btn-back-custom:hover {
      background: var(--border, #e2e8f0);
      color: var(--text-primary, #1e293b) !important;
    }
  </style>
</head>
<body>
<div class="no-print">
  <div class="invoice-actions-bar">
    <div>
      <a href="<?= BASE_URL ?>Vista/facturacion/index.php" class="btn-action-custom btn-back-custom">
        <i class="bi bi-arrow-left"></i> Volver a Facturación
      </a>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <button type="button" onclick="enviarFacturaWhatsApp()" class="btn-action-custom btn-whatsapp-custom" title="Enviar factura por WhatsApp al cliente">
        <i class="bi bi-whatsapp"></i> Enviar por WhatsApp
      </button>
      <a href="<?= BASE_URL ?>Vista/ventas/detalle.php?id=<?= $factura['venta_id'] ?>" class="btn-action-custom btn-view-custom">
        <i class="bi bi-eye"></i> Ver Venta
      </a>
      <button onclick="window.print()" class="btn-action-custom btn-print-custom">
        <i class="bi bi-printer-fill"></i> Imprimir Factura
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
      <div style="font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:4px">Factura</div>
      <div class="num"><?= htmlspecialchars($factura['numero_factura']) ?></div>
      <div class="date">Fecha: <?= date('d/m/Y', strtotime($factura['fecha'])) ?></div>
      <div class="date">Venta: <?= htmlspecialchars($factura['numero_venta']) ?></div>
    </div>
  </div>

  <!-- PARTIES -->
  <div class="invoice-parties">
    <div>
      <div class="party-label">Vendedor</div>
      <div class="fw-semi"><?= htmlspecialchars($empresa['nombre'] ?? 'TENKI') ?></div>
      <div class="text-muted" style="font-size:13px">RIF: <?= htmlspecialchars($empresa['rif'] ?? 'J-000000000-0') ?></div>
      <div class="text-muted" style="font-size:13px"><?= htmlspecialchars($empresa['direccion'] ?? 'Av. Principal, Ciudad') ?></div>
      <?php if (!empty($empresa['telefono'])): ?>
      <div class="text-muted" style="font-size:13px">Tel: <?= htmlspecialchars($empresa['telefono']) ?></div>
      <?php endif; ?>
    </div>
    <div>
      <div class="party-label">Cliente</div>
      <div class="fw-semi"><?= htmlspecialchars($factura['cliente_nombre'] ?? 'Cliente General') ?></div>
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
      <tr><th>Código</th><th>Producto</th><th>Cant.</th><th>Precio Unit.</th><th style="text-align:right">Subtotal</th></tr>
    </thead>
    <tbody>
      <?php foreach ($detalle as $d): ?>
      <tr>
        <td><?= htmlspecialchars($d['codigo']) ?></td>
        <td><?= htmlspecialchars($d['producto_nombre']) ?></td>
        <td><?= $d['cantidad'] ?></td>
        <td><?= formatMoney((float)$d['precio_unitario']) ?></td>
        <td style="text-align:right;font-weight:600"><?= formatMoney((float)$d['subtotal']) ?></td>
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
    <div class="total-row"><span class="text-muted">IVA:</span><span style="font-style:italic;color:var(--text-muted)">Exento</span></div>
    <?php endif; ?>
    <div class="total-row grand"><span>TOTAL:</span><span><?= formatMoney((float)$factura['total']) ?></span></div>
  </div>

  <?php if (!empty($factura['observaciones'])): ?>
  <div style="margin-top:24px;padding:14px;background:rgba(255,255,255,.03);border-radius:8px;font-size:13px;color:var(--text-muted)">
    <span class="fw-semi">Observaciones: </span><?= htmlspecialchars($factura['observaciones']) ?>
  </div>
  <?php endif; ?>

  <!-- FOOTER -->
  <div class="invoice-footer">
    <p>Gracias por su preferencia • <?= htmlspecialchars($empresa['nombre'] ?? 'TENKI') ?></p>
    <p style="margin-top:4px">Esta factura es su comprobante de compra. Consérvela.</p>
  </div>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?= BASE_URL ?>Assets/Js/main.js"></script>
<script>
const waData = {
    url: <?= json_encode($waUrl) ?>,
    phone: <?= json_encode($cleanTel) ?>,
    text: <?= json_encode($waTexto) ?>
};

function enviarFacturaWhatsApp() {
    if (waData.phone) {
        // El cliente ya tiene teléfono registrado: abrir directamente WhatsApp
        window.open(waData.url, '_blank');
    } else {
        // Si es cliente general o no tiene teléfono registrado, pedir el número mediante modal
        Swal.fire({
            title: 'Enviar por WhatsApp',
            text: 'El cliente no tiene teléfono registrado. Ingrese el número de WhatsApp al que desea enviar la factura:',
            input: 'tel',
            inputLabel: 'Número de WhatsApp (Ej: 04141234567 o 584141234567)',
            inputPlaceholder: '04141234567',
            showCancelButton: true,
            confirmButtonText: '<i class="bi bi-whatsapp me-1"></i> Abrir WhatsApp',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#25D366',
            cancelButtonColor: '#64748b',
            inputValidator: (value) => {
                const cleaned = (value || '').replace(/[^0-9]/g, '');
                if (!cleaned || cleaned.length < 10) {
                    return 'Por favor ingrese un número telefónico válido (mínimo 10 dígitos)';
                }
            }
        }).then((result) => {
            if (result.isConfirmed && result.value) {
                let tel = result.value.replace(/[^0-9]/g, '');
                if (tel.startsWith('0')) {
                    tel = '58' + tel.substring(1);
                } else if (tel.length === 10 && !tel.startsWith('58')) {
                    tel = '58' + tel;
                }
                const customUrl = "https://api.whatsapp.com/send?phone=" + encodeURIComponent(tel) + "&text=" + encodeURIComponent(waData.text);
                window.open(customUrl, '_blank');
            }
        });
    }
}
</script>
</body>
</html>
