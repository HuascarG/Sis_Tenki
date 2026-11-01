<?php
define('ROOT', dirname(__DIR__));
require_once ROOT . '/Config/database.php';
require_once ROOT . '/Config/config.php';
require_once ROOT . '/Config/auth.php';
require_once ROOT . '/Controlador/ProveedorController.php';

header('Content-Type: application/json');

// Verificar sesión activa
if (!isLoggedIn()) {
    http_response_code(401);
    die(json_encode(['status' => 'error', 'message' => 'Sesión expirada. Inicia sesión de nuevo.']));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['status' => 'error', 'message' => 'Método no permitido']));
}

validateCsrf();

$ctrl = new ProveedorController();

$data = [
    'rif'      => trim($_POST['rif']      ?? ''),
    'nombre'   => trim($_POST['nombre']   ?? ''),
    'telefono' => trim($_POST['telefono'] ?? ''),
    'email'    => trim($_POST['email']    ?? ''),
    'direccion'=> trim($_POST['direccion']?? ''),
    'contacto' => trim($_POST['contacto'] ?? ''),
];

if (empty($data['rif']) || empty($data['nombre'])) {
    die(json_encode(['status' => 'error', 'message' => 'Rif y Nombre son obligatorios.']));
}

if ($ctrl->crear($data)) {
    $id = db()->lastInsertId();
    echo json_encode([
        'status' => 'success',
        'id' => $id,
        'nombre' => $data['nombre'],
        'rif' => $data['rif']
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'El RIF está duplicado o los datos son inválidos.'
    ]);
}
