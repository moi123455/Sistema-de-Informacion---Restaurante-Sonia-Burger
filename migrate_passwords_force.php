<?php
// migrate_passwords_force.php - FORZAR migración (versión corregida)
// Ejecutar UNA sola vez. Hacer BACKUP antes.
require_once __DIR__ . '/conexion.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Iniciando migración...\n\n";

$updated = 0;
$skipped = 0;
$failed = 0;

$res = $conn->query("SELECT id, password FROM usuarios ORDER BY id ASC");
if (!$res) {
    echo "Query fallida: " . $conn->error . "\n";
    exit;
}

while ($row = $res->fetch_assoc()) {
    $id = intval($row['id']);
    $pw = $row['password'];

    $info = password_get_info($pw);
    // CORRECCIÓN: considerar hash sólo si algo es distinto de 0 y no vacío
    $isHash = (isset($info['algo']) && intval($info['algo']) !== 0);

    echo "id {$id} - length: " . strlen($pw) . " - prefix: " . substr($pw,0,6) . " - isHash: " . ($isHash ? 'YES' : 'NO') . "\n";

    if ($isHash) {
        $skipped++;
        continue;
    }

    // Re-hashear y actualizar
    $newHash = password_hash($pw, PASSWORD_DEFAULT);
    if ($newHash === false) {
        echo "  ERROR: no se pudo generar hash para id {$id}\n";
        $failed++;
        continue;
    }

    $stmt = $conn->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
    if (!$stmt) {
        echo "  ERROR prepare UPDATE: " . $conn->error . "\n";
        $failed++;
        continue;
    }
    $stmt->bind_param("si", $newHash, $id);
    if ($stmt->execute()) {
        echo "  OK: id {$id} actualizado a hash (len " . strlen($newHash) . ")\n";
        $updated++;
    } else {
        echo "  ERROR execute UPDATE for id {$id}: " . $stmt->error . "\n";
        $failed++;
    }
    $stmt->close();
}

echo "\nResumen: updated={$updated}, skipped={$skipped}, failed={$failed}\n";
echo "Migración finalizada.\n";
