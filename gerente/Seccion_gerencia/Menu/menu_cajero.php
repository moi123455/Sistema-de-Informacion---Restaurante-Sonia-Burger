<?php
// menu_cajero.php - devuelve horario activo y productos activos filtrados por horario
header('Content-Type: application/json; charset=utf-8');
require_once("../../../conexion.php"); // AJUSTA RUTA si tu conexion.php está en otra ubicación

// Base URL dinámico
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$baseUrl = $protocol . $host . "/Restaurante_Sonia_Burger/Restaurante_SB/";

// Ruta física base del proyecto
$projectRoot = realpath(__DIR__ . '/../../../');

// 1) Obtener horario activo desde config (fallback 'dia')
$horario = 'dia';
$resCfg = $conn->query("SELECT value FROM config WHERE `key` = 'menu_horario_activo' LIMIT 1");
if ($resCfg && $rowCfg = $resCfg->fetch_assoc()) {
    $h = trim(strtolower($rowCfg['value']));
    if (in_array($h, ['dia','tarde','noche'])) $horario = $h;
}

// 2) Obtener la última tasa BCV (monto_bs) si existe
$tasa = null;
$tasa_establecida = false;
$resT = $conn->query("SELECT monto_bs FROM tasa_bcv ORDER BY id DESC LIMIT 1");
if ($resT && $rt = $resT->fetch_assoc()) {
    $raw = str_replace(',', '.', trim($rt['monto_bs']));
    $val = floatval($raw);
    if ($val > 0) {
        $tasa = $val;
        $tasa_establecida = true;
    }
}

// 3) Preparar consulta filtrando por horario y estado (normalizamos estado a 'activo')
$stmt = $conn->prepare("SELECT id, nombre, descripcion, categoria, precio_usd, precio_bs, imagen, imagen_blob, imagen_mime, estado, horario FROM menu WHERE LOWER(estado) = 'activo' AND horario = ? ORDER BY categoria, nombre");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'Error interno: '.$conn->error]);
    exit;
}
$stmt->bind_param("s", $horario);
$stmt->execute();
$res = $stmt->get_result();

$rows = [];
while ($r = $res->fetch_assoc()) {
    // Normalizar campos
    $r['precio_usd'] = isset($r['precio_usd']) ? (float)$r['precio_usd'] : 0.0;
    // Si precio_bs en BD es nulo o 0, calcular con tasa si está disponible
    $precio_bs_bd = (isset($r['precio_bs']) && $r['precio_bs'] !== null) ? floatval($r['precio_bs']) : null;
    if (($precio_bs_bd === null || $precio_bs_bd == 0.0) && $tasa_establecida) {
        $r['precio_bs'] = round($r['precio_usd'] * $tasa, 2);
        $r['tasa_establecida'] = true;
    } else {
        $r['precio_bs'] = $precio_bs_bd;
        $r['tasa_establecida'] = $tasa_establecida && $precio_bs_bd !== null;
    }

    $r['descripcion'] = $r['descripcion'] ?? '';
    $r['horario'] = $r['horario'] ?? $horario;

    // Determinar imagen_url (misma lógica que tenías, con fallback)
    $ruta = trim($r['imagen'] ?? '');
    $imagen_url = $baseUrl . "gerente/Seccion_gerencia/Menu/default.jpg";

    if ($ruta !== "") {
        $relativePath = 'gerente/Seccion_gerencia/Menu/' . ltrim($ruta, "/");
        $physicalPath = $projectRoot . '/' . $relativePath;
        if (file_exists($physicalPath)) {
            $imagen_url = $baseUrl . $relativePath;
        } else {
            $physicalPath2 = $projectRoot . '/' . ltrim($ruta, "/");
            if (file_exists($physicalPath2)) {
                $imagen_url = $baseUrl . ltrim($ruta, "/");
            } else {
                if (preg_match('/^https?:\/\//i', $ruta)) {
                    $imagen_url = $ruta;
                }
            }
        }
    } else {
        if (!empty($r['imagen_blob'])) {
            $imagen_url = $baseUrl . "gerente/Seccion_gerencia/Menu/image.php?id=" . intval($r['id']);
        }
    }

    $r['imagen_url'] = $imagen_url;
    $rows[] = $r;
}

// Respuesta incluye la tasa y si está establecida
echo json_encode([
    'success' => true,
    'horario' => $horario,
    'tasa' => $tasa,                 // null o número
    'tasa_establecida' => $tasa_establecida ? true : false,
    'data' => $rows
], JSON_UNESCAPED_UNICODE);
