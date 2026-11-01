<?php
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/Config/database.php';
require_once ROOT . '/Config/config.php';
require_once ROOT . '/Config/auth.php';
require_once ROOT . '/Controlador/AuthController.php';

if (isLoggedIn()) { redirect(''); }

$ctrl   = new AuthController();
$token  = trim($_GET['token'] ?? '');
$user   = $token ? $ctrl->validarToken($token) : null;
$done   = false;
$errors = [];

if (!$user && $token) {
    $errors[] = 'El enlace es inválido o ha expirado. Solicita uno nuevo.';
}

if ($user && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $pass1 = $_POST['password']  ?? '';
    $pass2 = $_POST['password2'] ?? '';

    if (!AuthController::validarPassword($pass1)) {
        $errors[] = 'La contraseña no cumple los requisitos mínimos.';
    } elseif ($pass1 !== $pass2) {
        $errors[] = 'Las contraseñas no coinciden.';
    } else {
        $ctrl->cambiarPassword($user['id'], $pass1);
        $done = true;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Nueva Contraseña | TENKI</title>
  <link rel="icon" type="image/png" href="<?= BASE_URL ?>Assets/img/favicon.png">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>Assets/Css/style.css">
  <style>
    body { min-height:100vh; display:flex; align-items:center; justify-content:center;
           background:radial-gradient(ellipse at 60% 0%, #1e3a5f 0%, #0f172a 70%); }
    .login-card { width:100%; max-width:440px; background:var(--bg-card);
                  border:1px solid var(--border); border-radius:16px;
                  padding:2.5rem 2rem; box-shadow:0 25px 60px rgba(0,0,0,.5); }
    .brand-logo { font-size:2rem; font-weight:800; color:var(--accent); letter-spacing:2px; }
    .req { display:flex; align-items:center; gap:.5rem; font-size:.82rem; color:var(--text-muted-c);
           transition:color .2s; }
    .req.ok { color:#22c55e; }
    .req i   { font-size:1rem; }
  </style>
</head>
<body>
<div class="login-card">
  <div class="text-center mb-4">
    <div class="brand-logo">TENKI</div>
    <p class="text-muted small mt-1">Establecer nueva contraseña</p>
  </div>

  <?php if ($done): ?>
    <div class="alert alert-success text-center">
      <i class="bi bi-check-circle fs-3 d-block mb-2"></i>
      <strong>Contraseña actualizada</strong><br>
      Ya puedes iniciar sesión con tu nueva contraseña.
    </div>
    <a href="<?= BASE_URL ?>Vista/auth/login.php" class="btn w-100 text-white mt-2"
       style="background:var(--accent);border:none">
      <i class="bi bi-box-arrow-in-right me-1"></i>Ir al Login
    </a>
  <?php elseif (!$user): ?>
    <?php foreach ($errors as $e): ?>
      <div class="alert alert-danger py-2 small"><i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>
    <a href="<?= BASE_URL ?>Vista/auth/recuperar.php" class="btn btn-secondary w-100">
      <i class="bi bi-arrow-left me-1"></i>Solicitar nuevo enlace
    </a>
  <?php else: ?>
    <?php foreach ($errors as $e): ?>
      <div class="alert alert-danger py-2 small"><i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>

    <form method="POST">
      <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
      <div class="mb-3">
        <label class="form-label">Nueva contraseña</label>
        <input type="password" name="password" id="pwd" class="form-control"
               placeholder="••••••••" oninput="checkReqs(this.value)" autocomplete="new-password">
      </div>
      <!-- Indicador de requisitos en tiempo real -->
      <div class="mb-3 p-3 rounded" style="background:var(--bg-sidebar)">
        <div class="req" id="r-len"><i class="bi bi-circle"></i> Mínimo 8 caracteres</div>
        <div class="req" id="r-up" ><i class="bi bi-circle"></i> Al menos 1 mayúscula</div>
        <div class="req" id="r-lo" ><i class="bi bi-circle"></i> Al menos 1 minúscula</div>
        <div class="req" id="r-sp" ><i class="bi bi-circle"></i> Al menos 1 carácter especial (!@#$...)</div>
      </div>
      <div class="mb-4">
        <label class="form-label">Confirmar contraseña</label>
        <input type="password" name="password2" id="pwd2" class="form-control"
               placeholder="••••••••" autocomplete="new-password">
      </div>
      <button type="submit" id="btnSave" class="btn w-100 text-white"
              style="background:var(--accent);border:none;font-weight:600;padding:.75rem">
        <i class="bi bi-shield-check me-1"></i>Guardar contraseña
      </button>
    </form>
  <?php endif; ?>
</div>
<script>
function checkReqs(val) {
  const set = (id, ok) => {
    const el = document.getElementById(id);
    el.classList.toggle('ok', ok);
    el.querySelector('i').className = ok ? 'bi bi-check-circle-fill' : 'bi bi-circle';
  };
  set('r-len', val.length >= 8);
  set('r-up',  /[A-Z]/.test(val));
  set('r-lo',  /[a-z]/.test(val));
  set('r-sp',  /[^a-zA-Z0-9]/.test(val));
}
</script>
</body>
</html>
