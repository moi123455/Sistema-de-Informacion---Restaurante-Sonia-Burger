<?php
// cierre_caja.php - crea reporte con caja_num, calcula plato_favorito, BORRA pedidos entregados
// Versión corregida: tipos de bind_param alineados, tasa normalizada, manejo robusto de errores
ini_set('display_errors', 0);
error_reporting(E_ALL);
header("Content-Type: application/json; charset=utf-8");

// localizar conexion.php (ajusta si tu estructura es distinta)
$possible = [
    __DIR__ . "/../../conexion.php",
    __DIR__ . "/../conexion.php",
    __DIR__ . "/conexion.php",
    __DIR__ . "/../../../conexion.php",
    __DIR__ . "/../../../../conexion.php"
];
$found = false;
foreach ($possible as $p) {
    if (file_exists($p)) { require_once $p; $found = true; break; }
}
if (!$found) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "No se encontró conexion.php"]);
    exit;
}

date_default_timezone_set('America/Caracas');

if (!isset($conn) || !$conn) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "BD no disponible"]);
    exit;
}

// leer input (JSON o form)
$input = file_get_contents("php://input");
$data = json_decode($input, true);
if (!is_array($data)) $data = $_POST;
$usuario = isset($data['usuario']) ? trim($data['usuario']) : null;

// sesión (usar id de sesión si existe)
if (session_status() === PHP_SESSION_NONE) session_start();
$sessionUserId = $_SESSION['user_id'] ?? $_SESSION['userId'] ?? null;
$sessionUserRole = $_SESSION['rol'] ?? $_SESSION['user_role'] ?? null;

// si no se pasó usuario en payload, usar nombre de sesión si existe
if (!$usuario) {
    if (!empty($_SESSION['user_name'])) $usuario = $_SESSION['user_name'];
    elseif (!empty($_SESSION['usuario'])) $usuario = $_SESSION['usuario'];
    else $usuario = 'Sistema';
}

// obtener tasa (última)
$tasa = null;
$resT = $conn->query("SELECT monto_bs FROM tasa_bcv ORDER BY id DESC LIMIT 1");
if ($resT && $rT = $resT->fetch_assoc()) $tasa = $rT['monto_bs'];

// obtener pedidos entregados del día
$sql = "SELECT * FROM pedidos WHERE DATE(fecha) = CURDATE() AND LOWER(TRIM(estado)) = 'entregado'";
$res = $conn->query($sql);
$pedidos = [];
$total_usd = 0.0;
$total_bs = 0.0;
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $pedidos[] = $row;
        $total_usd += (float)$row['total'];
        $total_bs += isset($row['plato_precio_bs']) && $row['plato_precio_bs'] !== null ? (float)$row['plato_precio_bs'] : 0.0;
    }
}

if (count($pedidos) === 0) {
    echo json_encode(["success" => false, "error" => "No hay pedidos entregados para archivar"]);
    exit;
}

// calcular plato favorito
$counts = [];
foreach ($pedidos as $p) {
    $nombre = trim($p['plato_nombre'] ?? '');
    $cantidad = isset($p['cantidad']) ? (int)$p['cantidad'] : 1;
    if ($nombre === '') continue;
    if (!isset($counts[$nombre])) $counts[$nombre] = 0;
    $counts[$nombre] += $cantidad;
}
$plato_favorito = '';
$max = 0;
foreach ($counts as $nombre => $c) {
    if ($c > $max) { $max = $c; $plato_favorito = $nombre; }
}

$fecha_reporte = date("Y-m-d H:i:s");
$pedidos_json = json_encode($pedidos, JSON_UNESCAPED_UNICODE);

// Preparar detección de columnas relevantes en tablas usuarios/pedidos
function detect_column($conn, $table, $candidates = []) {
    $res = $conn->query("SHOW COLUMNS FROM {$table}");
    if (!$res) return null;
    $found = [];
    while ($col = $res->fetch_assoc()) {
        $f = strtolower($col['Field']);
        foreach ($candidates as $cand) {
            if ($f === strtolower($cand)) {
                $found[] = $f;
            }
        }
    }
    return $found;
}

// candidatos para contador de cierres y entregados en tabla usuarios
$userCloseCandidates = ['cash_closes_count','cash_closes','cierres','cierres_count'];
$userDeliveredCandidates = ['orders_delivered_count','orders_delivered','entregados','entregados_count'];

// candidatos para campo que indica qué usuario atendió/entregó el pedido
$pedidoUserFieldCandidates = ['user_id','usuario_id','cajero_id','atendido_por','handled_by','user'];

// Contadores por usuario (para incrementar orders_delivered_count)
$deliveredCountsByUser = [];

// intentar detectar campo de "quién atendió" en pedidos
$pedidoCols = [];
$resCols = $conn->query("SHOW COLUMNS FROM pedidos");
if ($resCols) {
    while ($c = $resCols->fetch_assoc()) $pedidoCols[] = strtolower($c['Field']);
}
$pedidoUserField = null;
foreach ($pedidoUserFieldCandidates as $cand) {
    if (in_array(strtolower($cand), $pedidoCols)) { $pedidoUserField = strtolower($cand); break; }
}

// si existe campo de usuario en pedidos, acumular contadores por usuario
if ($pedidoUserField) {
    foreach ($pedidos as $p) {
        $uid = null;
        if (isset($p[$pedidoUserField]) && $p[$pedidoUserField] !== null && $p[$pedidoUserField] !== '') {
            $uid = intval($p[$pedidoUserField]);
        }
        if ($uid && $uid > 0) {
            if (!isset($deliveredCountsByUser[$uid])) $deliveredCountsByUser[$uid] = 0;
            // sumar cantidad si existe, sino 1
            $deliveredCountsByUser[$uid] += isset($p['cantidad']) ? intval($p['cantidad']) : 1;
        }
    }
}

