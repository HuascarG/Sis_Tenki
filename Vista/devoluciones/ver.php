<?php
// Vista/devoluciones/ver.php
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/Config/database.php';
require_once ROOT . '/Config/config.php';
require_once ROOT . '/Config/auth.php';
requireLogin();

require_once ROOT . '/Config/bcv.php';
require_once ROOT . '/Controlador/DevolucionController.php';
require_once ROOT . '/Controlador/EmpresaController.php';

$ctrl = new DevolucionController();
$empCtrl = new EmpresaController();

$id = (int)($_GET['id'] ?? 0);
$dev = $ctrl->getById($id);
if (!$dev) {
    setFlash('danger', 'Comprobante de devolución no encontrado.');
    redirect('Vista/devoluciones/index.php');
}

$empresa = $empCtrl->getDatos();
$detalle = $ctrl->getDetalle($id);

// Tasa BCV
$tasaBCV = getBcvRate() ?? TASA_BCV;
$totalBs = ((float)$dev['monto_total']) * $tasaBCV;

$resLabels = [
    'nota_credito' => 'Nota de Crédito (Saldo a favor para futuras compras)',
    'cambio_producto' => 'Cambio Físico de Producto / Repuesto',
    'reembolso_dinero' => 'Reembolso de Dinero al Cliente',
    'solo_ingreso_garantia' => 'Ingreso por Garantía Técnica / Proveedor'
];

$accionLabels = [
    'reingresar_stock' => 'Reingresado a Almacén (Apto)',
    'garantia_proveedor' => 'Apartado para Proveedor / Falla',
    'desecho_defectuoso' => 'Desecho / Inutilizable'
];

