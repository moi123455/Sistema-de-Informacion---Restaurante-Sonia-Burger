<?php
// login.php - versión corregida y compatible
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/conexion.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success'=>false,'message'=>'Método no permitido']);
    exit;
}

$correo = trim($_POST['correo'] ?? '');
$password = $_POST['password'] ?? '';

if ($correo === '' || $password === '') {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'Correo y contraseña requeridos']);
    exit;
}

$stmt = $conn->prepare("SELECT id, nombre, correo, password, rol FROM usuarios WHERE correo = ?");
$stmt->bind_param("s", $correo);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows !== 1) {
    echo json_encode(['success'=>false,'message'=>'Credenciales incorrectas']);
    exit;
}

$user = $res->fetch_assoc();
$stored = $user['password'];

if (!password_verify($password, $stored)) {
    echo json_encode(['success'=>false,'message'=>'Credenciales incorrectas']);
    exit;
}

// -------------------------------------------------
// Login exitoso: asegurar cookie y variables de sesión
// -------------------------------------------------
// Ajustar cookie params ANTES de session_start
session_set_cookie_params([
  'lifetime' => 0,
  'path'     => '/',
  'domain'   => '',      // vacío = host actual (localhost)
  'secure'   => false,   // true solo si usas HTTPS
  'httponly' => true,
  'samesite' => 'Lax'
]);

if (session_status() === PHP_SESSION_NONE) session_start();

// Asignar datos de sesión (nombres modernos)
$_SESSION['user_id']   = intval($user['id']);
$_SESSION['user_role'] = $user['rol'];
$_SESSION['user_name'] = $user['nombre'];

// Compatibilidad con código antiguo (evita 403 en endpoints existentes)
$_SESSION['rol']     = $user['rol'];
$_SESSION['usuario'] = $user['nombre'];

// Regenerar id de sesión por seguridad
session_regenerate_id(true);

// actualizar last_activity_at
$u2 = $conn->prepare("UPDATE usuarios SET last_activity_at = NOW() WHERE id = ?");
$u2->bind_param("i", $_SESSION['user_id']);
$u2->execute();
$u2->close();

$redirect = ($user['rol'] === 'gerente')
    ? '/Restaurante_Sonia_Burger/Restaurante_SB/gerente/Seccion_gerencia/gerente.html'
    : '/Restaurante_Sonia_Burger/Restaurante_SB/gerente/Seccion_cajero/cajero.html';

echo json_encode([
    'success' => true,
    'usuario' => $user['nombre'],
    'rol' => $user['rol'],
    'userId' => $_SESSION['user_id'],
    'redirect' => $redirect
]);
exit;
