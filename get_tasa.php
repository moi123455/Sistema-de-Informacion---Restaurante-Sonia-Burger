<?php
include("conexion.php"); // estaba mal con ../../

$sql = "SELECT monto_bs FROM tasa_bcv ORDER BY id DESC LIMIT 1";
$result = $conn->query($sql);

header('Content-Type: application/json'); // fuerza salida JSON

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo json_encode(["exists" => true, "monto_bs" => $row["monto_bs"]]);
} else {
    echo json_encode(["exists" => false]);
}
?>
