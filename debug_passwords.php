<?php
// debug_passwords.php - muestra exactamente lo que hay en la BD y password_get_info
require_once __DIR__ . '/conexion.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/plain; charset=utf-8');

$res = $conn->query("SELECT id, correo, password FROM usuarios ORDER BY id ASC");
if (!$res) {
    echo "Query failed: " . $conn->error . "\n";
    exit;
}

while ($row = $res->fetch_assoc()) {
    $id = $row['id'];
    $correo = $row['correo'];
    $pw = $row['password'];
    $len = strlen($pw);
    $hex = bin2hex($pw);
    $info = password_get_info($pw);
    echo "id: {$id}\n";
    echo " correo: {$correo}\n";
    echo " password (raw): [" . $pw . "]\n";
    echo " length: {$len}\n";
    echo " hex: {$hex}\n";
    echo " password_get_info algo: " . var_export($info['algo'], true) . " algoName: " . var_export($info['algoName'], true) . "\n";
    echo "-----------------------------\n";
}
