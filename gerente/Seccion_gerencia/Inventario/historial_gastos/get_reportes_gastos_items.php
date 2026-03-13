<?php
// get_reportes_gastos_items.php - devuelve items de un reporte por id (incluye tasa si existe)
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

// Intentar localizar conexion.php desde varias ubicaciones relativas
$possible = [
    __DIR__ . '/conexion.php',
    __DIR__ . '/../conexion.php',
    __DIR__ . '/../../conexion.php',
    __DIR__ . '/../../../conexion.php',
    __DIR__ . '/../../../../conexion.php'
];

$found = false;
foreach ($possible as $p) {
    if (file_exists($p)) {
        require_once $p;
        $found = true;
        break;
    }
}

if (!$found) {
    http_response_code(500);
    echo json_encode(['error' => 'No se encontró conexion.php. Ajusta la ruta.']);
    exit;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    echo json_encode([]);
    exit;
}

// Detectar columnas de la tabla reportes_gastos_items
$colsRes = $conn->query("SHOW COLUMNS FROM reportes_gastos_items");
$cols = [];
if ($colsRes) {
    while ($c = $colsRes->fetch_assoc()) {
        $cols[] = strtolower($c['Field']);
    }
}

// Lista de nombres posibles para la tasa impuesta por gerente
$tasaCandidates = ['tasa', 'tasa_cobrada', 'tasa_cobrado', 'tasa_usd', 'tasa_cobrada_usd', 'tasa_cobrada_bs'];

// Buscar el primer candidato presente en la tabla
$tasaColumn = null;
foreach ($tasaCandidates as $cand) {
    if (in_array(strtolower($cand), $cols)) {
        $tasaColumn = $cand;
        break;
    }
}

// Construir SELECT incluyendo la columna de tasa si existe (alias 'tasa')
$selectFields = "gasto_id, nombre, cantidad, unidad, costo_unitario_usd, total_usd, total_bs, proveedor, fecha_hora";
if ($tasaColumn) {
    // proteger nombre de columna (no user input) y aliasarlo como 'tasa'
    $selectFields .= ", {$tasaColumn} AS tasa";
}

$stmt = $conn->prepare("SELECT {$selectFields} FROM reportes_gastos_items WHERE reporte_id = ? ORDER BY fecha_hora ASC, id ASC");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Error preparando consulta: ' . $conn->error]);
    exit;
}
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$items = [];
while ($r = $res->fetch_assoc()) $items[] = $r;
$stmt->close();

echo json_encode($items);
