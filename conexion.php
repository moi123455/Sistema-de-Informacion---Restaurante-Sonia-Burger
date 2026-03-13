<?php
// Datos de conexión
$servername = "localhost";
$username   = "root";      // usuario por defecto en WAMP
$password   = "";          // normalmente vacío
$dbname     = "restaurante_sb";

// Crear conexión
$conn = new mysqli($servername, $username, $password, $dbname);

// Verificar conexión
if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

// Opcional: configurar charset para evitar problemas con acentos y caracteres especiales
$conn->set_charset("utf8mb4");
?>
