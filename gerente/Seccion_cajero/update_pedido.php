<?php
// update_pedido.php
ini_set('display_errors', 0);
error_reporting(0);
header("Content-Type: application/json; charset=utf-8");
require_once("../../conexion.php"); // AJUSTA RUTA si hace falta
date_default_timezone_set('America/Caracas');

$input = file_get_contents("php://input");
$data = json_decode($input, true);
if (!is_array($data)) $data = $_POST;

// Campos esperados (algunos opcionales)
$id           = isset($data['id']) ? (int)$data['id'] : null;
$cliente      = isset($data['cliente']) ? trim($data['cliente']) : null;
$telefono     = isset($data['telefono']) ? trim($data['telefono']) : null;
$metodo_pago  = isset($data['metodo_pago']) ? trim($data['metodo_pago']) : null;
$plato_id     = isset($data['plato_id']) && $data['plato_id'] !== "" ? (int)$data['plato_id'] : null;
$plato_nombre = isset($data['plato_nombre']) ? trim($data['plato_nombre']) : null;
$cantidad     = isset($data['cantidad']) ? (int)$data['cantidad'] : null;
$usuario      = isset($data['usuario']) ? trim($data['usuario']) : (isset($data['usuario_mod']) ? trim($data['usuario_mod']) : 'Sistema');

if (!$id) {
    echo json_encode(["success" => false, "error" => "ID de pedido requerido"]);
    exit;
}

// Obtener pedido actual
$stmt = $conn->prepare("SELECT id, cliente, telefono, metodo_pago, plato_id, plato_nombre, plato_precio, cantidad, total FROM pedidos WHERE id = ?");
if (!$stmt) {
    error_log("update_pedido prepare select error: " . $conn->error);
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Error interno"]);
    exit;
}
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
if (!$pedido = $res->fetch_assoc()) {
    echo json_encode(["success" => false, "error" => "Pedido no encontrado"]);
    $stmt->close();
    exit;
}
$stmt->close();

// Valores base (si no se envía el campo, mantener el actual)
$cliente_new     = $cliente !== null ? $cliente : $pedido['cliente'];
$telefono_new    = $telefono !== null ? $telefono : $pedido['telefono'];
$metodo_pago_new = $metodo_pago !== null ? $metodo_pago : $pedido['metodo_pago'];
$cantidad_new    = ($cantidad !== null && $cantidad > 0) ? $cantidad : (int)$pedido['cantidad'];
$plato_id_new    = $plato_id !== null ? $plato_id : (int)$pedido['plato_id'];
$plato_nombre_new= $plato_nombre !== null ? $plato_nombre : $pedido['plato_nombre'];
$plato_precio_new= isset($pedido['plato_precio']) ? (float)$pedido['plato_precio'] : 0.0;

// Si se proporcionó un nuevo plato_id, obtener su precio y nombre desde menu
if ($plato_id !== null) {
    $stmt = $conn->prepare("SELECT id, nombre, precio_usd FROM menu WHERE id = ?");
    if (!$stmt) {
        error_log("update_pedido prepare menu select error: " . $conn->error);
        http_response_code(500);
        echo json_encode(["success" => false, "error" => "Error interno"]);
        exit;
    }
    $stmt->bind_param("i", $plato_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $plato_nombre_new = $row['nombre'];
        $plato_precio_new = (float)$row['precio_usd'];
    } else {
        $stmt->close();
        echo json_encode(["success" => false, "error" => "Plato seleccionado no existe"]);
        exit;
    }
    $stmt->close();
}

// Obtener la última tasa desde tasa_bcv (si existe) para recalcular plato_precio_bs
$tasa = null;
try {
    $resTasa = $conn->query("SELECT monto_bs FROM tasa_bcv ORDER BY id DESC LIMIT 1");
    if ($resTasa && $rowT = $resTasa->fetch_assoc()) {
        $tasa = (float)$rowT['monto_bs'];
    }
} catch (Exception $e) {
    error_log("update_pedido tasa_bcv error: " . $e->getMessage());
    $tasa = null;
}

$plato_precio_bs_new = $tasa !== null ? round($plato_precio_new * $tasa, 2) : null;

// Recalcular total
$total_new = round($plato_precio_new * $cantidad_new, 2);

// Preparar UPDATE dinámico
$fields = [
    "cliente" => $cliente_new,
    "telefono" => $telefono_new,
    "metodo_pago" => $metodo_pago_new,
    "plato_id" => $plato_id_new,
    "plato_nombre" => $plato_nombre_new,
    "plato_precio" => $plato_precio_new,
    "cantidad" => $cantidad_new,
    "total" => $total_new,
    "usuario_mod" => $usuario,
    "fecha_mod" => date("Y-m-d H:i:s")
];

// Si hay tasa disponible, actualizar plato_precio_bs (puede ser NULL)
if ($plato_precio_bs_new !== null) {
    $fields['plato_precio_bs'] = $plato_precio_bs_new;
} else {
    // si prefieres no tocar el valor anterior cuando no hay tasa, comenta la siguiente línea
    $fields['plato_precio_bs'] = null;
}

// Construir SQL dinámico
$set_parts = [];
$types = "";
$values = [];
foreach ($fields as $col => $val) {
    if ($col === 'plato_precio_bs' && $val === null) {
        $set_parts[] = "$col = NULL";
        continue;
    }
    $set_parts[] = "$col = ?";
    if (is_int($val)) {
        $types .= "i";
    } elseif (is_float($val)) {
        $types .= "d";
    } else {
        $types .= "s";
    }
    $values[] = $val;
}
$set_sql = implode(", ", $set_parts);
$sql = "UPDATE pedidos SET $set_sql WHERE id = ?";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log("update_pedido prepare update error: " . $conn->error);
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Error interno al preparar actualización"]);
    exit;
}

// bind dinámico: agregar id al final
$types .= "i";
$values[] = $id;

// bind_param requiere variables por referencia
$bind_names[] = $types;
for ($i=0; $i<count($values); $i++) {
    $bind_name = 'bind' . $i;
    $$bind_name = $values[$i];
    $bind_names[] = &$$bind_name;
}
call_user_func_array([$stmt, 'bind_param'], $bind_names);

if ($stmt->execute()) {
    $stmt->close();
    echo json_encode(["success" => true, "id" => $id, "total" => $total_new, "plato_precio_bs" => $plato_precio_bs_new]);
} else {
    $err = $stmt->error;
    $stmt->close();
    error_log("update_pedido execute error: " . $err);
    echo json_encode(["success" => false, "error" => $err]);
}
