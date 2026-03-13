<?php
// get_pedidos.php
ini_set('display_errors', 0);
error_reporting(0);
header("Content-Type: application/json; charset=utf-8");
require_once("../../conexion.php");
date_default_timezone_set('America/Caracas');

$estado = isset($_GET['estado']) && $_GET['estado'] !== '' ? trim($_GET['estado']) : null;
$desde  = isset($_GET['desde']) && $_GET['desde'] !== '' ? trim($_GET['desde']) : null;
$hasta  = isset($_GET['hasta']) && $_GET['hasta'] !== '' ? trim($_GET['hasta']) : null;
$q      = isset($_GET['q']) ? trim($_GET['q']) : null;
$limit  = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

$where = [];
$params = [];
$types = "";

// estado
if ($estado) {
    $where[] = "LOWER(TRIM(estado)) = LOWER(TRIM(?))";
    $types .= "s";
    $params[] = $estado;
}

// rango de fechas (por fecha)
if ($desde && $hasta) {
    $where[] = "DATE(fecha) BETWEEN ? AND ?";
    $types .= "ss";
    $params[] = $desde;
    $params[] = $hasta;
} elseif ($desde) {
    $where[] = "DATE(fecha) >= ?";
    $types .= "s";
    $params[] = $desde;
} elseif ($hasta) {
    $where[] = "DATE(fecha) <= ?";
    $types .= "s";
    $params[] = $hasta;
}

// búsqueda por cliente o teléfono
if ($q) {
    $where[] = "(cliente LIKE CONCAT('%',?,'%') OR telefono LIKE CONCAT('%',?,'%') OR plato_nombre LIKE CONCAT('%',?,'%'))";
    $types .= "sss";
    $params[] = $q;
    $params[] = $q;
    $params[] = $q;
}

$where_sql = count($where) ? "WHERE " . implode(" AND ", $where) : "";

// contar total
$count_sql = "SELECT COUNT(*) AS total FROM pedidos $where_sql";
$stmt = $conn->prepare($count_sql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();
$total = $res->fetch_assoc()['total'] ?? 0;
$stmt->close();

// obtener filas
$sql = "SELECT id, cliente, telefono, metodo_pago, plato_id, plato_nombre, plato_precio, cantidad, total, plato_precio_bs, estado, fecha
        FROM pedidos
        $where_sql
        ORDER BY fecha DESC
        LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Error interno: ".$conn->error]);
    exit;
}

// bind dinámico: agregar limit y offset
$types_with_limit = $types . "ii";
$params_with_limit = $params;
$params_with_limit[] = $limit;
$params_with_limit[] = $offset;

$stmt->bind_param($types_with_limit, ...$params_with_limit);
$stmt->execute();
$res = $stmt->get_result();

$rows = [];
while ($r = $res->fetch_assoc()) {
    // formatear fecha local si quieres
    $r['fecha'] = date("Y-m-d H:i:s", strtotime($r['fecha']));
    // asegurar tipos
    $r['plato_precio'] = isset($r['plato_precio']) ? (float)$r['plato_precio'] : null;
    $r['plato_precio_bs'] = isset($r['plato_precio_bs']) ? (float)$r['plato_precio_bs'] : null;
    $r['total'] = isset($r['total']) ? (float)$r['total'] : 0.0;
    $rows[] = $r;
}
$stmt->close();

// estadísticas rápidas (hoy)
$stats = ["total_pedidos_hoy" => 0, "ventas_usd_hoy" => 0.0, "ventas_bs_hoy" => 0.0];
$today_sql = "SELECT COUNT(*) AS cnt, COALESCE(SUM(total),0) AS ventas_usd, COALESCE(SUM(plato_precio_bs),0) AS ventas_bs FROM pedidos WHERE DATE(fecha) = CURDATE() AND LOWER(TRIM(estado)) = 'entregado'";
$r = $conn->query($today_sql);
if ($r && $row = $r->fetch_assoc()) {
    $stats["total_pedidos_hoy"] = (int)$row['cnt'];
    $stats["ventas_usd_hoy"] = (float)$row['ventas_usd'];
    $stats["ventas_bs_hoy"] = (float)$row['ventas_bs'];
}

echo json_encode(["success" => true, "total" => (int)$total, "pedidos" => $rows, "stats" => $stats]);
