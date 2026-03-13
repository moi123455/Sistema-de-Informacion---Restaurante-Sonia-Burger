<?php
// update_pedido_estado.php - versión que usa sesión y actualiza actividad del usuario
ini_set('display_errors', 1); // activar solo para debug; poner 0 en producción
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json; charset=utf-8");

// Ajusta la ruta: desde Seccion_cajero subir dos niveles hasta Restaurante_SB
require_once(__DIR__ . "/../../conexion.php");
date_default_timezone_set('America/Caracas');

session_start();

// Verificar que $conn exista
if (!isset($conn) || !$conn) {
    error_log("update_pedido_estado: conexión a BD no encontrada (\$conn no definida). Intentada ruta: " . __DIR__ . "/../../conexion.php");
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Error interno: conexión a BD no disponible"]);
    exit;
}

// Verificar sesión: obligamos a estar autenticado
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["success" => false, "error" => "No autenticado"]);
    exit;
}

// Id del usuario que realiza la acción (más seguro que confiar en el cliente)
$sessionUserId = intval($_SESSION['user_id']);
$sessionUserName = $_SESSION['user_name'] ?? ($_SESSION['usuario'] ?? 'Sistema');

$input = file_get_contents("php://input");
$data = json_decode($input, true);
if (!is_array($data)) $data = $_POST;

$id = isset($data['id']) ? (int)$data['id'] : 0;
$nuevo_estado = isset($data['nuevo_estado']) ? trim($data['nuevo_estado']) : '';
// $usuario enviado por cliente queda como info opcional, pero no se usará para identificar al actor
$usuario_cliente = isset($data['usuario']) ? trim($data['usuario']) : $sessionUserName;

if ($id <= 0 || $nuevo_estado === '') {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Datos incompletos"]);
    exit;
}

$allowed = ['pendiente','entregado','cancelado','archivado'];
if (!in_array(strtolower($nuevo_estado), $allowed)) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Estado no permitido"]);
    exit;
}

try {
    // Actualizar pedido
    if (strtolower($nuevo_estado) === 'entregado') {
        $fecha_entrega = date("Y-m-d H:i:s");
        $stmt = $conn->prepare("UPDATE pedidos SET estado = ?, fecha_entrega = ?, usuario_mod = ?, fecha_mod = ? WHERE id = ?");
        if (!$stmt) {
            error_log("update_pedido_estado prepare error (entregado): " . $conn->error);
            throw new Exception("Error interno al preparar consulta");
        }
        // Guardamos el nombre de sesión como usuario_mod para trazabilidad
        $stmt->bind_param("ssssi", $nuevo_estado, $fecha_entrega, $sessionUserName, $fecha_entrega, $id);
    } else {
        $fecha_mod = date("Y-m-d H:i:s");
        $stmt = $conn->prepare("UPDATE pedidos SET estado = ?, usuario_mod = ?, fecha_mod = ? WHERE id = ?");
        if (!$stmt) {
            error_log("update_pedido_estado prepare error (otros): " . $conn->error);
            throw new Exception("Error interno al preparar consulta");
        }
        $stmt->bind_param("sssi", $nuevo_estado, $sessionUserName, $fecha_mod, $id);
    }

    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        error_log("update_pedido_estado execute error: " . $err);
        throw new Exception("Error interno al ejecutar actualización");
    }
    $stmt->close();

    // Si se marcó como entregado, actualizar contador y last_activity_at del usuario que está en sesión
    if (strtolower($nuevo_estado) === 'entregado' && $sessionUserId > 0) {
        // Incrementar contador entregados y actualizar last_activity_at
        $stmt2 = $conn->prepare("UPDATE usuarios SET orders_delivered_count = COALESCE(orders_delivered_count,0) + 1, last_activity_at = NOW() WHERE id = ?");
        if ($stmt2) {
            $stmt2->bind_param("i", $sessionUserId);
            if (!$stmt2->execute()) {
                error_log("update_pedido_estado usuarios execute error: " . $stmt2->error);
            }
            $stmt2->close();
        } else {
            error_log("update_pedido_estado prepare usuarios failed: " . $conn->error);
        }
    }

    echo json_encode(["success" => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Error interno"]);
    error_log("update_pedido_estado exception: " . $e->getMessage());
}
