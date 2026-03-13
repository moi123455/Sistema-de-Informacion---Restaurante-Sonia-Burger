<?php
// detalle.php?tipo=ganancias|perdidas&id=NN
// DEBUG temporal: mostrar errores (quitar en producción)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Ajusta la ruta absoluta si tu conexion.php está en otra ubicación
require_once 'F:/wamp64/www/Restaurante_Sonia_Burger/Restaurante_SB/conexion.php';

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) session_start();
$user_role = $_SESSION['rol'] ?? $_SESSION['user_role'] ?? '';
if (strtolower((string)$user_role) !== 'gerente') {
  http_response_code(403);
  echo json_encode(['success'=>false,'error'=>'Acceso denegado']);
  exit;
}

$tipo = $_GET['tipo'] ?? '';
$id = intval($_GET['id'] ?? 0);
if (!$id || !in_array($tipo,['ganancias','perdidas'])) {
  http_response_code(400);
  echo json_encode(['success'=>false,'error'=>'Parámetros inválidos']);
  exit;
}

try {
  if ($tipo === 'ganancias') {
    $stmt = $conn->prepare("SELECT * FROM reportes WHERE id = ? LIMIT 1");
    if (!$stmt) throw new Exception("Prepare failed (detalle ganancias): " . $conn->error);
    $stmt->bind_param("i",$id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row && !empty($row['pedidos_json'])) {
      $decoded = json_decode($row['pedidos_json'], true);
      $row['pedidos'] = $decoded !== null ? $decoded : $row['pedidos_json'];
    }
    echo json_encode(['success'=>true,'data'=>$row], JSON_UNESCAPED_UNICODE);
  } else {
    $stmt = $conn->prepare("SELECT * FROM reportes_gastos WHERE id = ? LIMIT 1");
    if (!$stmt) throw new Exception("Prepare failed (detalle perdidas): " . $conn->error);
    $stmt->bind_param("i",$id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $items = [];
    $stmt2 = $conn->prepare("SELECT * FROM reportes_gastos_items WHERE reporte_id = ? ORDER BY id");
    if ($stmt2) {
      $stmt2->bind_param("i",$id);
      $stmt2->execute();
      $res2 = $stmt2->get_result();
      while($it = $res2->fetch_assoc()) $items[] = $it;
      $stmt2->close();
    }

    echo json_encode(['success'=>true,'data'=>$row,'items'=>$items], JSON_UNESCAPED_UNICODE);
  }
} catch(Exception $e){
  http_response_code(500);
  error_log("detalle.php error: " . $e->getMessage());
  echo json_encode(['success'=>false,'error'=>'Error interno','exception'=>$e->getMessage()]);
}
