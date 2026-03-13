<?php
// cajero.php - insertar pedido guardando snapshot de precio en Bs (versión robusta)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.txt');
header("Content-Type: application/json; charset=utf-8");

require_once("../../conexion.php"); // AJUSTA RUTA si hace falta
date_default_timezone_set('America/Caracas');

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== "POST") {
    http_response_code(405);
    echo json_encode(["success" => false, "error" => "Método no permitido"]);
    exit;
}

// Leer JSON o form-data
$input = file_get_contents("php://input");
$data = json_decode($input, true);
if (!is_array($data)) $data = $_POST;

// Sanitizar / validar entradas
$cliente_id = isset($data['cliente_id']) ? (int)$data['cliente_id'] : null;
$plato_id    = isset($data['plato_id']) ? (int)$data['plato_id'] : null;
$metodo_pago = isset($data['metodo_pago']) ? trim($data['metodo_pago']) : null;
$cantidad    = isset($data['cantidad']) ? (int)$data['cantidad'] : 1;

if (!$cliente_id || !$plato_id || !$metodo_pago) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Datos incompletos"]);
    exit;
}
if ($cantidad < 1) $cantidad = 1;

// Obtener cliente
$stmt = $conn->prepare("SELECT id, nombre, telefono FROM clientes WHERE id = ?");
if (!$stmt) {
    error_log("cajero.php prepare clientes error: " . $conn->error);
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Error interno"]);
    exit;
}
$stmt->bind_param("i", $cliente_id);
$stmt->execute();
$res = $stmt->get_result();
$cliente = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$cliente) {
    http_response_code(404);
    echo json_encode(["success" => false, "error" => "Cliente no encontrado"]);
    exit;
}

$nombre_cliente = $cliente['nombre'];
$telefono_cliente = $cliente['telefono'];

// Obtener datos del plato desde tabla menu
$stmt = $conn->prepare("SELECT id, nombre, precio_usd FROM menu WHERE id = ?");
if (!$stmt) {
    error_log("cajero.php prepare menu error: " . $conn->error);
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Error interno"]);
    exit;
}
$stmt->bind_param("i", $plato_id);
$stmt->execute();
$res = $stmt->get_result();
$plato = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$plato) {
    http_response_code(404);
    echo json_encode(["success" => false, "error" => "Plato no encontrado"]);
    exit;
}

$plato_nombre = $plato['nombre'];
$plato_precio = (float)$plato['precio_usd'];

// Obtener la última tasa desde tasa_bcv (si existe)
$tasa = null;
try {
    $resTasa = $conn->query("SELECT monto_bs FROM tasa_bcv ORDER BY id DESC LIMIT 1");
    if ($resTasa && $rowT = $resTasa->fetch_assoc()) {
        $tasa = (float)$rowT['monto_bs'];
    }
} catch (Exception $e) {
    error_log("Error leyendo tasa_bcv: " . $e->getMessage());
    $tasa = null;
}

// Calcular precios y total en backend
$plato_precio_bs = ($tasa !== null && $tasa > 0) ? round($plato_precio * $tasa, 2) : null;
$calculated_total = round($plato_precio * $cantidad, 2);
$total = $calculated_total;

$estado = "pendiente";
$fecha = date("Y-m-d H:i:s");

// Usar transacción para mayor seguridad
$conn->begin_transaction();

try {
    if ($plato_precio_bs !== null) {
        // Tipos: s (cliente), s (telefono), s (metodo), i (plato_id), s (plato_nombre),
        // d (plato_precio), i (cantidad), d (total), d (plato_precio_bs), s (estado), s (fecha)
        $stmt = $conn->prepare("INSERT INTO pedidos (cliente, telefono, metodo_pago, plato_id, plato_nombre, plato_precio, cantidad, total, plato_precio_bs, estado, fecha) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) throw new Exception("Prepare insert failed: " . $conn->error);
        $stmt->bind_param("sssisdiddss",
            $nombre_cliente,
            $telefono_cliente,
            $metodo_pago,
            $plato_id,
            $plato_nombre,
            $plato_precio,
            $cantidad,
            $total,
            $plato_precio_bs,
            $estado,
            $fecha
        );
    } else {
        // plato_precio_bs = NULL
        $stmt = $conn->prepare("INSERT INTO pedidos (cliente, telefono, metodo_pago, plato_id, plato_nombre, plato_precio, cantidad, total, plato_precio_bs, estado, fecha) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, ?)");
        if (!$stmt) throw new Exception("Prepare insert null failed: " . $conn->error);
        $stmt->bind_param("sssisdidss",
            $nombre_cliente,
            $telefono_cliente,
            $metodo_pago,
            $plato_id,
            $plato_nombre,
            $plato_precio,
            $cantidad,
            $total,
            $estado,
            $fecha
        );
    }

    if (!$stmt->execute()) {
        throw new Exception("Execute insert failed: " . $stmt->error);
    }

    $insert_id = $stmt->insert_id;
    $stmt->close();

    // Actualizar contador de pedidos en clientes si existe la columna
    $check2 = $conn->query("SHOW COLUMNS FROM clientes LIKE 'pedidos_count'");
    if ($check2 && $check2->num_rows) {
        $upd = $conn->prepare("UPDATE clientes SET pedidos_count = COALESCE(pedidos_count,0) + 1, ultima_compra = ? WHERE id = ?");
        if ($upd) {
            $upd->bind_param("si", $fecha, $cliente_id);
            $upd->execute();
            $upd->close();
        }
    }

    $conn->commit();

    echo json_encode([
        "success" => true,
        "id" => $insert_id,
        "total" => $total,
        "plato_precio_bs" => $plato_precio_bs
    ]);
    exit;
} catch (Exception $e) {
    $conn->rollback();
    error_log("cajero.php transaction error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Error al insertar pedido"]);
    exit;
}
