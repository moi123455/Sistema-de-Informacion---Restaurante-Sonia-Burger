<?php
// tokens.php - ejemplo simple (NO producción)
header("Content-Type: application/json; charset=utf-8");
// en producción: proteger con sesión/rol gerente
$caja = $_GET['caja'] ?? null;
if (!$caja) {
  echo json_encode(["success"=>false,"error"=>"caja requerida"]);
  exit;
}
// ejemplo: token derivado (mejor: generar aleatorio y guardar en BD)
$token = 'TOKEN_SECRETO_' . strtoupper(str_replace('caja_','C',$caja));
echo json_encode(["success"=>true,"caja"=>$caja,"token"=>$token]);
