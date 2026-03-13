<?php
// delete_usuario.php
require_once __DIR__ . '/../../../conexion.php';
header('Content-Type: application/json; charset=utf-8');
session_start();

try {
    // Validar sesión
    if (empty($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'No autenticado']);
        exit;
    }

    // Solo permitir a gerentes eliminar usuarios
    $currentUserId = intval($_SESSION['user_id']);
    $currentUserRole = $_SESSION['user_role'] ?? null; // asegúrate de setear esto en el login
    if ($currentUserRole !== 'gerente') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Permisos insuficientes']);
        exit;
    }

    // Leer id desde POST
    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ID inválido']);
        exit;
    }

    // Evitar auto-eliminación
    if ($id === $currentUserId) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'No puedes eliminar tu propia cuenta']);
        exit;
    }

    // Comprobar existencia del usuario
    $stmt = $conn->prepare("SELECT id, nombre, rol FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Usuario no encontrado']);
        exit;
    }
    $userRow = $res->fetch_assoc();
    $stmt->close();

    // Ejecutar eliminación
    $stmt = $conn->prepare("DELETE FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Error al eliminar usuario']);
    }
    $stmt->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
