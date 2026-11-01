<?php
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/Config/database.php';
require_once ROOT . '/Config/config.php';
require_once ROOT . '/Config/auth.php';
require_once ROOT . '/Controlador/UsuarioController.php';
require_once ROOT . '/Controlador/AuthController.php';

requireAdmin();
$ctrl   = new UsuarioController();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'nombre'   => trim($_POST['nombre']   ?? ''),
        'apellido' => trim($_POST['apellido'] ?? ''),
        'cedula'   => trim($_POST['cedula']   ?? ''),
        'telefono' => trim($_POST['telefono'] ?? ''),
        'correo'   => trim($_POST['correo']   ?? ''),
        'password' => $_POST['password']  ?? '',
        'password2'=> $_POST['password2'] ?? '',
        'rol'      => $_POST['rol']        ?? 'usuario',
    ];

    if (empty($data['nombre']) || strlen($data['nombre']) > 100 || !preg_match('/^[a-zA-ZÀ-ÿ\s]{2,100}$/', $data['nombre'])) {
        $errors[] = 'El nombre es inválido o excede los 100 caracteres.';
    }
    if (empty($data['apellido']) || strlen($data['apellido']) > 100 || !preg_match('/^[a-zA-ZÀ-ÿ\s]{2,100}$/', $data['apellido'])) {
        $errors[] = 'El apellido es inválido o excede los 100 caracteres.';
    }
    if (empty($data['cedula']) || !preg_match('/^[0-9]{6,8}$/', $data['cedula'])) {
        $errors[] = 'La cédula debe contener solo números (entre 6 y 8 dígitos).';
    }
    if (!empty($data['telefono']) && !preg_match('/^[0-9\-\+\s]{7,20}$/', $data['telefono'])) {
        $errors[] = 'El formato del teléfono es inválido.';
    }
    if (!filter_var($data['correo'], FILTER_VALIDATE_EMAIL) || strlen($data['correo']) > 150) {
        $errors[] = 'El correo es inválido o demasiado largo.';
    }
    // La validez criptográfica de la contraseña ya la hace el Controlador, aquí solo chequeamos que coincidan
    if ($data['password'] !== $data['password2']) {
        $errors[] = 'Las contraseñas no coinciden.';
    }

    if (empty($errors)) {
        $result = $ctrl->crear($data);
        if ($result === true) {
            setFlash('success', 'Usuario creado correctamente.');
            redirect('Vista/usuarios/index.php');
        } else {
            $errors[] = is_string($result) ? $result : 'Error al guardar el usuario.';
        }
    }
}

$pageTitle = 'Nuevo Usuario';
include ROOT . '/Vista/Includes/header.php';
?>

