<?php
// cierre_gastos.php - cierre basado en inventario (versión robusta)
// Marca inventario.activo = 0 para los items procesados y devuelve ids procesados
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

// localizar conexion.php
$possible = [
    __DIR__ . '/conexion.php',
    __DIR__ . '/../conexion.php',
    __DIR__ . '/../../conexion.php',
    __DIR__ . '/../../../conexion.php',
    __DIR__ . '/../../../../conexion.php'
];
$found = false;
foreach ($possible as $p) {
    if (file_exists($p)) { require_once $p; $found = true; break; }
}
if (!$found) { http_response_code(500); echo json_encode(['success'=>false,'error'=>'No se encontró conexion.php']); exit; }

// sesión y permisos
if (session_status() === PHP_SESSION_NONE) session_start();
$user_id   = $_SESSION['user_id'] ?? $_SESSION['userId'] ?? null;
$user_role = $_SESSION['rol'] ?? $_SESSION['user_role'] ?? '';
if (!$user_id || strtolower((string)$user_role) !== 'gerente') {
    http_response_code(403);
    echo json_encode(['success'=>false,'error'=>'Acceso no autorizado']);
    exit;
}

// rango opcional: si se pasan 'desde' y 'hasta' se usan como fechas (YYYY-MM-DD o datetime)
// por defecto se procesa el día actual (DATE)
$desde = $_POST['desde'] ?? null;
$hasta = $_POST['hasta'] ?? null;
if ($desde && $hasta) {
    // normalizar a DATETIME si vienen solo fechas
    $fecha_inicio = (strlen($desde) === 10) ? ($desde . ' 00:00:00') : $desde;
    $fecha_fin    = (strlen($hasta) === 10) ? ($hasta . ' 23:59:59') : $hasta;
    // también permitimos que el usuario pase rangos con hora
} else {
    // procesar todo el día actual (evita problemas de zona horaria)
    $today = date('Y-m-d');
    $fecha_inicio = $today . ' 00:00:00';
    $fecha_fin    = $today . ' 23:59:59';
}

