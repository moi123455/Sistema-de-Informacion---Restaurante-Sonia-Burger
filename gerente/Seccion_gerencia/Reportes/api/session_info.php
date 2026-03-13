<?php
// session_info.php - devuelve user_name y user_role desde la sesión
header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) session_start();

// compatibilidad con claves antiguas
if (!isset($_SESSION['user_name']) && isset($_SESSION['usuario'])) $_SESSION['user_name'] = $_SESSION['usuario'];
if (!isset($_SESSION['user_role']) && isset($_SESSION['rol'])) $_SESSION['user_role'] = $_SESSION['rol'];

$user_name = $_SESSION['user_name'] ?? null;
$user_role = $_SESSION['user_role'] ?? null;

if (!$user_name && !$user_role) {
    echo json_encode(['success'=>false]);
    exit;
}

echo json_encode(['success'=>true, 'user_name'=>$user_name, 'user_role'=>$user_role]);
