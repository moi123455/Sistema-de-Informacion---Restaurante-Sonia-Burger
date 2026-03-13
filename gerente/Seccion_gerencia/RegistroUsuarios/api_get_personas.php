<?php
// api_get_personas.php - lista unificada usuarios + clientes (incluye telefono)
require_once __DIR__ . '/../../../conexion.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $data = [];

    // 1) Usuarios (gerente, cajero, etc.) - incluir telefono
    $sqlU = "SELECT id, nombre, correo, rol, telefono,
                    last_login_at, last_activity_at, last_order_at,
                    COALESCE(orders_completed_count,0) AS orders_completed_count,
                    COALESCE(orders_delivered_count,0) AS orders_delivered_count,
                    COALESCE(cash_closes_count,0) AS cash_closes_count
             FROM usuarios";
    $resU = $conn->query($sqlU);
    if ($resU) {
        while ($r = $resU->fetch_assoc()) {
            $r['tipo'] = 'usuario';
            // Normalizar campos para frontend
            if (!isset($r['telefono'])) $r['telefono'] = null;
            $r['pedidos_count'] = 0;
            $r['ultima_compra'] = $r['last_order_at'] ?? null;
            $data[] = $r;
        }
    }

    // 2) Clientes (incluye telefono, pedidos_count, ultima_compra)
    $sqlC = "SELECT CONCAT('c_', id) AS id, nombre, telefono, NULL AS correo,
                    'cliente' AS rol, NULL AS last_login_at, NULL AS last_activity_at,
                    ultima_compra AS last_order_at,
                    0 AS orders_completed_count, 0 AS orders_delivered_count, 0 AS cash_closes_count,
                    COALESCE(pedidos_count,0) AS pedidos_count,
                    ultima_compra AS ultima_compra
             FROM clientes";
    $resC = $conn->query($sqlC);
    if ($resC) {
        while ($r = $resC->fetch_assoc()) {
            $r['tipo'] = 'cliente';
            if (!isset($r['telefono'])) $r['telefono'] = null;
            $data[] = $r;
        }
    }

    echo json_encode(['success'=>true,'data'=>$data], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
