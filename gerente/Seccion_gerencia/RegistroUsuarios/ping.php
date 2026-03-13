<?php
// ping.php (mejorado para depuración)
require_once __DIR__ . '/../../../conexion.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    error_log("ping.php: no session user_id");
    echo json_encode(['success'=>false,'error'=>'No autenticado']);
    exit;
}

$uid = intval($_SESSION['user_id']);
$stmt = $conn->prepare("UPDATE usuarios SET last_activity_at = NOW() WHERE id = ?");
$stmt->bind_param("i", $uid);
$stmt->execute();
error_log("ping.php: updated last_activity_at for user_id={$uid}");

echo json_encode(['success'=>true]);
