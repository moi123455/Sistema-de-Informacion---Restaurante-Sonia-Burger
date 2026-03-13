<?php
// get_reportes_gastos.php (robusto para carpeta historial_gastos)
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

$possiblePaths = [
    __DIR__ . '/conexion.php',
    __DIR__ . '/../conexion.php',
    __DIR__ . '/../../conexion.php',
    __DIR__ . '/../../../conexion.php',
    __DIR__ . '/../../../../conexion.php' // desde historial_gastos esto debe encontrar Restaurante_SB/conexion.php
];

$found = false;
foreach ($possiblePaths as $p) {
    if (file_exists($p)) {
        require_once $p;
        $found = true;
        break;
    }
}

if (!$found) {
    http_response_code(500);
    echo json_encode(["error" => "No se encontró conexion.php. Ajusta la ruta."]);
    exit;
}

$sql = "SELECT id, titulo, total_usd, total_bs, user_id, created_at FROM reportes_gastos ORDER BY created_at DESC";
$res = $conn->query($sql);
$list = [];
if ($res) {
    while ($r = $res->fetch_assoc()) $list[] = $r;
}
echo json_encode($list);
