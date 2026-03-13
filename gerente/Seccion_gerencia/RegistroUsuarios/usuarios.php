<?php
// usuarios.php (completo) - crear / listar / editar / eliminar usuarios con campo telefono
// Ahora con soporte para editar/eliminar clientes cuando el id viene con prefijo c_
// (ej: id = "c_12" -> cliente id 12)
require_once __DIR__ . '/../../../conexion.php';
header('Content-Type: application/json; charset=utf-8');
session_start();

$method = $_SERVER['REQUEST_METHOD'];
$override = null;
if ($method === 'POST' && isset($_POST['_method'])) {
    $override = strtoupper(trim($_POST['_method']));
}

try {
    // -----------------------
    // GET -> listar usuarios (sin password)
    // -----------------------
    if ($method === 'GET') {
        $sql = "SELECT id, nombre, correo, rol, telefono, last_login_at, last_activity_at, last_order_at,
                       COALESCE(orders_completed_count,0) AS orders_completed_count,
                       COALESCE(orders_delivered_count,0) AS orders_delivered_count,
                       COALESCE(cash_closes_count,0) AS cash_closes_count
                FROM usuarios";
        $res = $conn->query($sql);
        $out = [];
        if ($res) {
            while ($r = $res->fetch_assoc()) $out[] = $r;
        }
        echo json_encode(['success'=>true,'data'=>$out], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // -----------------------
    // POST -> crear usuario
    // -----------------------
    if ($method === 'POST' && $override === null) {
        $nombre = trim($_POST['nombre'] ?? '');
        $correo = trim($_POST['correo'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $password = $_POST['password'] ?? '';
        $rol = trim($_POST['rol'] ?? 'cajero');

        if ($nombre === '' || $correo === '' || $password === '') {
            http_response_code(400);
            echo json_encode(['success'=>false,'error'=>'Nombre, correo y contraseña requeridos']);
            exit;
        }

        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['success'=>false,'error'=>'Correo inválido']);
            exit;
        }

        // Evitar duplicados por correo
        $stmt = $conn->prepare("SELECT id FROM usuarios WHERE correo = ?");
        $stmt->bind_param("s", $correo);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            http_response_code(409);
            echo json_encode(['success'=>false,'error'=>'Correo ya registrado']);
            exit;
        }
        $stmt->close();

        // Hash de contraseña
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("INSERT INTO usuarios (nombre, correo, password, rol, telefono) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $nombre, $correo, $hash, $rol, $telefono);
        if ($stmt->execute()) {
            echo json_encode(['success'=>true,'id'=>$conn->insert_id]);
        } else {
            http_response_code(500);
            echo json_encode(['success'=>false,'error'=>'Error al insertar usuario']);
        }
        $stmt->close();
        exit;
    }

    // -----------------------
    // PUT -> editar usuario (via POST + _method=PUT) o editar cliente si id tiene prefijo c_
    // -----------------------
    if (($method === 'POST' && $override === 'PUT') || $method === 'PUT') {
        $data = $_POST;
        if ($method === 'PUT') parse_str(file_get_contents("php://input"), $data);

        // Tomar raw id para detectar prefijo de cliente
        $rawId = $data['id'] ?? '';
        $rawId = is_string($rawId) ? trim($rawId) : $rawId;

        // Si viene con prefijo c_ -> actualizar tabla clientes
        if (is_string($rawId) && preg_match('/^c_(\d+)$/', $rawId, $m)) {
            $clientId = intval($m[1]);
            $nombre = trim($data['nombre'] ?? '');
            $telefono = trim($data['telefono'] ?? '');

            if ($clientId <= 0 || $nombre === '') {
                http_response_code(400);
                echo json_encode(['success'=>false,'error'=>'Parámetros inválidos']);
                exit;
            }

            $stmt = $conn->prepare("UPDATE clientes SET nombre = ?, telefono = ? WHERE id = ?");
            $stmt->bind_param("ssi", $nombre, $telefono, $clientId);
            if ($stmt->execute()) {
                echo json_encode(['success'=>true]);
            } else {
                http_response_code(500);
                echo json_encode(['success'=>false,'error'=>'Error al actualizar cliente']);
            }
            $stmt->close();
            exit;
        }

        // Si no es cliente, tratar como usuario normal
        $id = intval($data['id'] ?? 0);
        $nombre = trim($data['nombre'] ?? '');
        $correo = trim($data['correo'] ?? '');
        $telefono = trim($data['telefono'] ?? '');
        $password = $data['password'] ?? '';

        if ($id <= 0 || $nombre === '' || $correo === '') {
            http_response_code(400);
            echo json_encode(['success'=>false,'error'=>'Parámetros inválidos']);
            exit;
        }

        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['success'=>false,'error'=>'Correo inválido']);
            exit;
        }

        // Evitar duplicado de correo en otro usuario
        $stmt = $conn->prepare("SELECT id FROM usuarios WHERE correo = ? AND id <> ?");
        $stmt->bind_param("si", $correo, $id);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            http_response_code(409);
            echo json_encode(['success'=>false,'error'=>'Correo ya en uso por otro usuario']);
            exit;
        }
        $stmt->close();

        if (!empty($password)) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE usuarios SET nombre = ?, correo = ?, telefono = ?, password = ? WHERE id = ?");
            $stmt->bind_param("ssssi", $nombre, $correo, $telefono, $hash, $id);
        } else {
            $stmt = $conn->prepare("UPDATE usuarios SET nombre = ?, correo = ?, telefono = ? WHERE id = ?");
            $stmt->bind_param("sssi", $nombre, $correo, $telefono, $id);
        }

        if ($stmt->execute()) {
            echo json_encode(['success'=>true]);
        } else {
            http_response_code(500);
            echo json_encode(['success'=>false,'error'=>'Error al actualizar usuario']);
        }
        $stmt->close();
        exit;
    }

    // -----------------------
    // DELETE -> eliminar usuario (via POST + _method=DELETE) o eliminar cliente si id tiene prefijo c_
    // -----------------------
    if (($method === 'POST' && $override === 'DELETE') || $method === 'DELETE') {
        $data = $_POST;
        if ($method === 'DELETE') parse_str(file_get_contents("php://input"), $data);

        $rawId = $data['id'] ?? '';
        $rawId = is_string($rawId) ? trim($rawId) : $rawId;

        // Si id con prefijo c_ -> eliminar cliente
        if (is_string($rawId) && preg_match('/^c_(\d+)$/', $rawId, $m)) {
            $clientId = intval($m[1]);
            if ($clientId <= 0) {
                http_response_code(400);
                echo json_encode(['success'=>false,'error'=>'ID inválido']);
                exit;
            }

            $stmt = $conn->prepare("DELETE FROM clientes WHERE id = ?");
            $stmt->bind_param("i", $clientId);
            if ($stmt->execute()) {
                echo json_encode(['success'=>true]);
            } else {
                http_response_code(500);
                echo json_encode(['success'=>false,'error'=>'No se pudo eliminar el cliente']);
            }
            $stmt->close();
            exit;
        }

        // Si no es cliente, eliminar usuario normal
        $id = intval($data['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success'=>false,'error'=>'ID inválido']);
            exit;
        }

        if (!empty($_SESSION['user_id']) && intval($_SESSION['user_id']) === $id) {
            http_response_code(403);
            echo json_encode(['success'=>false,'error'=>'No puedes eliminar tu propia cuenta']);
            exit;
        }

        $stmt = $conn->prepare("DELETE FROM usuarios WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            echo json_encode(['success'=>true]);
        } else {
            http_response_code(500);
            echo json_encode(['success'=>false,'error'=>'No se pudo eliminar el usuario']);
        }
        $stmt->close();
        exit;
    }

    // Método no soportado
    http_response_code(405);
    echo json_encode(['success'=>false,'error'=>'Método no soportado']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
