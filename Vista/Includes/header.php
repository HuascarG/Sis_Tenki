<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// Cargar env y auth si no están cargados aún
if (!function_exists('isLoggedIn')) {
    require_once ROOT . '/Config/env.php';
    require_once ROOT . '/Config/auth.php';
}
requireLogin();

// ── Cabeceras de seguridad HTTP ──
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');


$flash = getFlash();
$currentPath = $_SERVER['PHP_SELF'];

// Determine active module from URL path
$pathParts = explode('/', trim($currentPath, '/'));
$currentModule = 'dashboard';
foreach ($pathParts as $part) {
    if (in_array($part, ['inventario','ventas','facturacion','compras','creditos','clientes','proveedores','usuarios','empresa','reportes','backups'])) {
        $currentModule = $part;
        break;
    }
}
$me = currentUser();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="TENKI - Sistema de Gestión de Repuestos Automotrices">
  <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' | ' : '' ?>TENKI</title>
  <!-- Favicon -->
  <link rel="icon" type="image/png" href="<?= BASE_URL ?>Assets/img/icono.png">
  <link rel="shortcut icon" type="image/png" href="<?= BASE_URL ?>Assets/img/icono.png">

  <!-- Bootstrap 5 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <!-- SweetAlert2 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
  <!-- Custom Styles -->
  <link rel="stylesheet" href="<?= BASE_URL ?>Assets/Css/style.css?v=<?= file_exists(ROOT . '/Assets/Css/style.css') ? filemtime(ROOT . '/Assets/Css/style.css') : '1.1' ?>">

  <!-- CSRF Token Meta -->
  <meta name="csrf-token" content="<?= csrfToken() ?>">
  <!-- Pre-aplicar estado retraído del sidebar para evitar parpadeos -->
  <script>
    if (localStorage.getItem('sidebar_collapsed') === 'true') {
      document.documentElement.classList.add('sidebar-collapsed');
    }
  </script>
</head>
<body>

<!-- ===== OFFCANVAS SIDEBAR (mobile) ===== -->
<?php include ROOT . '/Vista/Includes/sidebar.php'; ?>

<!-- ===== MAIN WRAPPER ===== -->
<div class="main-wrapper">

  <!-- TOPBAR -->
  <header class="topbar">
    <div class="d-flex align-items-center gap-3">
      <!-- Desktop sidebar toggle -->
      <button class="btn btn-icon d-none d-lg-inline-flex sidebar-toggle-btn" id="sidebarToggleBtn" onclick="toggleSidebarRetract()" type="button"
              title="Mostrar / ocultar menú lateral (Ctrl+B)" aria-label="Alternar menú lateral">
        <i class="bi bi-layout-sidebar-inset fs-5 icon-expanded"></i>
        <i class="bi bi-layout-sidebar fs-5 icon-collapsed"></i>
      </button>

      <!-- Mobile toggle -->
      <button class="btn btn-icon d-lg-none" type="button"
              data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas">
        <i class="bi bi-list fs-5"></i>
      </button>
      <div>
        <div class="topbar-title"><?= isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Dashboard' ?></div>
        <div class="topbar-breadcrumb d-none d-sm-block">
          TENKI &rsaquo; <?= isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Inicio' ?>
        </div>
      </div>
    </div>
    <div class="d-flex align-items-center gap-2">
      <span class="badge badge-gold fw-semi d-none d-sm-inline">TENKI v1.0</span>
      <?php if ($me): ?>
      <span class="d-none d-md-inline badge <?= isAdmin() ? 'badge-gold' : 'badge-info-c' ?>">
        <?= isAdmin() ? '🔑' : '👤' ?> <?= htmlspecialchars($me['nombre']) ?>
      </span>
      <?php endif; ?>
      <a href="<?= BASE_URL ?>" class="btn btn-icon" title="Inicio">
        <i class="bi bi-house"></i>
      </a>
      <a href="<?= BASE_URL ?>Vista/auth/logout.php" class="btn btn-icon" title="Cerrar sesión">
        <i class="bi bi-box-arrow-right"></i>
      </a>
    </div>
  </header>

  <!-- FLASH MESSAGES (SweetAlert2) -->
  <?php if ($flash): ?>
  <script>
  document.addEventListener('DOMContentLoaded', () => {
    const swalType = <?= json_encode($flash['type']) ?>;
    const swalMsg  = <?= json_encode($flash['message']) ?>;
    const iconMap  = { success: 'success', danger: 'error', warning: 'warning', info: 'info' };
    Swal.fire({
      icon: iconMap[swalType] || 'info',
      title: swalType === 'success' ? '¡Éxito!' : (swalType === 'danger' ? 'Error' : 'Aviso'),
      text: swalMsg,
      confirmButtonColor: '#f97316',
      timer: 4000,
      timerProgressBar: true,
      showConfirmButton: true
    });
  });
  </script>
  <?php endif; ?>

  <!-- PAGE CONTENT -->
  <main class="page-content">
    <div class="container-fluid">
