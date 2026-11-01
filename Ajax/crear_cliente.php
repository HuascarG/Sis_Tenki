<?php
define('ROOT', dirname(__DIR__));
require_once ROOT . '/Config/database.php';
require_once ROOT . '/Config/config.php';
require_once ROOT . '/Config/auth.php';
require_once ROOT . '/Controlador/ClienteController.php';

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

$ctrl = new ClienteController();

$data = [
    'tipo_documento' => in_array($_POST['tipo_documento'] ?? '', ['CI','RIF']) ? $_POST['tipo_documento'] : 'CI',
    'documento'      => trim($_POST['documento']  ?? ''),
    'nombre'         => trim($_POST['nombre']      ?? ''),
    'telefono'       => trim($_POST['telefono']    ?? ''),
    'email'          => trim($_POST['email']       ?? ''),
    'direccion'      => trim($_POST['direccion']   ?? ''),
    'referencia'     => trim($_POST['referencia']  ?? ''),
];

// Validaciones
if (empty($data['documento']) || !preg_match('/^[a-zA-Z0-9\-]{6,20}$/', $data['documento'])) {
    die(json_encode(['status' => 'error', 'message' => 'El documento es inválido (alfanumérico, 6-20 caracteres).']));
}
if (empty($data['nombre']) || strlen($data['nombre']) > 100 || !preg_match('/^[a-zA-ZÀ-ÿ\s]{2,100}$/', $data['nombre'])) {
    die(json_encode(['status' => 'error', 'message' => 'El nombre es inválido (solo letras, 2-100 caracteres).']));
}
if (!empty($data['telefono']) && !preg_match('/^[0-9\-\+\s]{7,20}$/', $data['telefono'])) {
    die(json_encode(['status' => 'error', 'message' => 'El formato del teléfono es inválido.']));
}
if (!empty($data['email']) && (!filter_var($data['email'], FILTER_VALIDATE_EMAIL) || strlen($data['email']) > 150)) {
    die(json_encode(['status' => 'error', 'message' => 'El correo electrónico es inválido.']));
}
if (!empty($data['direccion']) && strlen($data['direccion']) > 255) {
    die(json_encode(['status' => 'error', 'message' => 'La dirección no puede exceder los 255 caracteres.']));
}
if (!empty($data['referencia']) && strlen($data['referencia']) > 255) {
    die(json_encode(['status' => 'error', 'message' => 'La referencia no puede exceder los 255 caracteres.']));
}

if ($ctrl->crear($data)) {
    $id = db()->lastInsertId();
    echo json_encode([
        'status'    => 'success',
        'id'        => $id,
        'nombre'    => $data['nombre'],
        'documento' => $data['documento']
    ]);
} else {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Error al guardar. El documento puede estar duplicado.'
    ]);
}
