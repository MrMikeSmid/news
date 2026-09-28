<?php

declare(strict_types=1);

function serve_media(string $filename): void
{
    global $config;

    $filename = basename($filename);
    $path = rtrim($config['uploads_path'], '/') . '/' . $filename;

    if ($filename === '' || !is_file($path)) {
        abort(404, 'Bestand niet gevonden.');
    }

    $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
    $mimeTypes = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
        'gif' => 'image/gif', 'webp' => 'image/webp',
        'mp4' => 'video/mp4', 'webm' => 'video/webm', 'mov' => 'video/quicktime',
    ];
    $mime = $mimeTypes[$extension] ?? 'application/octet-stream';

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string) filesize($path));
    header('Cache-Control: public, max-age=604800');
    readfile($path);
    exit;
}

/**
 * @return array{path:?string, type:?string, error:?string}
 */
function handle_media_upload(string $field): array
{
    global $config;

    if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['path' => null, 'type' => null, 'error' => null];
    }

    $file = $_FILES[$field];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['path' => null, 'type' => null, 'error' => 'Upload mislukt.'];
    }
    if ($file['size'] > $config['max_upload_bytes']) {
        return ['path' => null, 'type' => null, 'error' => 'Bestand is te groot (max 15MB).'];
    }

    $extension = strtolower((string) pathinfo((string) $file['name'], PATHINFO_EXTENSION));
    $type = null;
    if (in_array($extension, $config['allowed_image_types'], true)) {
        $type = 'image';
    } elseif (in_array($extension, $config['allowed_video_types'], true)) {
        $type = 'video';
    }

    if ($type === null) {
        return ['path' => null, 'type' => null, 'error' => 'Bestandstype niet toegestaan.'];
    }

    if (!is_dir($config['uploads_path'])) {
        mkdir($config['uploads_path'], 0775, true);
    }

    $newName = bin2hex(random_bytes(16)) . '.' . $extension;
    $destination = rtrim($config['uploads_path'], '/') . '/' . $newName;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return ['path' => null, 'type' => null, 'error' => 'Kon bestand niet opslaan.'];
    }

    return ['path' => url('media/' . $newName), 'type' => $type, 'error' => null];
}
