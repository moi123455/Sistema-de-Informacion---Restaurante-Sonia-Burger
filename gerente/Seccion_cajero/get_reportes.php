<?php
header("Content-Type: application/json; charset=utf-8");
require_once(__DIR__ . "/../../conexion.php");
if (!isset($conn) || !$conn) { http_response_code(500); echo json_encode(["success"=>false,"error"=>"BD no disponible"]); exit; }

$res = $conn->query("SELECT id, fecha_reporte, usuario, tasa_usd_bs, total_usd, total_bs, pedidos_json, caja_num, plato_favorito FROM reportes ORDER BY fecha_reporte DESC");
$rows = [];
if ($res) {
  while ($r = $res->fetch_assoc()) $rows[] = $r;
}
echo json_encode(["success"=>true,"reportes"=>$rows]);
