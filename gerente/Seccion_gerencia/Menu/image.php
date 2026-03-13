<?php
include("../../../conexion.php");
$id = intval($_GET['id'] ?? 0);
if ($id <= 0) { header("HTTP/1.1 404 Not Found"); exit; }

$result = $conn->query("SELECT imagen, imagen_blob, imagen_mime FROM menu WHERE id=$id LIMIT 1");
if (!$result) { header("HTTP/1.1 500 Internal Server Error"); exit; }
$row = $result->fetch_assoc();
if (!$row) { header("HTTP/1.1 404 Not Found"); exit; }

if (!empty($row['imagen'])) {
    $path = __DIR__ . "/" . $row['imagen'];
    if (file_exists($path)) {
        $mime = mime_content_type($path);
        header("Content-Type: $mime");
        readfile($path);
        exit;
    }
}

if (!empty($row['imagen_blob'])) {
    $mime = $row['imagen_mime'] ?: 'image/jpeg';
    header("Content-Type: $mime");
    echo $row['imagen_blob'];
    exit;
}

header("HTTP/1.1 404 Not Found");
exit;
?>
