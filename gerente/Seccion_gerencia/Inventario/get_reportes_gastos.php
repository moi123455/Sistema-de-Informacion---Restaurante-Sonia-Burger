<?php
// get_reportes_gastos.php - versión robusta que intenta localizar conexion.php y devuelve JSON consistente
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

// localizar conexion.php desde varias ubicaciones relativas
$possible = [
    __DIR__ . '/conexion.php',
    __DIR__ . '/../conexion.php',
    __DIR__ . '/../../conexion.php',
    __DIR__ . '/../../../conexion.php',
    __DIR__ . '/../../../../conexion.php'
];
$found = false;
foreach ($possible as $p) {
    if (file_exists($p)) {
        require_once $p;
        $found = true;
        break;
    }
}
if (!$found) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'No se encontró conexion.php. Ajusta la ruta.']);
    exit;
}

// Helper: respuesta JSON
function resp($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    if (isset($_GET['id'])) {
        $id = intval($_GET['id']);
        if ($id <= 0) resp(['success' => false, 'error' => 'ID inválido'], 400);

        $stmt = $conn->prepare("SELECT id, titulo, total_usd, total_bs, user_id, created_at, notas FROM reportes_gastos WHERE id = ? LIMIT 1");
        if (!$stmt) throw new Exception("Error preparando consulta: " . $conn->error);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        if (!$row) resp(['success' => false, 'error' => 'Reporte no encontrado'], 404);

        // Obtener items con orden seguro
        $stmt2 = $conn->prepare("SELECT id, gasto_id, nombre, cantidad, unidad, costo_unitario_usd, total_usd, total_bs, proveedor, fecha_hora FROM reportes_gastos_items WHERE reporte_id = ? ORDER BY id ASC");
        if (!$stmt2) throw new Exception("Error preparando items: " . $conn->error);
        $stmt2->bind_param("i", $id);
        $stmt2->execute();
        $res2 = $stmt2->get_result();
        $items = [];
        while ($it = $res2->fetch_assoc()) $items[] = $it;
        $stmt2->close();

        resp(['success' => true, 'reporte' => $row, 'items' => $items]);
    } else {
        $res = $conn->query("SELECT id, titulo, total_usd, total_bs, user_id, created_at FROM reportes_gastos ORDER BY created_at DESC");
        if ($res === false) throw new Exception("Error consultando reportes: " . $conn->error);
        $rows = [];
        while ($r = $res->fetch_assoc()) $rows[] = $r;
        resp(['success' => true, 'data' => $rows]);
    }
} catch (Exception $e) {
    error_log("get_reportes_gastos error: " . $e->getMessage());
    resp(['success' => false, 'error' => 'Error interno al obtener reportes'], 500);
}
