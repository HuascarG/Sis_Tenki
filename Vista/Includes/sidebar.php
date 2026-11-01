<?php
$nav = [
    ['url' => '',                         'label' => 'Dashboard',   'mod' => 'dashboard',   'icon' => 'bi-speedometer2'],
    ['url' => 'Vista/inventario/index.php','label' => 'Inventario', 'mod' => 'inventario',  'icon' => 'bi-box-seam'],
    ['url' => 'Vista/ventas/index.php',    'label' => 'Ventas',     'mod' => 'ventas',      'icon' => 'bi-cart3'],
    ['url' => 'Vista/facturacion/index.php','label'=> 'Facturación','mod' => 'facturacion', 'icon' => 'bi-file-earmark-text'],
    ['url' => 'Vista/compras/index.php',   'label' => 'Compras',    'mod' => 'compras',     'icon' => 'bi-truck'],
    ['url' => 'Vista/creditos/index.php',  'label' => 'Créditos',   'mod' => 'creditos',    'icon' => 'bi-credit-card-2-front'],
    ['url' => 'Vista/devoluciones/index.php','label'=> 'Garantías/Dev.','mod' => 'devoluciones','icon' => 'bi-arrow-left-right'],
    ['url' => 'Vista/clientes/index.php',  'label' => 'Clientes',   'mod' => 'clientes',    'icon' => 'bi-people'],
    ['url' => 'Vista/proveedores/index.php','label'=> 'Proveedores','mod' => 'proveedores', 'icon' => 'bi-building'],
];
$navAdmin = [
    ['url' => 'Vista/reportes/index.php',  'label' => 'Reportes',     'mod' => 'reportes',    'icon' => 'bi-file-earmark-bar-graph'],
    ['url' => 'Vista/empresa/index.php',   'label' => 'Datos Empresa','mod' => 'empresa',     'icon' => 'bi-building-gear'],
    ['url' => 'Vista/usuarios/index.php',  'label' => 'Usuarios',     'mod' => 'usuarios',    'icon' => 'bi-person-gear'],
    ['url' => 'Vista/backups/index.php',   'label' => 'Sit. y Backups','mod' => 'backups',   'icon' => 'bi-hdd-network'],
];

$sidebarContent = function($isMobile = false) use ($nav, $navAdmin, $currentModule) {
?>
  <div class="sidebar-brand">
    <a href="<?= BASE_URL ?>" class="sidebar-brand-link">
      <img src="<?= BASE_URL ?>Assets/img/logo_icon.png" alt="TENKI Logo" class="sidebar-brand-logo">
      <div class="brand-text-wrap">
        <div class="brand-name">TENKI</div>
        <div class="brand-sub">Repuestos Automotrices</div>
      </div>
    </a>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-section-label">Menú Principal</div>
    <?php foreach ($nav as $item):
      $active = ($currentModule === $item['mod']);
    ?>
    <a href="<?= BASE_URL . $item['url'] ?>" class="nav-item <?= $active ? 'active' : '' ?>">
      <i class="bi <?= $item['icon'] ?> nav-icon-i"></i>
      <?= $item['label'] ?>
    </a>
    <?php endforeach; ?>

    <?php if (function_exists('isAdmin') && isAdmin()): ?>
    <div class="nav-section-label mt-3">Administración</div>
    <?php foreach ($navAdmin as $item):
      $active = ($currentModule === $item['mod']);
    ?>
    <a href="<?= BASE_URL . $item['url'] ?>" class="nav-item <?= $active ? 'active' : '' ?>">
      <i class="bi <?= $item['icon'] ?> nav-icon-i"></i>
      <?= $item['label'] ?>
    </a>
    <?php endforeach; ?>
    <?php endif; ?>
  </nav>

  <!-- Botón inferior para retraer el menú lateral -->
  <div class="sidebar-collapse-wrapper">
    <?php if ($isMobile): ?>
    <button type="button" class="btn-sidebar-collapse" data-bs-dismiss="offcanvas" title="Cerrar menú lateral">
      <i class="bi bi-chevron-bar-left"></i>
      <span>Cerrar menú</span>
    </button>
    <?php else: ?>
    <button type="button" class="btn-sidebar-collapse" id="btnSidebarCollapse" onclick="toggleSidebarRetract(true)" title="Retraer menú lateral (pantalla completa)">
      <i class="bi bi-chevron-bar-left"></i>
      <span>Retraer menú</span>
    </button>
    <?php endif; ?>
  </div>

  <div class="sidebar-footer">
    <div>&copy; <?= date('Y') ?> TENKI &mdash; Todos los derechos reservados</div>
  </div>
<?php };
?>

<!-- DESKTOP SIDEBAR -->
<aside class="sidebar d-none d-lg-flex" id="desktopSidebar">
  <?php $sidebarContent(false); ?>
</aside>

<!-- MOBILE OFFCANVAS -->
<div class="offcanvas offcanvas-start sidebar-offcanvas" tabindex="-1" id="sidebarOffcanvas" aria-labelledby="sidebarOffcanvasLabel">
  <div class="offcanvas-header justify-content-end pb-0">
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
  </div>
  <div class="offcanvas-body p-0 d-flex flex-column">
    <?php $sidebarContent(true); ?>
  </div>
</div>

