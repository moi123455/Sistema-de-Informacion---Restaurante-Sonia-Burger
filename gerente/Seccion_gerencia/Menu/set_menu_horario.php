<?php
require_once __DIR__ . '/../../../conexion.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_role']) || $_SESSION['user_role'] !== 'gerente') {
    http_response_code(403);
    echo json_encode(['success'=>false,'error'=>'No autorizado']);
    exit;
}

$horario = $_POST['horario'] ?? '';
if (!in_array($horario, ['dia','tarde','noche'])) {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>'Horario inválido']);
    exit;
}

$stmt = $conn->prepare("INSERT INTO config (`key`,`value`) VALUES ('menu_horario_activo', ?) ON DUPLICATE KEY UPDATE value = VALUES(value)");
$stmt->bind_param("s", $horario);
if ($stmt->execute()) echo json_encode(['success'=>true,'horario'=>$horario]);
else { http_response_code(500); echo json_encode(['success'=>false,'error'=>'No se pudo guardar']); }
