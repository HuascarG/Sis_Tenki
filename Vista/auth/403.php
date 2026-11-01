<?php
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/Config/database.php';
require_once ROOT . '/Config/config.php';
require_once ROOT . '/Config/auth.php';
$pageTitle = 'Acceso Denegado';
include ROOT . '/Vista/Includes/header.php';
?>
<div class="d-flex flex-column align-items-center justify-content-center py-5 text-center" style="min-height:60vh">
  <i class="bi bi-shield-lock" style="font-size:4rem;color:var(--accent)"></i>
  <h1 class="mt-3 fw-bold">Acceso Denegado</h1>
  <p class="text-muted mb-4">No tienes permisos para realizar esta acción.<br>
     Si crees que esto es un error, contacta al administrador.</p>
  <a href="<?= BASE_URL ?>" class="btn btn-primary">
    <i class="bi bi-house me-1"></i>Volver al Dashboard
  </a>
</div>
<?php include ROOT . '/Vista/Includes/footer.php'; ?>
