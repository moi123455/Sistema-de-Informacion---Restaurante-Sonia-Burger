<?php
// delete_pedido.php
ini_set('display_errors', 1); // 1 solo para debug; poner 0 en producción
error_reporting(E_ALL);
header("Content-Type: application/json; charset=utf-8");

require_once(__DIR__ . "/../../conexion.php");
date_default_timezone_set('America/Caracas');

if (!isset($conn) || !$conn) {
    error_log("delete_pedido: conexion no encontrada");
    http_response_code(500);
    echo json_encode(["success"=>false,"error"=>"Error interno: BD no disponible"]);
    exit;
}

$input = file_get_contents("php://input");
$data = json_decode($input, true);
if (!is_array($data)) $data = $_POST;

$id = isset($data['id']) ? (int)$data['id'] : 0;
$usuario = isset($data['usuario']) ? trim($data['usuario']) : 'Sistema';

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(["success"=>false,"error"=>"ID requerido"]);
    exit;
}

// Verificar estado actual (opcional)
$stmt = $conn->prepare("SELECT estado FROM pedidos WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

if (!$row) {
    http_response_code(404);
    echo json_encode(["success"=>false,"error"=>"Pedido no encontrado"]);
    exit;
}
if (strtolower(trim($row['estado'])) !== 'cancelado') {
    http_response_code(400);
    echo json_encode(["success"=>false,"error"=>"Solo se pueden borrar pedidos cancelados"]);
    exit;
}

try {
    $stmt = $conn->prepare("DELETE FROM pedidos WHERE id = ?");
    $stmt->bind_param("i", $id);
    if (!$stmt->execute()) {
        error_log("delete_pedido execute error: " . $stmt->error);
        throw new Exception("Error al borrar pedido");
    }
    $stmt->close();
    echo json_encode(["success"=>true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success"=>false,"error"=>"Error interno"]);
    error_log("delete_pedido exception: " . $e->getMessage());
}
