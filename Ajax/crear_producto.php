<?php
define('ROOT', dirname(__DIR__));
require_once ROOT . '/Config/database.php';
require_once ROOT . '/Config/config.php';
require_once ROOT . '/Config/auth.php';
require_once ROOT . '/Controlador/InventarioController.php';

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

$ctrl = new InventarioController();

$data = [
    'codigo'        => trim($_POST['codigo'] ?? ''),
    'nombre'        => trim($_POST['nombre'] ?? ''),
    'descripcion'   => trim($_POST['descripcion'] ?? ''),
    'categoria_id'  => (int)($_POST['categoria_id'] ?? 0),
    'proveedor_id'  => (int)($_POST['proveedor_id'] ?? 0),
    'precio_compra' => (float)str_replace(',', '.', ($_POST['precio_compra'] ?? '0')),
    'precio_venta'  => (float)str_replace(',', '.', ($_POST['precio_venta'] ?? '0')),
    'stock'         => (int)($_POST['stock'] ?? 0),
    'stock_minimo'  => (int)($_POST['stock_minimo'] ?? 5),
    'unidad'        => trim($_POST['unidad'] ?? 'unidad'),
    'marca'         => trim($_POST['marca'] ?? ''),
    'condicion'     => in_array($_POST['condicion'] ?? '', ['nuevo','usado']) ? $_POST['condicion'] : 'nuevo',
    'ubicacion'     => trim($_POST['ubicacion'] ?? ''),
];

if (!empty($data['ubicacion']) && strlen($data['ubicacion']) > 150) {
    die(json_encode(['status' => 'error', 'message' => 'La ubicación no puede exceder los 150 caracteres.']));
}

if (!empty($data['codigo'])) {
    if (strlen($data['codigo']) > 50 || !preg_match('/^[a-zA-Z0-9\-\_]+$/', $data['codigo'])) {
        die(json_encode(['status' => 'error', 'message' => 'El código es inválido o excede los 50 caracteres permitidos.']));
    }
} else {
    $data['codigo'] = null;
}

if (empty($data['nombre']) || $data['precio_venta'] <= 0) {
    die(json_encode(['status' => 'error', 'message' => 'Nombre y Precio de Venta (Mayor a 0) son obligatorios.']));
}

$result = $ctrl->crear($data);
if ($result === true) {
    // Handle optional photo upload
    $id = db()->lastInsertId();
    if (!empty($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $fotoPath = $ctrl->subirFoto($_FILES['foto']);
        if ($fotoPath) {
            $pdo = db();
            $pdo->prepare("UPDATE productos SET foto = ? WHERE id = ?")->execute([$fotoPath, $id]);
        }
    }
    echo json_encode([
        'status' => 'success',
        'id' => $id,
        'codigo' => $data['codigo'],
        'nombre' => $data['nombre'],
        'precio_compra' => $data['precio_compra'],
        'precio_venta' => $data['precio_venta'],
        'stock' => $data['stock']
    ]);
} else {
    // $result es un string con el mensaje de error descriptivo
    $msg = is_string($result) ? $result : 'Error al guardar. Posible código duplicado.';
    echo json_encode([
        'status' => 'error',
        'message' => $msg
    ]);
}
