<?php
// get_estadisticas.php
ini_set('display_errors', 0);
error_reporting(0);
header("Content-Type: application/json; charset=utf-8");
require_once("../../conexion.php");
date_default_timezone_set('America/Caracas');

$stats = [
    "total_pedidos_hoy" => 0,
    "ventas_usd_hoy" => 0.0,
    "ventas_bs_hoy" => 0.0,
    "top_platos" => []
];

// Totales del día (entregados)
$sql = "SELECT COUNT(*) AS cnt, COALESCE(SUM(total),0) AS ventas_usd, COALESCE(SUM(plato_precio_bs),0) AS ventas_bs
        FROM pedidos
        WHERE DATE(fecha) = CURDATE() AND LOWER(TRIM(estado)) = 'entregado'";
$r = $conn->query($sql);
if ($r && $row = $r->fetch_assoc()) {
    $stats["total_pedidos_hoy"] = (int)$row['cnt'];
    $stats["ventas_usd_hoy"] = (float)$row['ventas_usd'];
    $stats["ventas_bs_hoy"] = (float)$row['ventas_bs'];
}

// Top 5 platos por cantidad (todos los tiempos o filtrar por fecha si prefieres)
$sql2 = "SELECT plato_nombre, COUNT(*) AS veces, COALESCE(SUM(total),0) AS ventas_usd
         FROM pedidos
         GROUP BY plato_nombre
         ORDER BY veces DESC
         LIMIT 5";
$r2 = $conn->query($sql2);
if ($r2) {
    while ($row = $r2->fetch_assoc()) {
        $stats["top_platos"][] = [
            "plato_nombre" => $row['plato_nombre'],
            "veces" => (int)$row['veces'],
            "ventas_usd" => (float)$row['ventas_usd']
        ];
    }
}

echo json_encode(["success" => true, "stats" => $stats]);
