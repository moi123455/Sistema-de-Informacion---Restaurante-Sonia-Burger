<?php
// export_pedidos.php
ini_set('display_errors', 0);
error_reporting(0);
require_once("../../conexion.php");
date_default_timezone_set('America/Caracas');

// Recibir filtros (mismos que get_pedidos.php)
$estado = isset($_GET['estado']) && $_GET['estado'] !== '' ? trim($_GET['estado']) : null;
$desde  = isset($_GET['desde']) && $_GET['desde'] !== '' ? trim($_GET['desde']) : null;
$hasta  = isset($_GET['hasta']) && $_GET['hasta'] !== '' ? trim($_GET['hasta']) : null;
$q      = isset($_GET['q']) ? trim($_GET['q']) : null;

$where = [];
$params = [];
$types = "";

if ($estado) {
    $where[] = "LOWER(TRIM(estado)) = LOWER(TRIM(?))";
    $types .= "s";
    $params[] = $estado;
}
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
if ($q) {
    $where[] = "(cliente LIKE CONCAT('%',?,'%') OR telefono LIKE CONCAT('%',?,'%') OR plato_nombre LIKE CONCAT('%',?,'%'))";
    $types .= "sss";
    $params[] = $q; $params[] = $q; $params[] = $q;
}

$where_sql = count($where) ? "WHERE " . implode(" AND ", $where) : "";

$sql = "SELECT id, cliente, telefono, metodo_pago, plato_nombre, cantidad, plato_precio, total, plato_precio_bs, estado, fecha FROM pedidos $where_sql ORDER BY fecha DESC";
$stmt = $conn->prepare($sql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();

// Cabeceras CSV
$filename = "pedidos_export_" . date("Ymd_His") . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="'.$filename.'"');

$out = fopen('php://output', 'w');
// encabezado
fputcsv($out, ['ID','Cliente','Telefono','MetodoPago','Plato','Cantidad','PrecioUSD','TotalUSD','PrecioBs','Estado','Fecha']);

while ($row = $res->fetch_assoc()) {
    fputcsv($out, [
        $row['id'],
        $row['cliente'],
        $row['telefono'],
        $row['metodo_pago'],
        $row['plato_nombre'],
        $row['cantidad'],
        number_format((float)$row['plato_precio'], 2, '.', ''),
        number_format((float)$row['total'], 2, '.', ''),
        $row['plato_precio_bs'] !== null ? number_format((float)$row['plato_precio_bs'], 2, '.', '') : '',
        $row['estado'],
        $row['fecha']
    ]);
}
fclose($out);
exit;
