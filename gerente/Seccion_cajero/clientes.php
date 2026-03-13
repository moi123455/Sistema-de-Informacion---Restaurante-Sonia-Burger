<?php
header("Content-Type: application/json; charset=utf-8");
require_once("../../conexion.php"); // AJUSTA RUTA

$method = $_SERVER['REQUEST_METHOD'];

if ($method === "GET") {
    $cedula = $_GET['cedula'] ?? null;
    if (!$cedula) {
        echo json_encode(["success" => false, "error" => "Cédula requerida"]);
        exit;
    }

    $stmt = $conn->prepare("SELECT id, nombre, cedula, telefono FROM clientes WHERE cedula = ?");
    $stmt->bind_param("s", $cedula);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        echo json_encode(["success" => true, "cliente" => $row]);
    } else {
        echo json_encode(["success" => false, "error" => "Cliente no encontrado"]);
    }
    $stmt->close();
    exit;
}

if ($method === "POST") {
    // Soportar FormData (application/x-www-form-urlencoded / multipart/form-data)
    $nombre = $_POST['nombreClienteReg'] ?? null;
    $cedula = $_POST['cedulaClienteReg'] ?? null;
    $telefono = $_POST['telefonoClienteReg'] ?? null;

    // Si no vienen por POST, intentar JSON (por si se envía application/json)
    if (!$nombre || !$cedula || !$telefono) {
        $raw = file_get_contents("php://input");
        $json = json_decode($raw, true);
        if (is_array($json)) {
            $nombre = $nombre ?? ($json['nombreClienteReg'] ?? $json['nombre'] ?? null);
            $cedula = $cedula ?? ($json['cedulaClienteReg'] ?? $json['cedula'] ?? null);
            $telefono = $telefono ?? ($json['telefonoClienteReg'] ?? $json['telefono'] ?? null);
        }
    }

    if (!$nombre || !$cedula || !$telefono) {
        echo json_encode(["success" => false, "error" => "Datos incompletos"]);
        exit;
    }

    // Validación básica: longitud
    if (strlen($cedula) < 6) {
        echo json_encode(["success" => false, "error" => "Cédula inválida"]);
        exit;
    }

    // Insertar (evitar duplicados por cedula)
    $stmt = $conn->prepare("SELECT id FROM clientes WHERE cedula = ?");
    $stmt->bind_param("s", $cedula);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->fetch_assoc()) {
        echo json_encode(["success" => false, "error" => "Cédula ya registrada"]);
        $stmt->close();
        exit;
    }
    $stmt->close();

    $stmt = $conn->prepare("INSERT INTO clientes (nombre, cedula, telefono) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $nombre, $cedula, $telefono);

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "id" => $stmt->insert_id]);
    } else {
        echo json_encode(["success" => false, "error" => $stmt->error]);
    }
    $stmt->close();
    exit;
}

// Método no permitido
http_response_code(405);
echo json_encode(["success" => false, "error" => "Método no permitido"]);
