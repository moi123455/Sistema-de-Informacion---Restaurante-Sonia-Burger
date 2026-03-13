<?php
require_once __DIR__ . '/../../../conexion.php';
header('Content-Type: application/json; charset=utf-8');

$res = $conn->query("SELECT value FROM config WHERE `key` = 'menu_horario_activo' LIMIT 1");
$row = $res ? $res->fetch_assoc() : null;
$horario = $row['value'] ?? 'dia';
echo json_encode(['success'=>true,'horario'=>$horario]);
