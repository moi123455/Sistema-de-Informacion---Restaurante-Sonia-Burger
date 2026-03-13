<?php
// summary.php - devuelve ganancias y perdidas y totales por periodos
header('Content-Type: application/json; charset=utf-8');
require_once 'F:/wamp64/www/Restaurante_Sonia_Burger/Restaurante_SB/conexion.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Normalizar claves de sesión
if (!isset($_SESSION['user_role']) && isset($_SESSION['rol'])) $_SESSION['user_role'] = $_SESSION['rol'];
if (!isset($_SESSION['user_name']) && isset($_SESSION['usuario'])) $_SESSION['user_name'] = $_SESSION['usuario'];

$user_role = $_SESSION['user_role'] ?? $_SESSION['rol'] ?? '';
if (strtolower((string)$user_role) !== 'gerente') {
    http_response_code(403);
    echo json_encode(['success'=>false,'error'=>'Acceso denegado']);
    exit;
}

// Rango opcional (si se pasan desde/hasta)
$desde = $_GET['desde'] ?? null;
$hasta = $_GET['hasta'] ?? null;
if (!$desde || !$hasta) {
  $desde = date('Y-m-d 00:00:00');
  $hasta = date('Y-m-d 23:59:59');
}

try {
  // Ganancias en rango (para listado)
  $stmt = $conn->prepare("SELECT id, caja_num, usuario, tasa_usd_bs AS tasa, total_usd, total_bs, created_at, pedidos_json FROM reportes WHERE created_at BETWEEN ? AND ? ORDER BY created_at DESC");
  $stmt->bind_param("ss",$desde,$hasta); $stmt->execute(); $res = $stmt->get_result();
  $ganancias = []; while($r=$res->fetch_assoc()) $ganancias[]=$r; $stmt->close();

  // Perdidas en rango (para listado)
  $stmt2 = $conn->prepare("SELECT id, titulo, user_id, total_usd, total_bs, created_at, notas FROM reportes_gastos WHERE created_at BETWEEN ? AND ? ORDER BY created_at DESC");
  $stmt2->bind_param("ss",$desde,$hasta); $stmt2->execute(); $res2 = $stmt2->get_result();
  $perdidas = []; while($r=$res2->fetch_assoc()) $perdidas[]=$r; $stmt2->close();

  // Subtotales en rango
  $stmt3 = $conn->prepare("SELECT COALESCE(SUM(total_usd),0) AS usd, COALESCE(SUM(total_bs),0) AS bs FROM reportes WHERE created_at BETWEEN ? AND ?");
  $stmt3->bind_param("ss",$desde,$hasta); $stmt3->execute(); $s1 = $stmt3->get_result()->fetch_assoc(); $stmt3->close();

  $stmt4 = $conn->prepare("SELECT COALESCE(SUM(total_usd),0) AS usd, COALESCE(SUM(total_bs),0) AS bs FROM reportes_gastos WHERE created_at BETWEEN ? AND ?");
  $stmt4->bind_param("ss",$desde,$hasta); $stmt4->execute(); $s2 = $stmt4->get_result()->fetch_assoc(); $stmt4->close();

  // Totales por periodos: hoy, semana (7 días), mes actual
  $periods = [];

  // Hoy
  $qg_today = "SELECT COALESCE(SUM(total_usd),0) AS usd, COALESCE(SUM(total_bs),0) AS bs FROM reportes WHERE DATE(created_at) = CURDATE()";
  $qp_today = "SELECT COALESCE(SUM(total_usd),0) AS usd, COALESCE(SUM(total_bs),0) AS bs FROM reportes_gastos WHERE DATE(created_at) = CURDATE()";
  $tg = $conn->query($qg_today)->fetch_assoc();
  $tp = $conn->query($qp_today)->fetch_assoc();

  // Semana (últimos 7 días)
  $qg_week = "SELECT COALESCE(SUM(total_usd),0) AS usd, COALESCE(SUM(total_bs),0) AS bs FROM reportes WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
  $qp_week = "SELECT COALESCE(SUM(total_usd),0) AS usd, COALESCE(SUM(total_bs),0) AS bs FROM reportes_gastos WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
  $wg = $conn->query($qg_week)->fetch_assoc();
  $wp = $conn->query($qp_week)->fetch_assoc();

  // Mes actual
  $qg_month = "SELECT COALESCE(SUM(total_usd),0) AS usd, COALESCE(SUM(total_bs),0) AS bs FROM reportes WHERE YEAR(created_at)=YEAR(CURDATE()) AND MONTH(created_at)=MONTH(CURDATE())";
  $qp_month = "SELECT COALESCE(SUM(total_usd),0) AS usd, COALESCE(SUM(total_bs),0) AS bs FROM reportes_gastos WHERE YEAR(created_at)=YEAR(CURDATE()) AND MONTH(created_at)=MONTH(CURDATE())";
  $mg = $conn->query($qg_month)->fetch_assoc();
  $mp = $conn->query($qp_month)->fetch_assoc();

  // También devolver listas de cierres de hoy/semana/mes para mostrar tarjetas
  $list_today_g = []; $list_today_p = [];
  $resg = $conn->query("SELECT id, caja_num, usuario, tasa_usd_bs AS tasa, total_usd, total_bs, created_at, pedidos_json FROM reportes WHERE DATE(created_at) = CURDATE() ORDER BY created_at DESC");
  while($r=$resg->fetch_assoc()) $list_today_g[]=$r;
  $resp = $conn->query("SELECT id, titulo, user_id, total_usd, total_bs, created_at, notas FROM reportes_gastos WHERE DATE(created_at) = CURDATE() ORDER BY created_at DESC");
  while($r=$resp->fetch_assoc()) $list_today_p[]=$r;

  $periods['today'] = ['ganancias'=>$tg,'perdidas'=>$tp,'list_ganancias'=>$list_today_g,'list_perdidas'=>$list_today_p];
  $periods['week']  = ['ganancias'=>$wg,'perdidas'=>$wp];
  $periods['month'] = ['ganancias'=>$mg,'perdidas'=>$mp];

  echo json_encode([
    'success'=>true,
    'ganancias'=>$ganancias,
    'perdidas'=>$perdidas,
    'subtotal_ganancias'=>['usd'=>floatval($s1['usd']),'bs'=>floatval($s1['bs'])],
    'subtotal_perdidas'=>['usd'=>floatval($s2['usd']),'bs'=>floatval($s2['bs'])],
    'periods'=>$periods
  ], JSON_UNESCAPED_UNICODE);

} catch(Exception $e){
  http_response_code(500);
  error_log("summary.php error: " . $e->getMessage());
  echo json_encode(['success'=>false,'error'=>'Error interno']);
}
