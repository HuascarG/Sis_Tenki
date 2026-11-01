<?php
// Ajax/dashboard_stats.php — AJAX endpoint for dashboard data
define('ROOT', dirname(__DIR__));
require_once ROOT . '/Config/database.php';
require_once ROOT . '/Config/config.php';
require_once ROOT . '/Config/env.php';
require_once ROOT . '/Config/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'facturacion_historica':
        require_once ROOT . '/Controlador/FacturaController.php';
        $facCtrl = new FacturaController();
        $meses = (int)($_GET['meses'] ?? 12);
        $meses = max(1, min($meses, 24));
        $data = $facCtrl->facturacionPorMes($meses);
        echo json_encode(['success' => true, 'data' => $data]);
        break;

    case 'ventas_historicas':
        require_once ROOT . '/Controlador/VentaController.php';
        $ventaCtrl = new VentaController();
        $meses = (int)($_GET['meses'] ?? 6);
        $meses = max(1, min($meses, 24));
        $data = $ventaCtrl->ventasPorMes($meses);
        echo json_encode(['success' => true, 'data' => $data]);
        break;

    case 'compras_historicas':
        require_once ROOT . '/Controlador/CompraController.php';
        $compCtrl = new CompraController();
        $pdo = db();
        $meses = (int)($_GET['meses'] ?? 12);
        $meses = max(1, min($meses, 24));
        $stmt = $pdo->prepare("SELECT DATE_FORMAT(fecha,'%Y-%m') AS mes,
                COUNT(*) AS total_compras,
                COALESCE(SUM(total),0) AS total
                FROM compras
                WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
                GROUP BY mes
                ORDER BY mes DESC");
        $stmt->execute([$meses]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        break;

    default:
        echo json_encode(['error' => 'Acción no válida']);
        break;
}