$pageTitle = 'Comprobante ' . $dev['numero_devolucion'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Comprobante <?= htmlspecialchars($dev['numero_devolucion']) ?> | <?= htmlspecialchars($empresa['nombre'] ?? 'TENKI') ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>Assets/Css/style.css">
  <style>
    @media print {
      .no-print { display: none !important; }
      body { background: #fff !important; color: #000 !important; }
      .voucher-wrap { max-width: 100%; box-shadow: none !important; border: 1px solid #cbd5e1 !important; padding: 24px !important; margin: 0 !important; }
    }
    .voucher-wrap {
      max-width: 820px;
      margin: 24px auto 40px auto;
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: var(--radius-lg);
      padding: 40px 48px;
      box-shadow: var(--shadow-card);
    }
    .voucher-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 24px;
      padding-bottom: 20px;
      border-bottom: 2px solid var(--border);
    }
    .company-brand {
      display: flex;
      align-items: center;
      gap: 14px;
    }
    .company-logo {
      width: 52px;
      height: 52px;
      object-fit: contain;
    }
    .voucher-title-box {
      text-align: right;
    }
    .voucher-code {
      font-size: 20px;
      font-weight: 800;
      color: var(--accent);
    }
    .voucher-meta-box {
      background: rgba(249, 115, 22, 0.05);
      border: 1px solid rgba(249, 115, 22, 0.2);
      border-radius: 8px;
      padding: 14px 18px;
      margin-bottom: 24px;
    }
    .actions-bar {
      max-width: 820px;
      margin: 20px auto 0 auto;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 10px;
    }
  </style>
</head>
<body>

<div class="no-print">
  <div class="actions-bar">
    <a href="<?= BASE_URL ?>Vista/devoluciones/index.php" class="btn btn-secondary btn-volver btn-sm">
      <i class="bi bi-arrow-left me-1"></i>Volver al Listado
    </a>
    <div class="d-flex gap-2">
      <a href="<?= BASE_URL ?>Vista/ventas/detalle.php?id=<?= $dev['venta_id'] ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-receipt me-1"></i>Ver Venta Original
      </a>
      <button onclick="window.print()" class="btn btn-primary btn-sm px-3 fw-bold">
        <i class="bi bi-printer me-1"></i>Imprimir Comprobante
      </button>
    </div>
  </div>
</div>

<div class="voucher-wrap">
  <!-- ENCABEZADO -->
  <div class="voucher-header">
    <div class="company-brand">
      <img src="<?= BASE_URL ?>Assets/img/logo_icon.png" alt="Logo" class="company-logo">
      <div>
        <h4 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($empresa['nombre'] ?? 'TENKI') ?></h4>
        <small class="text-muted d-block"><?= htmlspecialchars($empresa['descripcion'] ?? 'Repuestos Automotrices') ?></small>
        <small class="text-muted">RIF: <?= htmlspecialchars($empresa['rif'] ?? 'J-000000000-0') ?> | <?= htmlspecialchars($empresa['direccion'] ?? 'Av. Principal') ?></small>
      </div>
    </div>
    <div class="voucher-title-box">
      <span class="badge bg-warning text-dark mb-1 px-2 py-1">COMPROBANTE DE RECLAMO</span>
      <div class="voucher-code"><?= htmlspecialchars($dev['numero_devolucion']) ?></div>
      <small class="text-muted d-block">Fecha: <?= date('d/m/Y', strtotime($dev['fecha'])) ?></small>
    </div>
  </div>

  <!-- METADATOS Y RESOLUCIÓN -->
  <div class="voucher-meta-box">
    <div class="row g-2">
      <div class="col-12 col-md-6">
        <small class="text-muted d-block text-uppercase fw-bold" style="font-size:10px;">Cliente Solicitante</small>
        <div class="fw-bold text-dark"><?= htmlspecialchars($dev['cliente_nombre'] ?? 'Cliente General') ?></div>
        <?php if (!empty($dev['cliente_doc'])): ?>
          <small class="text-muted">Doc: <?= htmlspecialchars($dev['cliente_doc']) ?> <?= !empty($dev['cliente_tel']) ? ' | Tel: ' . htmlspecialchars($dev['cliente_tel']) : '' ?></small>
        <?php endif; ?>
      </div>
      <div class="col-12 col-md-6">
        <small class="text-muted d-block text-uppercase fw-bold" style="font-size:10px;">Venta de Procedencia</small>
        <div class="fw-bold text-dark">N° Venta: <?= htmlspecialchars($dev['numero_venta']) ?></div>
        <small class="text-muted">Fecha de Facturación: <?= date('d/m/Y', strtotime($dev['fecha_venta'])) ?></small>
      </div>
      <div class="col-12 mt-2 pt-2 border-top">
        <small class="text-muted d-block text-uppercase fw-bold" style="font-size:10px;">Tipo de Resolución Aplicada</small>
        <div class="fw-bold text-primary"><?= $resLabels[$dev['tipo_resolucion']] ?? $dev['tipo_resolucion'] ?></div>
      </div>
      <div class="col-12">
        <small class="text-muted d-block text-uppercase fw-bold" style="font-size:10px;">Motivo Manifestado</small>
        <div class="text-dark"><?= htmlspecialchars($dev['motivo']) ?></div>
      </div>
      <?php if (!empty($dev['observaciones'])): ?>
      <div class="col-12">
        <small class="text-muted d-block text-uppercase fw-bold" style="font-size:10px;">Observaciones Técnicas</small>
        <div class="text-muted fst-italic"><?= htmlspecialchars($dev['observaciones']) ?></div>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- TABLA DE REPUESTOS DEVUELTOS -->
  <div class="table-responsive mb-4">
    <table class="table table-bordered align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th>Código</th>
          <th>Repuesto / Pieza</th>
          <th class="text-center">Cant.</th>
          <th class="text-end">PVP Unit.</th>
          <th>Tratamiento de Stock</th>
          <th class="text-end">Subtotal</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($detalle as $item): ?>
        <tr>
          <td class="font-monospace text-muted"><?= !empty($item['producto_codigo']) ? htmlspecialchars($item['producto_codigo']) : 'S/C' ?></td>
          <td>
            <div class="fw-bold"><?= htmlspecialchars($item['producto_nombre']) ?></div>
            <?php if (!empty($item['marca'])): ?>
              <small class="text-muted"><?= htmlspecialchars($item['marca']) ?></small>
            <?php endif; ?>
          </td>
          <td class="text-center fw-bold"><?= $item['cantidad'] ?></td>
          <td class="text-end">$<?= number_format((float)$item['precio_unitario'], 2) ?></td>
          <td>
            <span class="badge bg-light text-dark border">
              <?= $accionLabels[$item['accion_stock']] ?? $item['accion_stock'] ?>
            </span>
          </td>
          <td class="text-end fw-bold">$<?= number_format((float)$item['subtotal'], 2) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr>
          <th colspan="5" class="text-end text-uppercase">Monto Total de la Garantía / Devolución:</th>
          <th class="text-end fw-bold fs-5 text-primary">$<?= number_format((float)$dev['monto_total'], 2) ?></th>
        </tr>
        <tr class="table-light">
          <th colspan="5" class="text-end text-muted small">Equivalente estimado en Bolívares (Tasa BCV):</th>
          <th class="text-end fw-bold text-muted small">Bs <?= number_format($totalBs, 2, ',', '.') ?></th>
        </tr>
      </tfoot>
    </table>
  </div>

  <!-- FIRMAS -->
  <div class="row text-center mt-5 pt-4">
    <div class="col-6">
      <div style="width: 220px; border-top: 1px solid #94a3b8; margin: 0 auto; padding-top: 6px;">
        <small class="text-muted d-block fw-bold">Entregado por el Cliente</small>
        <small class="text-muted">Firma y Cédula</small>
      </div>
    </div>
    <div class="col-6">
      <div style="width: 220px; border-top: 1px solid #94a3b8; margin: 0 auto; padding-top: 6px;">
        <small class="text-muted d-block fw-bold"><?= htmlspecialchars($dev['usuario_nombre'] ?? 'Administración TENKI') ?></small>
        <small class="text-muted">Recibido en Almacén</small>
      </div>
    </div>
  </div>

  <div class="text-center mt-5 pt-3 border-top text-muted" style="font-size: 11px;">
    Documento interno y comprobante de entrega en garantía bajo las políticas de venta y reposición de repuestos automotrices.
  </div>
</div>

</body>
</html>
