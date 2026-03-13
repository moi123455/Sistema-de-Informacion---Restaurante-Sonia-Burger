<?php
// totales_periodo.php
header('Content-Type: application/json; charset=utf-8');
require_once 'F:/wamp64/www/Restaurante_Sonia_Burger/Restaurante_SB/conexion.php';

if (session_status() === PHP_SESSION_NONE) session_start();
// compatibilidad con claves de sesión
if (!isset($_SESSION['user_role']) && isset($_SESSION['rol'])) $_SESSION['user_role'] = $_SESSION['rol'];
$user_role = $_SESSION['user_role'] ?? $_SESSION['rol'] ?? '';
if (strtolower((string)$user_role) !== 'gerente') {
    http_response_code(403);
    echo json_encode(['success'=>false,'error'=>'Acceso denegado']);
    exit;
}

$periodo = $_GET['periodo'] ?? 'day';

try {
    // Por defecto condiciones simples (servidor timezone)
    if ($periodo === 'day') {
        $cond_g = "DATE(created_at) = CURDATE()";
        $cond_p = "DATE(created_at) = CURDATE()";
    } elseif ($periodo === 'week') {
        $cond_g = "created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        $cond_p = "created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    } else { // month
        $cond_g = "YEAR(created_at)=YEAR(CURDATE()) AND MONTH(created_at)=MONTH(CURDATE())";
        $cond_p = "YEAR(created_at)=YEAR(CURDATE()) AND MONTH(created_at)=MONTH(CURDATE())";
    }

    $qg = "SELECT COALESCE(SUM(total_usd),0) AS usd, COALESCE(SUM(total_bs),0) AS bs FROM reportes WHERE {$cond_g}";
    $qp = "SELECT COALESCE(SUM(total_usd),0) AS usd, COALESCE(SUM(total_bs),0) AS bs FROM reportes_gastos WHERE {$cond_p}";

    $rg = $conn->query($qg);
    if (!$rg) throw new Exception("Query ganancias failed: " . $conn->error);
    $rg = $rg->fetch_assoc();

    $rp = $conn->query($qp);
    if (!$rp) throw new Exception("Query perdidas failed: " . $conn->error);
    $rp = $rp->fetch_assoc();

    echo json_encode([
        'success' => true,
        'ganancias' => ['usd' => floatval($rg['usd']), 'bs' => floatval($rg['bs'])],
        'perdidas'  => ['usd' => floatval($rp['usd']), 'bs' => floatval($rp['bs'])]
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    error_log("totales_periodo.php error: " . $e->getMessage());
    echo json_encode(['success'=>false,'error'=>'Error interno']);
}
