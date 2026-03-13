<?php
// clear_all.php - borrar reportes y registrar auditoría (solo gerente)
header('Content-Type: application/json; charset=utf-8');
require_once 'F:/wamp64/www/Restaurante_Sonia_Burger/Restaurante_SB/conexion.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_role']) && isset($_SESSION['rol'])) $_SESSION['user_role'] = $_SESSION['rol'];
$user_role = $_SESSION['user_role'] ?? $_SESSION['rol'] ?? '';
$user_id = $_SESSION['user_id'] ?? null;
$user_name = $_SESSION['user_name'] ?? $_SESSION['usuario'] ?? null;

if (strtolower((string)$user_role) !== 'gerente') {
    http_response_code(403);
    echo json_encode(['success'=>false,'error'=>'Acceso denegado']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (empty($input['confirm'])) {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>'Confirmación requerida']);
    exit;
}

try {
    $conn->begin_transaction();

    // Opcional: guardar conteo previo para auditoría
    $countReportes = $conn->query("SELECT COUNT(*) AS c FROM reportes")->fetch_assoc()['c'] ?? 0;
    $countGastos   = $conn->query("SELECT COUNT(*) AS c FROM reportes_gastos")->fetch_assoc()['c'] ?? 0;

    // Borrar items relacionados si existen
    $conn->query("DELETE FROM reportes_gastos_items");

    // Borrar tablas principales
    $conn->query("DELETE FROM reportes");
    $conn->query("DELETE FROM reportes_gastos");

    // Registrar auditoría
    $stmt = $conn->prepare("INSERT INTO audit_log (action, user_id, user_name, details) VALUES (?, ?, ?, ?)");
    $action = 'clear_all_reportes';
    $details = json_encode(['deleted_reportes' => intval($countReportes), 'deleted_reportes_gastos' => intval($countGastos)]);
    $stmt->bind_param("siss", $action, $user_id, $user_name, $details);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
    echo json_encode(['success'=>true]);
} catch (Exception $e) {
    $conn->rollback();
    error_log("clear_all.php error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'Error interno']);
}
