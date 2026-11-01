<?php
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/Config/database.php';
require_once ROOT . '/Config/config.php';
require_once ROOT . '/Config/env.php';
require_once ROOT . '/Config/auth.php';
require_once ROOT . '/Controlador/AuthController.php';

if (isLoggedIn()) { redirect(''); }

$ctrl    = new AuthController();
$sent    = false;
$errors  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo = trim($_POST['correo'] ?? '');
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Ingresa un correo electrónico válido.';
    } else {
        $ctrl->solicitarRecuperacion($correo);
        $sent = true; // Respuesta genérica siempre
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Recuperar Contraseña | TENKI</title>
  <link rel="icon" type="image/png" href="<?= BASE_URL ?>Assets/img/favicon.png">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>Assets/Css/style.css">
  <style>
    body { min-height:100vh; display:flex; align-items:center; justify-content:center;
           background: radial-gradient(ellipse at 60% 0%, #1e3a5f 0%, #0f172a 70%); }
    .login-card { width:100%; max-width:420px; background:var(--bg-card);
                  border:1px solid var(--border); border-radius:16px;
                  padding:2.5rem 2rem; box-shadow:0 25px 60px rgba(0,0,0,.5); }
    .brand-logo { font-size:2rem; font-weight:800; color:var(--accent); letter-spacing:2px; }
  </style>
</head>
<body>
<div class="login-card">
  <div class="text-center mb-4">
    <div class="brand-logo">TENKI</div>
    <p class="text-muted small mt-1">Recuperación de contraseña</p>
  </div>

  <?php if ($sent): ?>
    <div class="alert alert-success text-center">
      <i class="bi bi-envelope-check fs-3 d-block mb-2"></i>
      <strong>Correo enviado</strong><br>
      Si tu correo está registrado, recibirás un enlace para restablecer tu contraseña.<br>
      <small class="text-muted">Revisa también tu carpeta de spam.</small>
    </div>
    <a href="<?= BASE_URL ?>Vista/auth/login.php" class="btn btn-secondary w-100 mt-2">
      <i class="bi bi-arrow-left me-1"></i>Volver al inicio de sesión
    </a>
  <?php else: ?>
    <?php foreach ($errors as $e): ?>
      <div class="alert alert-danger py-2 small"><i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>

    <p class="text-muted small mb-3">
      Ingresa el correo electrónico asociado a tu cuenta y te enviaremos un enlace para restablecer tu contraseña.
    </p>
    <form method="POST">
      <div class="mb-3">
        <label class="form-label">Correo electrónico</label>
        <div class="input-group">
          <span class="input-group-text" style="background:var(--bg-sidebar);border-color:var(--border);color:var(--text-muted-c)">
            <i class="bi bi-envelope"></i>
          </span>
          <input type="email" name="correo" class="form-control"
                 value="<?= htmlspecialchars($_POST['correo'] ?? '') ?>"
                 placeholder="tucorreo@ejemplo.com" required>
        </div>
      </div>
      <button type="submit" class="btn w-100 text-white mb-3"
              style="background:var(--accent);border:none;font-weight:600;padding:.75rem">
        <i class="bi bi-send me-1"></i>Enviar enlace
      </button>
    </form>
    <a href="<?= BASE_URL ?>Vista/auth/login.php" class="d-block text-center small text-muted">
      <i class="bi bi-arrow-left me-1"></i>Volver al inicio de sesión
    </a>
  <?php endif; ?>
</div>
</body>
</html>
