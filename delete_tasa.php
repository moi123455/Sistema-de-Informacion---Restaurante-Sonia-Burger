<?php
include("conexion.php");
$conn->query("DELETE FROM tasa_bcv");
header('Content-Type: application/json');
echo json_encode(["success" => true]);
?>