$conn->begin_transaction();
try {
    // Seleccionar inventario activo en rango (usar BETWEEN con DATETIME)
    $sql = "SELECT * FROM inventario WHERE fecha BETWEEN ? AND ? AND activo = 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("Prepare inventario failed: " . $conn->error);
    $stmt->bind_param("ss", $fecha_inicio, $fecha_fin);
    $stmt->execute();
    $res = $stmt->get_result();

    $items = [];
    $total_usd = 0.0;
    $total_bs = 0.0;
    $ids = [];

    // obtener última tasa_bcv
    $resT = $conn->query("SELECT monto_bs FROM tasa_bcv ORDER BY id DESC LIMIT 1");
    $tasa_bcv = 0;
    if ($resT && $rt = $resT->fetch_assoc()) $tasa_bcv = floatval(str_replace(',', '.', $rt['monto_bs']));

    while ($r = $res->fetch_assoc()) {
        $it = [];
        $it['id'] = intval($r['id']);
        $it['nombre'] = $r['nombre'] ?? 'Insumo';
        $it['cantidad'] = isset($r['cantidad']) ? floatval($r['cantidad']) : 1;
        $it['unidad'] = $r['unidad'] ?? '';
        $it['costo_unitario_usd'] = isset($r['costo_usd']) ? floatval($r['costo_usd']) : 0;
        $it['total_usd'] = $it['cantidad'] * $it['costo_unitario_usd'];

        if (isset($r['total_bs']) && $r['total_bs'] !== null && $r['total_bs'] !== '') {
            $it['total_bs'] = floatval($r['total_bs']);
        } elseif (isset($r['tasa']) && floatval($r['tasa']) > 0) {
            $it['total_bs'] = round($it['total_usd'] * floatval($r['tasa']), 2);
        } else {
            $it['total_bs'] = round($it['total_usd'] * $tasa_bcv, 2);
        }

        // tasa_cobrada preferida
        $it['tasa_cobrada'] = (isset($r['tasa']) && floatval($r['tasa']) > 0) ? floatval($r['tasa']) : floatval($tasa_bcv);

        $it['proveedor'] = $r['proveedor'] ?? null;
        $it['fecha_hora'] = $r['fecha'] ?? null;

        $items[] = $it;
        $total_usd += $it['total_usd'];
        $total_bs += $it['total_bs'];
        $ids[] = $it['id'];
    }
    $stmt->close();

    // Si no hay items, abortar con mensaje claro
    if (count($ids) === 0) {
        $conn->rollback();
        echo json_encode(['success'=>false,'error'=>'No se encontraron insumos activos en el rango especificado','ids_found'=>[]]);
        exit;
    }

    // crear título incremental (por fecha)
    $date_label = date('Y-m-d');
    $countRes = $conn->query("SELECT COUNT(*) AS cnt FROM reportes_gastos WHERE DATE(created_at) = '{$date_label}'");
    $cntRow2 = $countRes ? $countRes->fetch_assoc() : null;
    $n = ($cntRow2 && isset($cntRow2['cnt'])) ? intval($cntRow2['cnt']) + 1 : 1;
    $titulo = "Gastos {$n} - {$date_label}";

    // insertar cabecera
    $ins = $conn->prepare("INSERT INTO reportes_gastos (titulo, total_usd, total_bs, user_id) VALUES (?, ?, ?, ?)");
    if (!$ins) throw new Exception("Prepare insert reporte failed: " . $conn->error);
    $ins->bind_param("sddi", $titulo, $total_usd, $total_bs, $user_id);
    if (!$ins->execute()) throw new Exception("Error insert reporte: " . $ins->error);
    $reporte_id = $ins->insert_id;
    $ins->close();

    // detectar columnas en reportes_gastos_items
    $colsRes = $conn->query("SHOW COLUMNS FROM reportes_gastos_items");
    $cols = [];
    if ($colsRes) {
        while ($c = $colsRes->fetch_assoc()) $cols[] = strtolower($c['Field']);
    }
    $hasInventarioId = in_array('inventario_id', $cols);
    $hasGastoId = in_array('gasto_id', $cols);
    $hasTotalBsItem = in_array('total_bs', $cols);
    $hasTasaCobrada = in_array('tasa_cobrada', $cols) || in_array('tasa', $cols);

    // insertar items adaptándose a columnas (incluye tasa_cobrada si existe)
    $itemsInserted = 0;
    foreach ($items as $it) {
        $nombreEsc = $conn->real_escape_string($it['nombre']);
        $unidadEsc = $conn->real_escape_string($it['unidad']);
        $provEsc = $conn->real_escape_string($it['proveedor'] ?? '');
        $fhEsc = $it['fecha_hora'] !== null ? "'" . $conn->real_escape_string($it['fecha_hora']) . "'" : "NULL";
        $tBsVal = $it['total_bs'];
        $tasaVal = $it['tasa_cobrada'];

        if ($hasInventarioId) {
            $colsInsert = "reporte_id, inventario_id, nombre, cantidad, unidad, costo_unitario_usd, total_usd";
            if ($hasTotalBsItem) $colsInsert .= ", total_bs";
            if ($hasTasaCobrada) $colsInsert .= ", tasa_cobrada";
            $colsInsert .= ", proveedor, fecha_hora";

            $vals = "{$reporte_id}, {$it['id']}, '{$nombreEsc}', {$it['cantidad']}, '{$unidadEsc}', {$it['costo_unitario_usd']}, {$it['total_usd']}";
            if ($hasTotalBsItem) $vals .= ", {$tBsVal}";
            if ($hasTasaCobrada) $vals .= ", {$tasaVal}";
            $vals .= ", '{$provEsc}', {$fhEsc}";

            $sqlItem = "INSERT INTO reportes_gastos_items ({$colsInsert}) VALUES ({$vals})";
            if (!$conn->query($sqlItem)) throw new Exception("Error insert item: " . $conn->error);
        } elseif ($hasGastoId) {
            $colsInsert = "reporte_id, gasto_id, nombre, cantidad, unidad, costo_unitario_usd, total_usd";
            if ($hasTotalBsItem) $colsInsert .= ", total_bs";
            if ($hasTasaCobrada) $colsInsert .= ", tasa_cobrada";
            $colsInsert .= ", proveedor, fecha_hora";

            $vals = "{$reporte_id}, NULL, '{$nombreEsc}', {$it['cantidad']}, '{$unidadEsc}', {$it['costo_unitario_usd']}, {$it['total_usd']}";
            if ($hasTotalBsItem) $vals .= ", {$tBsVal}";
            if ($hasTasaCobrada) $vals .= ", {$tasaVal}";
            $vals .= ", '{$provEsc}', {$fhEsc}";

            $sqlItem = "INSERT INTO reportes_gastos_items ({$colsInsert}) VALUES ({$vals})";
            if (!$conn->query($sqlItem)) throw new Exception("Error insert item: " . $conn->error);
        } else {
            $sqlItem = "INSERT INTO reportes_gastos_items (reporte_id, nombre, cantidad, unidad, costo_unitario_usd, total_usd" .
                       ($hasTotalBsItem ? ", total_bs" : "") .
                       ($hasTasaCobrada ? ", tasa_cobrada" : "") .
                       ", proveedor, fecha_hora)
                        VALUES ({$reporte_id}, '{$nombreEsc}', {$it['cantidad']}, '{$unidadEsc}', {$it['costo_unitario_usd']}, {$it['total_usd']}" .
                       ($hasTotalBsItem ? ", {$tBsVal}" : "") .
                       ($hasTasaCobrada ? ", {$tasaVal}" : "") .
                       ", '{$provEsc}', {$fhEsc})";
            if (!$conn->query($sqlItem)) throw new Exception("Error insert item: " . $conn->error);
        }
        $itemsInserted++;
    }

    // BORRAR inventario procesado (eliminar filas)
if (count($ids) > 0) {
    $idsList = implode(',', array_map('intval', $ids));
    $delSql = "DELETE FROM inventario WHERE id IN ({$idsList})";
    if (!$conn->query($delSql)) {
        throw new Exception("Error borrando inventario: " . $conn->error);
    }
    $deleted = $conn->affected_rows;
    if ($deleted <= 0) {
        throw new Exception("No se eliminaron filas en inventario (affected_rows=0). IDs: {$idsList}");
    }
} else {
    // no hay ids -> nada que borrar
}


    // (opcional) actualizar contador de cierres del usuario - detectar columna
    $colToUpdate = null;
    $colsU = $conn->query("SHOW COLUMNS FROM usuarios");
    if ($colsU) {
        while ($cu = $colsU->fetch_assoc()) {
            $f = strtolower($cu['Field']);
            if (in_array($f, ['cash_closes_count','cash_closes','cierres','cierres_count'])) {
                $colToUpdate = $f;
                break;
            }
        }
    }
    if ($colToUpdate) {
        $updStmt = $conn->prepare("UPDATE usuarios SET {$colToUpdate} = COALESCE({$colToUpdate},0) + 1 WHERE id = ?");
        if ($updStmt) {
            $updStmt->bind_param("i", $user_id);
            if (!$updStmt->execute()) throw new Exception("Error actualizando contador de cierres: " . $updStmt->error);
            $updStmt->close();
        }
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'reporte_id' => $reporte_id,
        'items_count' => $itemsInserted,
        'ids_found' => $ids,
        'ids_updated' => array_map('intval', explode(',', $idsList)),
        'total_usd' => $total_usd,
        'total_bs' => $total_bs,
        'contador_actualizado' => $colToUpdate ?? null
    ]);
    exit;

} catch (Exception $e) {
    @$conn->rollback();
    error_log("cierre_gastos error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error al generar cierre', 'exception' => $e->getMessage()]);
    exit;
}
