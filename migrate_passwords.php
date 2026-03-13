<?php
// migrate_passwords.php - ejecutar una vez (hacer backup antes)
require_once __DIR__ . '/conexion.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

$res = $conn->query("SELECT id, password FROM usuarios");
while ($row = $res->fetch_assoc()) {
    $id = intval($row['id']);
    $pw = $row['password'];
    // si ya es hash, saltar
    if (password_get_info($pw)['algo'] !== 0) {
        echo "id {$id} ya tiene hash\n";
        continue;
    }
    $new = password_hash($pw, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
    $stmt->bind_param("si", $new, $id);
    $stmt->execute();
    echo "id {$id} actualizado\n";
}
echo "Migración completada\n";
