<?php
// Vista/auth/login.php — Standalone, sin header/sidebar
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/Config/database.php';
require_once ROOT . '/Config/config.php';
require_once ROOT . '/Config/env.php';
require_once ROOT . '/Config/auth.php';
require_once ROOT . '/Controlador/AuthController.php';

// Si ya hay sesión, redirigir al dashboard
if (isLoggedIn()) { redirect(''); }

$ctrl   = new AuthController();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar throttle anti fuerza bruta
    if (!checkLoginThrottle()) {
        $remaining = 60 - (time() - ($_SESSION['login_last_attempt'] ?? 0));
        $errors[] = "Demasiados intentos fallidos. Espera {$remaining} segundos antes de intentar de nuevo.";
    } else {
        $cedula = trim($_POST['cedula'] ?? '');
        $pass   = $_POST['password'] ?? '';

        if (empty($cedula) || empty($pass)) {
            $errors[] = 'Ingresa tu cédula y contraseña.';
        } elseif (!$ctrl->login($cedula, $pass)) {
            registerFailedLogin();
            $errors[] = 'Cédula o contraseña incorrectos.';
        } else {
            resetLoginAttempts();
            $dest = $_SESSION['redirect_after_login'] ?? '';
            unset($_SESSION['redirect_after_login']);
            header('Location: ' . (empty($dest) ? BASE_URL : $dest));
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="<?= BASE_URL ?>Assets/img/LOGO_TENKI.png">
  <link rel="shortcut icon" type="image/png" href="<?= BASE_URL ?>Assets/img/LOGO_TENKI.png">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>Assets/Css/style.css">
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }

    body { 
        min-height: 100vh; 
        display: flex; 
        align-items: center; 
        justify-content: center;
        background: url('../../Assets/img/fachada-tenki.jpg') center/cover no-repeat fixed #1e293b !important;
        font-family: 'Nunito', sans-serif;
        position: relative;
        overflow: hidden;
    }
    
    /* Overlay difuminado */
    body::before {
        content: '';
        position: absolute;
        inset: 0;
        background: rgba(15, 23, 42, 0.18);
        backdrop-filter: blur(2px);
        -webkit-backdrop-filter: blur(2px);
        z-index: 0;
    }

    /* ═══ Partículas animadas flotantes ═══ */
    .particles {
        position: fixed; inset: 0; z-index: 0; pointer-events: none; overflow: hidden;
    }
    .particle {
        position: absolute;
        border-radius: 50%;
        opacity: 0;
        animation: particleFloat linear infinite;
    }
    @keyframes particleFloat {
        0%   { transform: translateY(100vh) scale(0); opacity: 0; }
        10%  { opacity: 1; }
        90%  { opacity: 0.6; }
        100% { transform: translateY(-10vh) scale(1); opacity: 0; }
    }

    /* ═══ Tarjeta principal ═══ */
    .login-wrapper { 
        position: relative; z-index: 1; 
        width: 100%; max-width: 900px; padding: 1rem; 
        animation: slideUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        opacity: 0;
    }
    @keyframes slideUp {
        from { opacity: 0; transform: translateY(40px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    
    .login-card { 
        display: flex;
        background: #ffffff;
        border-radius: 28px;
        box-shadow: 0 30px 80px rgba(0,0,0,0.35), 0 0 0 1px rgba(255,255,255,0.1);
        overflow: hidden;
        min-height: 540px;
        width: 100%;
        transition: transform 0.5s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.5s ease;
    }
    .login-card:hover {
        transform: translateY(-6px);
        box-shadow: 0 40px 100px rgba(0,0,0,0.4), 0 0 0 1px rgba(255,255,255,0.15);
    }

    /* ═══ Lado izquierdo: Logo con gradiente animado ═══ */
    .login-left {
        width: 45%;
        background: linear-gradient(135deg, #fef08a 0%, #d4af37 30%, #ca8a04 60%, #a16207 100%);
        background-size: 300% 300%;
        animation: gradientShift 8s ease infinite;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 3rem;
        position: relative;
        overflow: hidden;
    }
    @keyframes gradientShift {
        0%, 100% { background-position: 0% 50%; }
        50%      { background-position: 100% 50%; }
    }

    /* Burbujas decorativas del lado izquierdo */
    .login-left::before,
    .login-left::after {
        content: '';
        position: absolute;
        border-radius: 50%;
        background: rgba(255,255,255,0.12);
        pointer-events: none;
    }
    .login-left::before {
        width: 200px; height: 200px;
        top: -50px; left: -50px;
        animation: bubbleFloat 6s ease-in-out infinite;
    }
    .login-left::after {
        width: 150px; height: 150px;
        bottom: -30px; right: -30px;
        animation: bubbleFloat 8s ease-in-out infinite reverse;
    }
    @keyframes bubbleFloat {
        0%, 100% { transform: translate(0, 0) scale(1); }
        50%      { transform: translate(20px, -20px) scale(1.1); }
    }

    /* ═══ Logo con contorno adaptado a su forma ═══ */
    .logo-container {
        width: 100%;
        display: flex;
        justify-content: center;
        align-items: center;
        position: relative;
        z-index: 2;
    }
    .logo-frame {
        background: #ffffff;
        padding: 12px;
        border-radius: 20px;  /* Respeta la forma del logo, no lo fuerza a circular */
        box-shadow: 
            0 15px 40px rgba(0,0,0,0.15),
            0 0 0 3px rgba(255,255,255,0.6),
            inset 0 0 15px rgba(0,0,0,0.03);
        animation: logoPulse 4s ease-in-out infinite;
        transition: transform 0.4s ease;
    }
    .logo-frame:hover {
        transform: scale(1.05) rotate(2deg);
    }
    @keyframes logoPulse {
        0%, 100% { box-shadow: 0 15px 40px rgba(0,0,0,0.15), 0 0 0 3px rgba(255,255,255,0.6), inset 0 0 15px rgba(0,0,0,0.03); }
        50%      { box-shadow: 0 20px 50px rgba(0,0,0,0.2), 0 0 0 6px rgba(255,255,255,0.4), inset 0 0 15px rgba(0,0,0,0.03); }
    }
    .logo-frame img {
        display: block;
        max-height: 250px;
        max-width: 100%;
        object-fit: contain;
        border-radius: 12px;
    }

    /* ═══ Lado derecho: Formulario ═══ */
    .login-right {
        width: 55%;
        padding: 3.5rem 4rem;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        text-align: center;
        position: relative;
    }

    /* Patrón decorativo en el panel derecho */
    .login-right::before {
        content: '';
        position: absolute;
        top: -60px; right: -60px;
        width: 180px; height: 180px;
        background: radial-gradient(circle, rgba(77,124,15,0.06) 0%, transparent 70%);
        border-radius: 50%;
        pointer-events: none;
    }

    .brand-title {
        font-family: 'Montserrat', sans-serif;
        font-size: 2.1rem;
        font-weight: 800;
        color: #1e293b;
        letter-spacing: 1px;
        margin-bottom: 0.4rem;
        text-transform: uppercase;
        animation: fadeInDown 0.9s 0.3s both;
    }
    @keyframes fadeInDown {
        from { opacity: 0; transform: translateY(-15px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    .subtitle {
        color: #64748b;
        font-size: 1rem;
        font-weight: 600;
        margin-bottom: 2rem;
        animation: fadeInDown 0.9s 0.45s both;
    }

    .login-form {
        width: 100%;
        max-width: 320px;
        animation: fadeInDown 0.9s 0.6s both;
    }

    /* ═══ Inputs estilo píldora ═══ */
    .input-icon-wrapper {
        position: relative;
        margin-bottom: 1.25rem;
    }

    .input-icon-wrapper i.form-icon {
        position: absolute;
        left: 1.25rem;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: 1.15rem;
        pointer-events: none;
        z-index: 2;
        transition: color 0.3s;
    }

    .input-icon-wrapper:focus-within i.form-icon {
        color: var(--accent);
    }

    .form-control-custom {
        background-color: #f1f5f9;
        border: 2px solid transparent;
        border-radius: 50px;
        padding: 0.85rem 1.5rem 0.85rem 3.2rem;
        font-size: 0.95rem;
        font-family: 'Nunito', sans-serif;
        color: #334155;
        width: 100%;
        font-weight: 600;
        transition: all 0.3s;
    }
    .form-control-custom:focus {
        outline: none;
        background-color: #ffffff;
        border-color: var(--accent);
        box-shadow: 0 0 0 4px rgba(77, 124, 15, 0.15);
    }
    .form-control-custom::placeholder {
        color: #94a3b8;
        font-weight: 600;
    }

    /* ═══ Botón con efecto shimmer ═══ */
    .btn-login { 
        background: linear-gradient(135deg, var(--accent) 0%, var(--accent-dark) 100%);
        color: white;
        border: none; 
        font-weight: 800;
        font-family: 'Montserrat', sans-serif;
        padding: 0.9rem; 
        font-size: 1.05rem; 
        border-radius: 50px;
        margin-top: 0.8rem;
        width: 100%;
        transition: all 0.3s ease; 
        letter-spacing: 0.5px;
        position: relative;
        overflow: hidden;
    }
    .btn-login::after {
        content: '';
        position: absolute;
        top: 0; left: -100%;
        width: 100%; height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.25), transparent);
        animation: shimmer 3s infinite;
    }
    @keyframes shimmer {
        0%   { left: -100%; }
        100% { left: 100%; }
    }
    .btn-login:hover { 
        background: linear-gradient(135deg, var(--accent-dark) 0%, var(--accent) 100%);
        color: white;
        transform: translateY(-3px); 
        box-shadow: 0 10px 25px rgba(77, 124, 15, 0.35); 
    }
    .btn-login:active {
        transform: translateY(-1px);
        box-shadow: 0 5px 15px rgba(77, 124, 15, 0.25);
    }

    /* ═══ Links inferiores ═══ */
    .bottom-links {
        margin-top: 1.8rem;
        display: flex;
        flex-direction: column;
        gap: 0.4rem;
        animation: fadeInDown 0.9s 0.75s both;
    }
    .bottom-links a {
        color: #64748b;
        text-decoration: none;
        font-size: 0.9rem;
        transition: color 0.3s, transform 0.2s;
        font-weight: 600;
    }
    .bottom-links a:hover {
        color: var(--accent);
        transform: translateX(2px);
    }
    .bottom-links .help-link {
        font-weight: 800;
        color: #334155;
        font-size: 0.95rem;
        margin-top: 0.2rem;
    }
    .bottom-links .help-link:hover {
        color: var(--accent);
    }

    /* ═══ Alertas ═══ */
    .alert-custom {
        background: #fee2e2;
        border-left: 4px solid #ef4444;
        color: #991b1b;
        border-radius: 8px;
        padding: 0.6rem 1rem;
        font-size: 0.85rem;
        font-weight: 700;
        margin-bottom: 1.5rem;
        text-align: left;
        animation: shake 0.5s ease-in-out;
    }
    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        20%      { transform: translateX(-8px); }
        40%      { transform: translateX(8px); }
        60%      { transform: translateX(-4px); }
        80%      { transform: translateX(4px); }
    }

    /* ═══ Responsive ═══ */
    @media (max-width: 860px) {
        .login-right { padding: 3rem 2rem; }
    }
    @media (max-width: 768px) {
        .login-card { flex-direction: column; min-height: auto; }
        .login-left { 
            width: 100%; padding: 3rem 2rem; 
        }
        .login-right { 
            width: 100%; padding: 3rem 1.5rem; 
        }
        .logo-frame img { max-height: 180px; }
    }
  </style>
</head>
<body>

<!-- Partículas flotantes de fondo -->
<div class="particles" id="particles"></div>

<div class="login-wrapper">
    <div class="login-card">
        
        <!-- MITAD IZQUIERDA (Logo con gradiente animado) -->
        <div class="login-left">
            <div class="logo-container">
                <div class="logo-frame">
                    <img src="<?= BASE_URL ?>Assets/img/LOGO_TENKI.png" alt="Logo corporativo">
                </div>
            </div>
        </div>

        <!-- MITAD DERECHA (Formulario) -->
        <div class="login-right">
            <h1 class="brand-title">¡BIENVENIDO!</h1>
            <p class="subtitle">Iniciar Sesión Para Continuar</p>

            <div class="login-form">
                <?php foreach ($errors as $e): ?>
                    <div class="alert-custom"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($e) ?></div>
                <?php endforeach; ?>

                <form method="POST" novalidate>
                    
                    <!-- Input: Cédula de Identidad -->
                    <div class="input-icon-wrapper">
                        <i class="bi bi-person-vcard form-icon"></i>
                        <input type="text" name="cedula" id="cedula" class="form-control-custom"
                               placeholder="Cédula de Identidad" value="<?= htmlspecialchars($_POST['cedula'] ?? '') ?>"
                               autocomplete="off" required>
                    </div>
                    
                    <!-- Input: Contraseña -->
                    <div class="input-icon-wrapper">
                        <i class="bi bi-lock-fill form-icon"></i>
                        <input type="password" name="password" id="password" class="form-control-custom"
                               placeholder="Contraseña" autocomplete="off" required
                               style="padding-right: 3rem;">
                        <button type="button" tabindex="-1" 
                                style="position: absolute; right: 1.2rem; top: 50%; transform: translateY(-50%); background: none; border: none; color: #94a3b8; cursor:pointer; transition: color 0.3s;"
                                onmouseenter="this.style.color='var(--accent)'" onmouseleave="this.style.color='#94a3b8'"
                                onclick="togglePass()">
                            <i class="bi bi-eye-fill" id="eyeIcon" style="font-size: 1.1rem;"></i>
                        </button>
                    </div>

                    <!-- Botón de Envío -->
                    <button type="submit" class="btn btn-login">
                        Iniciar Sesión
                    </button>
                </form>

                <!-- Links inferiores -->
                <div class="bottom-links">
                    <a href="<?= BASE_URL ?>Vista/auth/recuperar.php"><i class="bi bi-envelope me-1"></i>¿Olvidó su contraseña?</a>
                    <a href="mailto:soporte@empresa.com" class="help-link"><i class="bi bi-question-circle me-1"></i>¿Necesita ayuda?</a>
                </div>
            </div>
            
        </div>
        
    </div>
</div>

<script>
// ═══ Toggle contraseña ═══
function togglePass() {
    const inp = document.getElementById('password');
    const ico = document.getElementById('eyeIcon');
    if (inp.type === 'password') { 
        inp.type = 'text'; 
        ico.className = 'bi bi-eye-slash-fill'; 
    } else { 
        inp.type = 'password'; 
        ico.className = 'bi bi-eye-fill'; 
    }
}

// ═══ Generar partículas flotantes ═══
(function() {
    const container = document.getElementById('particles');
    const colors = ['rgba(255,255,255,0.3)', 'rgba(212,175,55,0.25)', 'rgba(77,124,15,0.15)', 'rgba(255,255,255,0.15)'];
    
    for (let i = 0; i < 25; i++) {
        const p = document.createElement('div');
        p.classList.add('particle');
        const size = Math.random() * 8 + 3;
        p.style.width = size + 'px';
        p.style.height = size + 'px';
        p.style.left = Math.random() * 100 + '%';
        p.style.background = colors[Math.floor(Math.random() * colors.length)];
        p.style.animationDuration = (Math.random() * 15 + 10) + 's';
        p.style.animationDelay = (Math.random() * 10) + 's';
        container.appendChild(p);
    }
})();
</script>
</body>
</html>
