<?php
// Controlador/ReporteController.php
class ReporteController {

    public function getReporteVentas(string $desde, string $hasta, string $estado = 'todas'): array {
        $pdo = db();
        $params = [$desde, $hasta];
        $sql = "SELECT v.*, c.nombre AS cliente, c.documento as cliente_doc
                FROM ventas v
                LEFT JOIN clientes c ON v.cliente_id = c.id
                WHERE DATE(v.fecha) >= ? AND DATE(v.fecha) <= ?";
        
        if ($estado !== 'todas') {
            $sql .= " AND v.estado = ?";
            $params[] = $estado; // 'completada' or 'anulada'
        }
        
        $sql .= " ORDER BY v.fecha DESC, v.id DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getReporteCompras(string $desde, string $hasta, int $proveedor_id = 0): array {
        $pdo = db();
        $params = [$desde, $hasta];
        $sql = "SELECT c.*, p.nombre AS proveedor, p.rif as proveedor_rif
                FROM compras c
                LEFT JOIN proveedores p ON c.proveedor_id = p.id
                WHERE DATE(c.fecha) >= ? AND DATE(c.fecha) <= ?";
        
        if ($proveedor_id > 0) {
            $sql .= " AND c.proveedor_id = ?";
            $params[] = $proveedor_id;
        }
        
        $sql .= " ORDER BY c.fecha DESC, c.id DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getReporteInventario(string $tipo = 'todos', int $categoria_id = 0): array {
        $pdo = db();
        $params = [];
        $sql = "SELECT p.*, c.nombre AS categoria, pr.nombre AS proveedor
                FROM productos p
                LEFT JOIN categorias c ON p.categoria_id = c.id
                LEFT JOIN proveedores pr ON p.proveedor_id = pr.id
                WHERE p.activo = 1";
        
        if ($tipo === 'bajo_stock') {
            $sql .= " AND p.stock <= p.stock_minimo";
        }
        if ($categoria_id > 0) {
            $sql .= " AND p.categoria_id = ?";
            $params[] = $categoria_id;
        }
        
        $sql .= " ORDER BY p.nombre ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
