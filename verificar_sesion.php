<?php
// verificar_sesion.php - versión compatible con AJAX y con redirección normal
require_once __DIR__ . '/conexion.php';
header('Content-Type: application/json; charset=utf-8');
session_start();

// Normalizar claves de sesión (compatibilidad con alias)
if (!isset($_SESSION['user_role']) && isset($_SESSION['rol'])) {
    $_SESSION['user_role'] = $_SESSION['rol'];
}
if (!isset($_SESSION['user_name']) && isset($_SESSION['usuario'])) {
    $_SESSION['user_name'] = $_SESSION['usuario'];
}

// Detectar si la petición espera JSON (AJAX)
$acceptsJson = false;
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    $acceptsJson = true;
} elseif (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
    $acceptsJson = true;
}

// Si no hay sesión activa -> comportamiento según tipo de petición
if (!isset($_SESSION['user_name'])) {
    if ($acceptsJson) {
        echo json_encode(['logged' => false]);
        exit();
    } else {
        header("Location: /Restaurante_Sonia_Burger/Restaurante_SB/index.html");
        exit();
    }
}

// Preparar datos de sesión
$response = [
    'logged' => true,
    'id' => $_SESSION['user_id'] ?? null,
    'name' => $_SESSION['user_name'] ?? null,
    'role' => $_SESSION['user_role'] ?? ($_SESSION['rol'] ?? null)
];

// Si la petición es AJAX devolvemos JSON y salimos
if ($acceptsJson) {
    echo json_encode($response);
    exit();
}

// Si no es AJAX, mantenemos la lógica de redirección por página (compatibilidad)
$pagina = basename($_SERVER['PHP_SELF']);
$role = $response['role'] ?? '';

// Reglas de redirección por página (ajusta rutas si hace falta)
if ($pagina === "gerente.html" && $role !== "gerente") {
    header("Location: /Restaurante_Sonia_Burger/Restaurante_SB/gerente/Seccion_cajero/cajero.html");
    exit();
}
if ($pagina === "cajero.html" && $role !== "cajero") {
    header("Location: /Restaurante_Sonia_Burger/Restaurante_SB/gerente/Seccion_gerencia/gerente.html");
    exit();
}

// Si no aplica ninguna regla, devolvemos JSON por si alguien lo solicita (opcional)
echo json_encode($response);
exit();