// Transacción: insertar reporte, asignar numero de caja incremental y borrar pedidos
$conn->begin_transaction();
try {
    // calcular numero de caja incremental (max + 1)
    $resNum = $conn->query("SELECT MAX(caja_num) AS maxnum FROM reportes");
    $caja_num = 1;
    if ($resNum && $rNum = $resNum->fetch_assoc()) {
        $caja_num = ($rNum['maxnum'] !== null) ? ((int)$rNum['maxnum'] + 1) : 1;
    }

    // Normalizar valores para bind_param
    $tasa_val = ($tasa !== null && $tasa !== '') ? floatval($tasa) : 0.0;
    $total_usd = floatval($total_usd);
    $total_bs  = floatval($total_bs);
    $caja_num  = intval($caja_num);
    $plato_favorito = (string)($plato_favorito ?? '');

    // insertar reporte (incluye plato_favorito)
    $stmt = $conn->prepare("INSERT INTO reportes (fecha_reporte, usuario, tasa_usd_bs, total_usd, total_bs, pedidos_json, caja_num, plato_favorito) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) throw new Exception("Prepare insert report failed: " . $conn->error);

    // tipos: s s d d d s i s  => "ssdddsis"
    if (!$stmt->bind_param("ssdddsis", $fecha_reporte, $usuario, $tasa_val, $total_usd, $total_bs, $pedidos_json, $caja_num, $plato_favorito)) {
        throw new Exception("bind_param failed: " . $stmt->error);
    }

    if (!$stmt->execute()) throw new Exception("Execute insert report failed: " . $stmt->error);
    $report_id = $stmt->insert_id;
    $stmt->close();

    // BORRAR pedidos entregados (ya guardados en reportes)
    $ids = array_map(function($p){ return (int)$p['id']; }, $pedidos);
    if (!empty($ids)) {
        $ids_list = implode(",", $ids);
        if (!$conn->query("DELETE FROM pedidos WHERE id IN ($ids_list)")) {
            throw new Exception("Error deleting pedidos: " . $conn->error);
        }
    }

    // -------------------------
    // Actualizar contador de cierres del usuario que ejecutó el cierre (si existe columna)
    // -------------------------
    $colToUpdateClose = null;
    $colsU = $conn->query("SHOW COLUMNS FROM usuarios");
    if ($colsU) {
        while ($cu = $colsU->fetch_assoc()) {
            $f = strtolower($cu['Field']);
            if (in_array($f, array_map('strtolower', $userCloseCandidates))) {
                $colToUpdateClose = $f;
                break;
            }
        }
    }
    if ($colToUpdateClose && $sessionUserId) {
        $updSql = "UPDATE usuarios SET {$colToUpdateClose} = COALESCE({$colToUpdateClose},0) + 1 WHERE id = ?";
        $updStmt = $conn->prepare($updSql);
        if ($updStmt) {
            $updStmt->bind_param("i", $sessionUserId);
            if (!$updStmt->execute()) {
                throw new Exception("Error actualizando contador de cierres (usuario): " . $updStmt->error);
            }
            $updStmt->close();
        } else {
            throw new Exception("Prepare update usuario (cierres) failed: " . $conn->error);
        }
    } else {
        if (!$colToUpdateClose) error_log("cierre_caja: no se detectó columna de contador de cierres en tabla usuarios");
    }

    // -------------------------
    // Actualizar contador de entregados por cajero(s) si detectamos columna en usuarios y campo en pedidos
    // -------------------------
    $colToUpdateDelivered = null;
    $colsU2 = $conn->query("SHOW COLUMNS FROM usuarios");
    if ($colsU2) {
        while ($cu = $colsU2->fetch_assoc()) {
            $f = strtolower($cu['Field']);
            if (in_array($f, array_map('strtolower', $userDeliveredCandidates))) {
                $colToUpdateDelivered = $f;
                break;
            }
        }
    }

    if ($colToUpdateDelivered && !empty($deliveredCountsByUser)) {
        $updSql = "UPDATE usuarios SET {$colToUpdateDelivered} = COALESCE({$colToUpdateDelivered},0) + ? WHERE id = ?";
        $updStmt = $conn->prepare($updSql);
        if (!$updStmt) throw new Exception("Prepare update usuarios entregados failed: " . $conn->error);
        foreach ($deliveredCountsByUser as $uid => $count) {
            $updStmt->bind_param("ii", $count, $uid);
            if (!$updStmt->execute()) {
                throw new Exception("Error actualizando entregados para usuario {$uid}: " . $updStmt->error);
            }
        }
        $updStmt->close();
    } else {
        if (!$colToUpdateDelivered) error_log("cierre_caja: no se detectó columna de entregados en tabla usuarios");
    }

    $conn->commit();

    echo json_encode([
        "success" => true,
        "report_id" => $report_id,
        "count" => count($pedidos),
        "total_usd" => $total_usd,
        "total_bs" => $total_bs,
        "caja_num" => $caja_num,
        "plato_favorito" => $plato_favorito,
        "contador_cierres_actualizado" => $colToUpdateClose ?? null,
        "contador_entregados_actualizado" => $colToUpdateDelivered ?? null
    ]);
    exit;
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    error_log("cierre_caja exception: " . $e->getMessage());
    echo json_encode(["success" => false, "error" => "Error interno al cerrar caja", "exception" => $e->getMessage()]);
    exit;
}