<div class="row justify-content-center">
<div class="col-12 col-xl-8">
<div class="card">
  <div class="card-header">
    <span class="card-title"><i class="bi bi-person-plus me-2"></i>Nuevo Usuario</span>
    <a href="<?= BASE_URL ?>Vista/usuarios/index.php" class="btn btn-secondary btn-sm">
      <i class="bi bi-arrow-left me-1"></i>Volver
    </a>
  </div>
  <div class="card-body">
    <?php foreach ($errors as $e): ?>
      <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-2"></i><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>

    <form method="POST" id="userForm">
      <div class="row g-3">
        <div class="col-12 col-md-6">
          <label class="form-label">Nombre *</label>
          <input type="text" name="nombre" class="form-control" required
                 pattern="[a-zA-ZÀ-ÿ\s]{2,100}" maxlength="100" title="Solo letras y espacios (2 a 100 caracteres)"
                 value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>">
        </div>
        <div class="col-12 col-md-6">
          <label class="form-label">Apellido *</label>
          <input type="text" name="apellido" class="form-control" required
                 pattern="[a-zA-ZÀ-ÿ\s]{2,100}" maxlength="100" title="Solo letras y espacios (2 a 100 caracteres)"
                 value="<?= htmlspecialchars($_POST['apellido'] ?? '') ?>">
        </div>
        <div class="col-12 col-md-4">
          <label class="form-label">Cédula *</label>
          <input type="text" name="cedula" class="form-control" required
                 pattern="[0-9]{6,8}" maxlength="8" title="Solo números (entre 6 y 8 dígitos)"
                 placeholder="Ej: 12345678"
                 value="<?= htmlspecialchars($_POST['cedula'] ?? '') ?>">
        </div>
        <div class="col-12 col-md-4">
          <label class="form-label">Teléfono</label>
          <input type="text" name="telefono" class="form-control"
                 pattern="[0-9\-\+\s]{7,20}" maxlength="20" title="Números, guiones o signos de suma válidos"
                 placeholder="Ej: +58 414 1234567"
                 value="<?= htmlspecialchars($_POST['telefono'] ?? '') ?>">
        </div>
        <div class="col-12 col-md-4">
          <label class="form-label">Rol</label>
          <select name="rol" class="form-select">
            <option value="usuario" <?= ($_POST['rol'] ?? 'usuario') === 'usuario' ? 'selected' : '' ?>>👤 Usuario</option>
            <option value="admin"   <?= ($_POST['rol'] ?? '') === 'admin'   ? 'selected' : '' ?>>🔑 Administrador</option>
          </select>
        </div>
        <div class="col-12">
          <label class="form-label">Correo electrónico *</label>
          <input type="email" name="correo" class="form-control" required
                 value="<?= htmlspecialchars($_POST['correo'] ?? '') ?>">
        </div>
        <!-- Contraseña con indicador -->
        <div class="col-12 col-md-6">
          <label class="form-label">Contraseña *</label>
          <input type="password" name="password" id="pwd" class="form-control"
                 oninput="checkReqs(this.value)" autocomplete="new-password"
                 placeholder="••••••••">
        </div>
        <div class="col-12 col-md-6">
          <label class="form-label">Confirmar contraseña *</label>
          <input type="password" name="password2" id="pwd2" class="form-control"
                 autocomplete="new-password" placeholder="••••••••">
        </div>
        <!-- Indicador de requisitos -->
        <div class="col-12">
          <div class="p-3 rounded" style="background:var(--bg-sidebar)">
            <div class="row g-1">
              <div class="col-12 col-sm-6"><div class="req" id="r-len"><i class="bi bi-circle"></i> Mínimo 8 caracteres</div></div>
              <div class="col-12 col-sm-6"><div class="req" id="r-up" ><i class="bi bi-circle"></i> Al menos 1 mayúscula</div></div>
              <div class="col-12 col-sm-6"><div class="req" id="r-lo" ><i class="bi bi-circle"></i> Al menos 1 minúscula</div></div>
              <div class="col-12 col-sm-6"><div class="req" id="r-sp" ><i class="bi bi-circle"></i> Al menos 1 carácter especial</div></div>
            </div>
          </div>
        </div>
        <div class="col-12">
          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Guardar Usuario</button>
            <a href="<?= BASE_URL ?>Vista/usuarios/index.php" class="btn btn-secondary">Cancelar</a>
          </div>
        </div>
      </div>
    </form>
  </div>
</div>
</div>
</div>

<style>
.req { display:flex; align-items:center; gap:.4rem; font-size:.8rem; color:var(--text-muted-c); padding:.2rem 0; transition:color .2s; }
.req.ok { color:#22c55e; }
</style>
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

// Validación Frontend antes de hacer submit
document.getElementById('userForm').addEventListener('submit', function(e) {
  const pwd = document.getElementById('pwd').value;
  const pwd2 = document.getElementById('pwd2').value;
  
  if (pwd !== pwd2) {
    e.preventDefault();
    Swal.fire({ icon: 'error', title: 'Contraseñas no coinciden', text: 'Las contraseñas ingresadas no son iguales. Verifica e intenta de nuevo.', confirmButtonColor: '#f97316' });
    return;
  }
  
  const isValid = pwd.length >= 8 && /[A-Z]/.test(pwd) && /[a-z]/.test(pwd) && /[^a-zA-Z0-9]/.test(pwd);
  if (!isValid) {
    e.preventDefault();
    Swal.fire({ icon: 'warning', title: 'Contraseña insegura', text: 'La contraseña no cumple con todos los requisitos de seguridad indicados en verde.', confirmButtonColor: '#f97316' });
  }
});
</script>

<?php include ROOT . '/Vista/Includes/footer.php'; ?>