<!-- BOTÓN FLOTANTE PARA EXPANDIR SIDEBAR (VISIBLE AL ESTAR RETRAÍDO EN ESCRITORIO) -->
<button type="button" class="sidebar-floating-toggle d-none d-lg-flex" id="sidebarFloatingToggle" onclick="toggleSidebarRetract(false)" title="Expandir menú lateral">
  <i class="bi bi-chevron-bar-right"></i>
  <span>Mostrar menú</span>
</button>

<style id="sidebar-retract-custom-style">
  /* Transiciones y comportamiento fluido */
  .sidebar {
    transition: transform 0.28s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.28s ease !important;
  }
  .main-wrapper {
    transition: margin-left 0.28s cubic-bezier(0.4, 0, 0.2, 1), width 0.28s cubic-bezier(0.4, 0, 0.2, 1) !important;
  }

  /* Estado cuando está retraído (pantalla completa para la app) */
  @media (min-width: 992px) {
    html.sidebar-collapsed .sidebar,
    body.sidebar-collapsed .sidebar {
      transform: translateX(-100%) !important;
      box-shadow: none !important;
      pointer-events: none !important;
    }

    html.sidebar-collapsed .main-wrapper,
    body.sidebar-collapsed .main-wrapper {
      margin-left: 0 !important;
      width: 100% !important;
      max-width: 100% !important;
    }

    /* Mostrar botón flotante solo al estar retraído */
    html.sidebar-collapsed .sidebar-floating-toggle,
    body.sidebar-collapsed .sidebar-floating-toggle {
      display: inline-flex !important;
      opacity: 1 !important;
      transform: translateX(0) !important;
      pointer-events: auto !important;
    }
  }

  /* Estilos del botón inferior dentro del sidebar */
  .sidebar-collapse-wrapper {
    padding: 10px 14px 4px !important;
    border-top: 1px solid rgba(255, 255, 255, 0.08) !important;
    flex-shrink: 0 !important;
  }
  .btn-sidebar-collapse {
    width: 100% !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 10px !important;
    padding: 9px 14px !important;
    background: rgba(255, 255, 255, 0.06) !important;
    border: 1px solid rgba(255, 255, 255, 0.12) !important;
    border-radius: 8px !important;
    color: rgba(255, 255, 255, 0.8) !important;
    font-size: 13.5px !important;
    font-weight: 500 !important;
    cursor: pointer !important;
    transition: all 0.2s ease !important;
    outline: none !important;
  }
  .btn-sidebar-collapse:hover {
    background: rgba(255, 255, 255, 0.14) !important;
    color: #ffffff !important;
    border-color: rgba(212, 175, 55, 0.5) !important;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.3) !important;
  }
  .btn-sidebar-collapse i {
    font-size: 16px !important;
    color: #d4af37 !important;
    transition: transform 0.2s ease !important;
  }
  .btn-sidebar-collapse:hover i {
    transform: translateX(-3px) !important;
  }

  /* Estilos del botón flotante inferior izquierdo */
  .sidebar-floating-toggle {
    position: fixed !important;
    bottom: 22px !important;
    left: 20px !important;
    z-index: 1040 !important;
    display: none;
    align-items: center !important;
    gap: 8px !important;
    padding: 9px 18px !important;
    background: #142414 !important;
    border: 1.5px solid rgba(212, 175, 55, 0.6) !important;
    border-radius: 99px !important;
    color: #ffffff !important;
    font-size: 13px !important;
    font-weight: 600 !important;
    box-shadow: 0 6px 24px rgba(0, 0, 0, 0.45), 0 0 16px rgba(212, 175, 55, 0.25) !important;
    cursor: pointer !important;
    text-decoration: none !important;
    transition: all 0.2s ease !important;
  }
  .sidebar-floating-toggle:hover {
    background: #1e361e !important;
    border-color: #d4af37 !important;
    color: #ffffff !important;
    transform: translateY(-2px) !important;
    box-shadow: 0 8px 28px rgba(0, 0, 0, 0.55), 0 0 20px rgba(212, 175, 55, 0.4) !important;
  }
  .sidebar-floating-toggle i {
    font-size: 16px !important;
    color: #d4af37 !important;
  }
</style>

<script>
// Función global de alternar / retraer el sidebar
window.toggleSidebarRetract = function(forceCollapse) {
  var html = document.documentElement;
  var body = document.body;
  var isCurrentlyCollapsed = html.classList.contains('sidebar-collapsed') || body.classList.contains('sidebar-collapsed');
  var shouldCollapse = (typeof forceCollapse === 'boolean') ? forceCollapse : !isCurrentlyCollapsed;

  if (shouldCollapse) {
    html.classList.add('sidebar-collapsed');
    body.classList.add('sidebar-collapsed');
    try { localStorage.setItem('sidebar_collapsed', 'true'); } catch(e) {}
  } else {
    html.classList.remove('sidebar-collapsed');
    body.classList.remove('sidebar-collapsed');
    try { localStorage.setItem('sidebar_collapsed', 'false'); } catch(e) {}
  }

  // Notificar a componentes, tablas y charts del cambio de ancho
  setTimeout(function() { window.dispatchEvent(new Event('resize')); }, 100);
  setTimeout(function() { window.dispatchEvent(new Event('resize')); }, 300);
};

// Sincronizar clase en body si ya fue seteada en head
(function() {
  if (document.documentElement.classList.contains('sidebar-collapsed') && !document.body.classList.contains('sidebar-collapsed')) {
    document.body.classList.add('sidebar-collapsed');
  }
})();
</script>