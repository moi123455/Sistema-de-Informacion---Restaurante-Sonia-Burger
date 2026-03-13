<?php
// reportes_list.php
ini_set('display_errors', 0);
error_reporting(0);
header("Content-Type: application/json; charset=utf-8");
require_once("../../conexion.php");
date_default_timezone_set('America/Caracas');

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

if ($id) {
    $stmt = $conn->prepare("SELECT id, fecha_reporte, usuario, tasa_usd_bs, total_usd, total_bs, pedidos_json, created_at FROM reportes WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();
    if ($row) {
        $row['pedidos'] = json_decode($row['pedidos_json'], true);
        unset($row['pedidos_json']);
        echo json_encode(["success" => true, "report" => $row]);
    } else {
        echo json_encode(["success" => false, "error" => "Reporte no encontrado"]);
    }
    exit;
}

// listar todos los reportes (paginado simple)
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

$stmt = $conn->prepare("SELECT id, fecha_reporte, usuario, tasa_usd_bs, total_usd, total_bs, created_at FROM reportes ORDER BY fecha_reporte DESC LIMIT ? OFFSET ?");
$stmt->bind_param("ii", $limit, $offset);
$stmt->execute();
$res = $stmt->get_result();
$rows = [];
while ($r = $res->fetch_assoc()) {
    $rows[] = $r;
}
$stmt->close();

echo json_encode(["success" => true, "total" => count($rows), "reportes" => $rows]);
