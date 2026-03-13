<?php
// logout.php (mejorado para depuración)
require_once __DIR__ . '/conexion.php'; // ajusta si tu conexion.php está en otra ruta
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!empty($_SESSION['user_id'])) {
    $uid = intval($_SESSION['user_id']);
    // actualizar last_login_at y last_activity_at
    $stmt = $conn->prepare("UPDATE usuarios SET last_login_at = NOW(), last_activity_at = NOW() WHERE id = ?");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    error_log("logout.php: updated last_login_at for user_id={$uid}");
} else {
    error_log("logout.php: no session user_id present");
}

// destruir sesión y devolver OK
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

echo json_encode(['success'=>true]);
