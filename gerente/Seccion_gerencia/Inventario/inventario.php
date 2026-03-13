<?php
// inventario.php - versión que guarda tasa y total_bs en inventario (si existen columnas)
// y devuelve la fila insertada en la respuesta POST
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);

header("Content-Type: application/json; charset=utf-8");
// Ajusta CORS según tu entorno
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

$host = "localhost";
$user = "root";
$pass = "";
$db   = "restaurante_sb";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "Conexión fallida"]);
    exit;
}

function sanitize_filename($filename) {
    return preg_replace("/[^A-Za-z0-9\-\_\.]/", '_', $filename);
}

function respuesta($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
if ($method === "OPTIONS") {
    respuesta(["ok" => true]);
}

switch ($method) {
    case "GET":
        if (isset($_GET['id']) && is_numeric($_GET['id'])) {
            $id = intval($_GET['id']);
            $stmt = $conn->prepare("SELECT * FROM inventario WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res->fetch_assoc();
            $stmt->close();
            if ($row) respuesta($row);
            respuesta(["error" => "Registro no encontrado"], 404);
        }

        $sql = "SELECT * FROM inventario ORDER BY fecha DESC";
        $result = $conn->query($sql);
        $insumos = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) $insumos[] = $row;
        }
        respuesta($insumos);
        break;

    case "POST":
        // Leer campos básicos
        $nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : null;
        $cantidad = isset($_POST['cantidad']) ? floatval($_POST['cantidad']) : null;
        $unidad = isset($_POST['unidad']) ? trim($_POST['unidad']) : null;
        $costo_usd = isset($_POST['costo_usd']) ? floatval($_POST['costo_usd']) : null;
        $proveedor = isset($_POST['proveedor']) ? trim($_POST['proveedor']) : null;
        $fecha = date("Y-m-d H:i:s");
        $imagen_path = null;

        // Leer tasa y total_bs enviados desde frontend (opcional)
        $tasa_post = isset($_POST['tasa']) ? (float) str_replace(',', '.', $_POST['tasa']) : null;
        $total_bs_post = isset($_POST['total_bs']) ? (float) str_replace(',', '.', $_POST['total_bs']) : null;

        if (!$nombre || $cantidad === null || !$unidad || $costo_usd === null) {
            respuesta(["error" => "Faltan campos obligatorios (nombre, cantidad, unidad, costo_usd)"], 400);
        }

        // Manejo de imagen (opcional)
        if (!empty($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
            $uploadsDir = __DIR__ . DIRECTORY_SEPARATOR . "uploads";
            if (!is_dir($uploadsDir)) mkdir($uploadsDir, 0755, true);

            $tmpName = $_FILES['imagen']['tmp_name'];
            $origName = basename($_FILES['imagen']['name']);
            $safeName = time() . "_" . sanitize_filename($origName);
            $targetPath = $uploadsDir . DIRECTORY_SEPARATOR . $safeName;

            if (!move_uploaded_file($tmpName, $targetPath)) {
                respuesta(["error" => "No se pudo mover el archivo subido", "file_error" => $_FILES['imagen']['error']], 500);
            }
            $imagen_path = "uploads/" . $safeName;
        }

        // Detectar columnas en inventario
        $hasImagenCol = false;
        $hasTasaCol = false;
        $hasTotalBsCol = false;
        $resColsInv = $conn->query("SHOW COLUMNS FROM inventario");
        if ($resColsInv) {
            while ($c = $resColsInv->fetch_assoc()) {
                $f = strtolower($c['Field']);
                if ($f === 'imagen') $hasImagenCol = true;
                if ($f === 'tasa') $hasTasaCol = true;
                if ($f === 'total_bs') $hasTotalBsCol = true;
            }
        }

        // Construir INSERT dinámico para inventario
        $cols = ["nombre","cantidad","unidad","costo_usd","proveedor","fecha","activo"];
        $placeholders = array_fill(0, count($cols), '?');
        $types = "sdsdssi"; // nombre(s), cantidad(d), unidad(s), costo_usd(d), proveedor(s), fecha(s), activo(i)
        $values = [$nombre, $cantidad, $unidad, $costo_usd, $proveedor, $fecha, 1];

        if ($hasImagenCol) {
            $cols[] = "imagen";
            $placeholders[] = "?";
            $types .= "s";
            $values[] = $imagen_path;
        }
        if ($hasTasaCol) {
            $cols[] = "tasa";
            $placeholders[] = "?";
            $types .= "d";
            $values[] = ($tasa_post !== null ? $tasa_post : null);
        }
        if ($hasTotalBsCol) {
            $cols[] = "total_bs";
            $placeholders[] = "?";
            $types .= "d";
            $values[] = ($total_bs_post !== null ? $total_bs_post : null);
        }

        $sqlInsert = "INSERT INTO inventario (" . implode(", ", $cols) . ") VALUES (" . implode(", ", $placeholders) . ")";
        $stmt = $conn->prepare($sqlInsert);
        if (!$stmt) respuesta(["error" => "Error preparando INSERT inventario: " . $conn->error], 500);

        // bind dinámico
        $bindParams = [];
        $bindParams[] = $types;
        // bind_param requires references
        foreach ($values as $k => $v) $bindParams[] = &$values[$k];
        call_user_func_array([$stmt, 'bind_param'], $bindParams);

        if (!$stmt->execute()) {
            respuesta(["error" => "Error ejecutando INSERT inventario: " . $stmt->error], 500);
        }
        $nuevoId = $stmt->insert_id;
        $stmt->close();

        // Insertar en gastos (si existe tabla gastos) y preferir enviar referencia_id
        $monto_usd = $cantidad * $costo_usd;
        $monto_bs = null;
        if ($total_bs_post !== null && $total_bs_post > 0) {
            $monto_bs = round($total_bs_post, 2);
        } else {
            $resT = $conn->query("SELECT monto_bs FROM tasa_bcv ORDER BY id DESC LIMIT 1");
            if ($resT && $rt = $resT->fetch_assoc()) {
                $t = floatval(str_replace(',', '.', $rt['monto_bs']));
                if ($t > 0) $monto_bs = round($monto_usd * $t, 2);
            }
        }

        $hasGastos = (bool)$conn->query("SHOW TABLES LIKE 'gastos'")->num_rows;
        if ($hasGastos) {
            $colsRes = $conn->query("SHOW COLUMNS FROM gastos");
            $colsG = [];
            while ($c = $colsRes->fetch_assoc()) $colsG[] = strtolower($c['Field']);

            if (in_array('monto_usd', $colsG) && in_array('monto_bs', $colsG) && in_array('referencia_id', $colsG)) {
                $stmtG = $conn->prepare("INSERT INTO gastos (concepto, monto_usd, monto_bs, fecha, referencia_id) VALUES (?, ?, ?, ?, ?)");
                if ($stmtG) {
                    $concepto = "Compra de " . $nombre;
                    $refId = $nuevoId;
                    $stmtG->bind_param("sddsi", $concepto, $monto_usd, $monto_bs, $fecha, $refId);
                    $stmtG->execute();
                    $stmtG->close();
                }
            } else {
                // fallback
                if (in_array('monto_bs', $colsG) && in_array('monto', $colsG)) {
                    $stmtG = $conn->prepare("INSERT INTO gastos (concepto, monto, monto_bs, fecha) VALUES (?, ?, ?, ?)");
                    if ($stmtG) {
                        $concepto = "Compra de " . $nombre;
                        $stmtG->bind_param("sdds", $concepto, $monto_usd, $monto_bs, $fecha);
                        $stmtG->execute();
                        $stmtG->close();
                    }
                } else {
                    $stmtG = $conn->prepare("INSERT INTO gastos (concepto, monto, fecha) VALUES (?, ?, ?)");
                    if ($stmtG) {
                        $concepto = "Compra de " . $nombre;
                        $stmtG->bind_param("sds", $concepto, $monto_usd, $fecha);
                        $stmtG->execute();
                        $stmtG->close();
                    }
                }
            }
        }

        // Recuperar fila insertada para devolverla al frontend (incluye tasa/total_bs si existen)
        $stmtGet = $conn->prepare("SELECT * FROM inventario WHERE id = ? LIMIT 1");
        if ($stmtGet) {
            $stmtGet->bind_param("i", $nuevoId);
            $stmtGet->execute();
            $resGet = $stmtGet->get_result();
            $rowInserted = $resGet ? $resGet->fetch_assoc() : null;
            $stmtGet->close();
            respuesta(["success" => true, "id" => $nuevoId, "row" => $rowInserted]);
        } else {
            respuesta(["success" => true, "id" => $nuevoId]);
        }
        break;

    case "PUT":
        $data = json_decode(file_get_contents("php://input"), true);
        if (!is_array($data) || !isset($data['id'])) respuesta(["error" => "Datos inválidos"], 400);

        $id = intval($data['id']);
        $nombre = isset($data['nombre']) ? trim($data['nombre']) : null;
        $cantidad = isset($data['cantidad']) ? floatval($data['cantidad']) : null;
        $unidad = isset($data['unidad']) ? trim($data['unidad']) : null;
        $costo_usd = isset($data['costo_usd']) ? floatval($data['costo_usd']) : null;
        $proveedor = isset($data['proveedor']) ? trim($data['proveedor']) : null;
        $activo = isset($data['activo']) ? intval($data['activo']) : 1;
        $fecha = date("Y-m-d H:i:s");

        $stmt = $conn->prepare("UPDATE inventario SET nombre = ?, cantidad = ?, unidad = ?, costo_usd = ?, proveedor = ?, fecha = ?, activo = ? WHERE id = ?");
        if ($stmt === false) respuesta(["error" => "Error preparando actualización: " . $conn->error], 500);

        $stmt->bind_param("sdsdssii", $nombre, $cantidad, $unidad, $costo_usd, $proveedor, $fecha, $activo, $id);

        if ($stmt->execute()) {
            $stmt->close();
            respuesta(["success" => true]);
        } else {
            respuesta(["error" => "Error al actualizar: " . $stmt->error], 500);
        }
        break;

    case "DELETE":
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if ($id <= 0) respuesta(["error" => "ID inválido"], 400);

        $stmtSel = $conn->prepare("SELECT imagen FROM inventario WHERE id = ?");
        $stmtSel->bind_param("i", $id);
        $stmtSel->execute();
        $resSel = $stmtSel->get_result();
        $row = $resSel->fetch_assoc();
        $stmtSel->close();

        $imagenAEliminar = ($row && !empty($row['imagen'])) ? $row['imagen'] : null;

        $stmt = $conn->prepare("DELETE FROM inventario WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $stmt->close();
            if ($imagenAEliminar) {
                $possiblePath = __DIR__ . DIRECTORY_SEPARATOR . $imagenAEliminar;
                $uploadsDir = realpath(__DIR__ . DIRECTORY_SEPARATOR . "uploads");
                $realPath = realpath($possiblePath);
                if ($realPath && $uploadsDir && strpos($realPath, $uploadsDir) === 0) {
                    @unlink($realPath);
                }
            }
            respuesta(["success" => true]);
        } else {
            respuesta(["error" => "Error al eliminar: " . $stmt->error], 500);
        }
        break;

    default:
        respuesta(["error" => "Método no soportado"], 405);
        break;
}

$conn->close();
