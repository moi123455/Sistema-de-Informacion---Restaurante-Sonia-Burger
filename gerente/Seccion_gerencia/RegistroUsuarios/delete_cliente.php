<?php
// delete_cliente.php
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

    // Permisos: permitir gerentes y cajeros eliminar clientes
    $currentUserRole = $_SESSION['user_role'] ?? null; // asegúrate de setear esto en el login
    if (!in_array($currentUserRole, ['gerente', 'cajero'])) {
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

    // Comprobar existencia del cliente
    $stmt = $conn->prepare("SELECT id, nombre FROM clientes WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Cliente no encontrado']);
        exit;
    }
    $stmt->close();

    // Ejecutar eliminación
    $stmt = $conn->prepare("DELETE FROM clientes WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Error al eliminar cliente']);
    }
    $stmt->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
