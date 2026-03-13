<?php
// menu.php - Versión integrada con soporte horario (dia/tarde/noche)
// Fusiona tus partes y añade manejo de horario en GET/POST/PUT/DELETE
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.txt');

include("../../../conexion.php");
header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];

/* -------------------------
   Helpers
   ------------------------- */
function obtenerTasaBCV($conn) {
    // Mantener tu lógica robusta (simplificada aquí: buscar tabla tasa_bcv)
    $res = $conn->query("SELECT monto_bs AS tasa FROM tasa_bcv ORDER BY id DESC LIMIT 1");
    if ($res && $r = $res->fetch_assoc()) {
        $raw = str_replace(',', '.', strval($r['tasa']));
        $val = floatval($raw);
        if ($val > 0) return $val;
    }
    return 1.0;
}

$uploadsDir = __DIR__ . "/uploads";
if (!is_dir($uploadsDir)) @mkdir($uploadsDir, 0777, true);

/* -------------------------
   GET - listar productos
   Soporta ?role=gerente|cajero|cliente y ?horario=dia|tarde|noche
   ------------------------- */
if ($method === 'GET') {
    $role = $_GET['role'] ?? 'gerente';
    $horarioFilter = $_GET['horario'] ?? null;
    $tasa = obtenerTasaBCV($conn);

    // Construir consulta
    if ($horarioFilter && in_array($horarioFilter, ['dia','tarde','noche'])) {
        $stmt = $conn->prepare("SELECT id, nombre, descripcion, categoria, precio_usd, precio_bs, imagen, imagen_blob, imagen_mime, estado, horario FROM menu WHERE horario = ? ORDER BY categoria ASC, id ASC");
        $stmt->bind_param("s", $horarioFilter);
    } else {
        $stmt = $conn->prepare("SELECT id, nombre, descripcion, categoria, precio_usd, precio_bs, imagen, imagen_blob, imagen_mime, estado, horario FROM menu ORDER BY categoria ASC, id ASC");
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $productos = [];

    while ($row = $res->fetch_assoc()) {
        $row['precio_usd'] = floatval($row['precio_usd']);
        if ($tasa <= 0) {
            $row['precio_bs'] = null;
            $row['tasa_establecida'] = false;
        } else {
            $row['precio_bs'] = round($row['precio_usd'] * $tasa, 2);
            $row['tasa_establecida'] = true;
        }
        if (empty($row['estado'])) $row['estado'] = "Activo";

        // Normalizar imagen_url si tienes imagen relativa
        if (empty($row['imagen'])) {
            $row['imagen'] = "image.php?id=" . $row['id'];
            $row['imagen_url'] = $row['imagen'];
        } else {
            $row['imagen_url'] = __DIR__ . "/" . $row['imagen'];
            // devolver ruta relativa accesible desde web (intenta mantener la lógica que usabas)
            $base = (isset($_SERVER['HTTP_HOST']) ? ( (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off') ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . "/Restaurante_Sonia_Burger/Restaurante_SB/" : '');
            $row['imagen_url'] = $base . "gerente/Seccion_gerencia/Menu/" . ltrim($row['imagen'], "/");
        }

        $productos[] = $row;
    }

    echo json_encode($productos, JSON_UNESCAPED_UNICODE);
    exit;
}

/* -------------------------
   POST - Registrar o Editar producto (si llega id => editar)
   - Soporta campo 'horario' (dia/tarde/noche)
   ------------------------- */
if ($method === 'POST') {
    $isEdit = isset($_POST['id']) && intval($_POST['id']) > 0;
    $forzar = isset($_POST['forzar']) && $_POST['forzar'] == '1';

    $nombre_raw = $_POST['nombre'] ?? '';
    $nombre = trim($nombre_raw);
    $descripcion = $_POST['descripcion'] ?? '';
    $categoria = $_POST['categoria'] ?? '';
    $precio_usd_raw = str_replace(',', '.', ($_POST['precio_usd'] ?? '0'));
    $precio_usd = floatval($precio_usd_raw);
    $estado = $_POST['estado'] ?? 'Activo';

    // nuevo: horario
    $horario = $_POST['horario'] ?? 'dia';
    $horario = strtolower(trim($horario));
    if (!in_array($horario, ['dia','tarde','noche'])) $horario = 'dia';

    if ($nombre === '') { echo json_encode(["error" => "Nombre requerido"]); exit; }

    // Imagen handling (igual que tu código)
    $allowedTypes = ['image/jpeg','image/png','image/gif','image/webp'];
    $maxSizeBytes = 5 * 1024 * 1024;
    $newImageUploaded = (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK);
    $imagen_rel = "";
    $imagen_blob = null;
    $imagen_mime = null;

    if ($newImageUploaded) {
        $tmpPath = $_FILES['imagen']['tmp_name'];
        $origName = $_FILES['imagen']['name'];
        $extension = pathinfo($origName, PATHINFO_EXTENSION);
        $safeName = preg_replace('/[^A-Za-z0-9_\-]/', '_', strtolower($nombre)) . "." . $extension;
        $mime = @mime_content_type($tmpPath);
        $size = @filesize($tmpPath);
        $imgInfo = @getimagesize($tmpPath);

        if ($mime === false || !in_array($mime, $allowedTypes)) { echo json_encode(["error" => "Tipo de imagen no permitido. Usa JPG, PNG, GIF o WEBP."]); exit; }
        if ($size === false || $size > $maxSizeBytes) { echo json_encode(["error" => "Imagen demasiado grande. Máx 5 MB."]); exit; }
        if ($imgInfo === false) { echo json_encode(["error" => "Archivo no es una imagen válida."]); exit; }

        if (is_writable($uploadsDir)) {
            $destPath = $uploadsDir . "/" . $safeName;
            if (file_exists($destPath) && !$forzar) {
                echo json_encode(["warning" => "Ya existe una imagen con ese nombre", "imagen" => "uploads/" . $safeName]);
                exit;
            }
            if (move_uploaded_file($tmpPath, $destPath)) {
                $imagen_rel = "uploads/" . $safeName;
            } else {
                $data = @file_get_contents($tmpPath);
                if ($data !== false) { $imagen_blob = $data; $imagen_mime = $mime ?: ("image/" . $extension); }
                else { echo json_encode(["error" => "No se pudo procesar la imagen subida."]); exit; }
            }
        } else {
            $data = @file_get_contents($tmpPath);
            if ($data !== false) { $imagen_blob = $data; $imagen_mime = $mime ?: ("image/" . $extension); }
            else { echo json_encode(["error" => "No se pudo procesar la imagen subida."]); exit; }
        }
    }

    // Verificar duplicados por nombre
    $ids = [];
    $stmtCheck = $conn->prepare("SELECT id FROM menu WHERE nombre = ?");
    if ($stmtCheck) {
        $stmtCheck->bind_param("s", $nombre);
        $stmtCheck->execute();
        $stmtCheck->store_result();
        if ($stmtCheck->num_rows > 0) {
            $stmtCheck->bind_result($foundId);
            $stmtCheck->data_seek(0);
            while ($stmtCheck->fetch()) $ids[] = intval($foundId);
        }
        $stmtCheck->free_result();
        $stmtCheck->close();
    }

    // Forzar reemplazo de duplicado (no edición)
    if (!$isEdit && $forzar && count($ids) > 0) {
        $dupId = $ids[0];
        $imagen_blob_esc = $imagen_blob !== null ? $conn->real_escape_string($imagen_blob) : null;
        $imagen_mime_esc = $imagen_mime !== null ? $conn->real_escape_string($imagen_mime) : null;
        $imagen_rel_esc = $imagen_rel !== "" ? $conn->real_escape_string($imagen_rel) : "";

        $resOld = $conn->query("SELECT imagen FROM menu WHERE id=" . intval($dupId));
        $oldRow = $resOld ? $resOld->fetch_assoc() : null;
        if ($imagen_rel !== "" && $oldRow && !empty($oldRow['imagen'])) {
            $oldPath = __DIR__ . "/" . $oldRow['imagen'];
            if (file_exists($oldPath)) @unlink($oldPath);
        }

        if ($imagen_blob !== null) {
            $sql = "UPDATE menu SET nombre='" . $conn->real_escape_string($nombre) . "', descripcion='" . $conn->real_escape_string($descripcion) . "', categoria='" . $conn->real_escape_string($categoria) . "', precio_usd=" . floatval($precio_usd) . ", imagen='', imagen_blob='{$imagen_blob_esc}', imagen_mime='{$imagen_mime_esc}', estado='" . $conn->real_escape_string($estado) . "', horario='" . $conn->real_escape_string($horario) . "' WHERE id=" . intval($dupId);
        } else {
            $sql = "UPDATE menu SET nombre='" . $conn->real_escape_string($nombre) . "', descripcion='" . $conn->real_escape_string($descripcion) . "', categoria='" . $conn->real_escape_string($categoria) . "', precio_usd=" . floatval($precio_usd) . ", imagen='" . $imagen_rel_esc . "', estado='" . $conn->real_escape_string($estado) . "', horario='" . $conn->real_escape_string($horario) . "' WHERE id=" . intval($dupId);
        }
        if ($conn->query($sql)) { echo json_encode(["success" => true, "replaced_id" => $dupId]); } else { echo json_encode(["error" => $conn->error]); }
        exit;
    }

    // --- Ramas: editar o insertar ---
    if ($isEdit) {
        $id = intval($_POST['id']);
        $resOld = $conn->query("SELECT imagen FROM menu WHERE id=" . $id);
        $oldRow = $resOld ? $resOld->fetch_assoc() : null;

        if ($newImageUploaded && $imagen_rel !== "") {
            if ($oldRow && !empty($oldRow['imagen'])) {
                $oldPath = __DIR__ . "/" . $oldRow['imagen'];
                if (file_exists($oldPath)) @unlink($oldPath);
            }
            $sql = "UPDATE menu SET nombre='" . $conn->real_escape_string($nombre) . "', descripcion='" . $conn->real_escape_string($descripcion) . "', categoria='" . $conn->real_escape_string($categoria) . "', precio_usd=" . floatval($precio_usd) . ", imagen='" . $conn->real_escape_string($imagen_rel) . "', estado='" . $conn->real_escape_string($estado) . "', horario='" . $conn->real_escape_string($horario) . "' WHERE id=" . intval($id);
            if ($conn->query($sql)) echo json_encode(["success" => true]); else echo json_encode(["error" => $conn->error]);
            exit;
        }

        if ($newImageUploaded && $imagen_blob !== null) {
            if ($oldRow && !empty($oldRow['imagen'])) {
                $oldPath = __DIR__ . "/" . $oldRow['imagen'];
                if (file_exists($oldPath)) @unlink($oldPath);
            }
            $imagen_blob_esc = $conn->real_escape_string($imagen_blob);
            $imagen_mime_esc = $conn->real_escape_string($imagen_mime);
            $sql = "UPDATE menu SET nombre='" . $conn->real_escape_string($nombre) . "', descripcion='" . $conn->real_escape_string($descripcion) . "', categoria='" . $conn->real_escape_string($categoria) . "', precio_usd=" . floatval($precio_usd) . ", imagen='', imagen_blob='{$imagen_blob_esc}', imagen_mime='{$imagen_mime_esc}', estado='" . $conn->real_escape_string($estado) . "', horario='" . $conn->real_escape_string($horario) . "' WHERE id=" . intval($id);
            if ($conn->query($sql)) echo json_encode(["success" => true]); else echo json_encode(["error" => $conn->error]);
            exit;
        }

        $sql = "UPDATE menu SET nombre='" . $conn->real_escape_string($nombre) . "', descripcion='" . $conn->real_escape_string($descripcion) . "', categoria='" . $conn->real_escape_string($categoria) . "', precio_usd=" . floatval($precio_usd) . ", estado='" . $conn->real_escape_string($estado) . "', horario='" . $conn->real_escape_string($horario) . "' WHERE id=" . intval($id);
        if ($conn->query($sql)) echo json_encode(["success" => true]); else echo json_encode(["error" => $conn->error]);
        exit;
    } else {
        $precio_bs = 0;
        if ($imagen_blob === null) {
            $imagen_rel = $imagen_rel ?: "";
            $sql = "INSERT INTO menu (nombre, descripcion, categoria, precio_usd, precio_bs, imagen, imagen_blob, imagen_mime, estado, horario) VALUES ('" . $conn->real_escape_string($nombre) . "', '" . $conn->real_escape_string($descripcion) . "', '" . $conn->real_escape_string($categoria) . "', " . floatval($precio_usd) . ", " . floatval($precio_bs) . ", '" . $conn->real_escape_string($imagen_rel) . "', NULL, NULL, '" . $conn->real_escape_string($estado) . "', '" . $conn->real_escape_string($horario) . "')";
            if ($conn->query($sql)) echo json_encode(["success" => true]); else echo json_encode(["error" => $conn->error]);
            exit;
        } else {
            $imagen_mime_esc = $conn->real_escape_string($imagen_mime);
            $imagen_blob_esc = $conn->real_escape_string($imagen_blob);
            $sql = "INSERT INTO menu (nombre, descripcion, categoria, precio_usd, precio_bs, imagen, imagen_blob, imagen_mime, estado, horario) VALUES ('" . $conn->real_escape_string($nombre) . "', '" . $conn->real_escape_string($descripcion) . "', '" . $conn->real_escape_string($categoria) . "', " . floatval($precio_usd) . ", " . floatval($precio_bs) . ", '', '" . $imagen_blob_esc . "', '" . $imagen_mime_esc . "', '" . $conn->real_escape_string($estado) . "', '" . $conn->real_escape_string($horario) . "')";
            if ($conn->query($sql)) echo json_encode(["success" => true]); else echo json_encode(["error" => $conn->error]);
            exit;
        }
    }
}

/* -------------------------
   PUT - Cambiar estado
   ------------------------- */
if ($method === 'PUT') {
    parse_str(file_get_contents("php://input"), $_PUT);
    $id = intval($_PUT['id'] ?? 0);
    $estado = $_PUT['estado'] ?? null;
    if ($estado) {
        $stmt = $conn->prepare("UPDATE menu SET estado=? WHERE id=?");
        if ($stmt) {
            $stmt->bind_param("si", $estado, $id);
            $stmt->execute();
            $stmt->close();
            echo json_encode(["success" => true]);
            exit;
        } else {
            echo json_encode(["error" => $conn->error]);
            exit;
        }
    }
}

/* -------------------------
   DELETE - Eliminar producto
   ------------------------- */
if ($method === 'DELETE') {
    parse_str(file_get_contents("php://input"), $_DELETE);
    $id = intval($_DELETE['id'] ?? 0);

    $result = $conn->query("SELECT imagen FROM menu WHERE id=" . $id);
    if ($row = $result->fetch_assoc()) {
        if (!empty($row['imagen'])) {
            $file = __DIR__ . "/" . $row['imagen'];
            if (file_exists($file)) @unlink($file);
        }
    }

    $stmt = $conn->prepare("DELETE FROM menu WHERE id=?");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        echo json_encode(["success" => true]);
        exit;
    } else {
        echo json_encode(["error" => $conn->error]);
        exit;
    }
}

echo json_encode(["error" => "Método no soportado"]);
exit;
?>
