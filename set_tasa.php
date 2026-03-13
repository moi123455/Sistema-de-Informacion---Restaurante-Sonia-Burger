<?php
include("conexion.php"); // estaba mal con ../../

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $monto = $_POST["monto_bs"];

    $stmt = $conn->prepare("INSERT INTO tasa_bcv (monto_bs) VALUES (?)");
    $stmt->bind_param("d", $monto);
    $stmt->execute();

    header('Content-Type: application/json'); // fuerza salida JSON
    echo json_encode(["success" => true]);
}
?>
