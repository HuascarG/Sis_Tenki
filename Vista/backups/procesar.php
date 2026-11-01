<?php
// Vista/backups/procesar.php
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/Config/database.php';
require_once ROOT . '/Config/config.php';
require_once ROOT . '/Config/auth.php';

requireAdmin();
require_once ROOT . '/Controlador/BackupController.php';

$backupCtrl = new BackupController();

// 1. Manejo de Descarga (Generar Backup)
if (isset($_GET['action']) && $_GET['action'] === 'download') {
    try {
        $backupCtrl->generarBackup();
        // Si generarBackup tiene éxito, ejecuta 'exit;' en el controlador forzando la descarga del stream binario.
    } catch (Exception $e) {
        setFlash('danger', 'Error al generar el respaldo técnico: ' . $e->getMessage());
        redirect('Vista/backups/index.php');
    }
}

// 2. Manejo de Carga (Restaurar Backup)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['backup_file'])) {
    validateCsrf();
    
    // Validar el texto de seguridad del candado
    $candado = trim($_POST['candado_seguridad'] ?? '');
    if (strtoupper($candado) !== 'RESTAURAR') {
        setFlash('danger', 'Verificación fallida: No escribiste RESTAURAR correctamente.');
        redirect('Vista/backups/index.php');
    }

    $file = $_FILES['backup_file'];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        setFlash('danger', 'Error al subir el archivo de base de datos.');
        redirect('Vista/backups/index.php');
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext !== 'sql') {
        setFlash('danger', 'Formato inválido. Solo se admiten archivos .sql generados por el sistema.');
        redirect('Vista/backups/index.php');
    }

    // Movemos el archivo de manera temporal y segura
    $tmpTarget = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tenki_upload_' . time() . '.sql';
    if (!move_uploaded_file($file['tmp_name'], $tmpTarget)) {
        setFlash('danger', 'Fallo al escribir en el disco temporal del servidor.');
        redirect('Vista/backups/index.php');
    }

    // Ejecutar Restauración
    if ($backupCtrl->restaurarBackup($tmpTarget)) {
        setFlash('success', 'Restauración Completada. La base de datos ha regresado al estado idéntico del archivo subido.');
        // Opcional: Cerrar sesión por seguridad tras restaurar (ya que los hashes de contraseña pueden haber cambiado si viajó al pasado)
        // require_once ROOT . '/Vista/auth/logout.php'; // Se podría redirigir allí.
    } else {
        setFlash('danger', 'La base de datos rechazó el archivo o falló la comunicación en la restauración.');
    }
    
    redirect('Vista/backups/index.php');
}

// Si llega hasta aquí sin entrar a los flujos, lo devolvemos
redirect('Vista/backups/index.php');
