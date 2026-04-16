<?php
require_once __DIR__ . '/auth.php';

function profile_public_path(?string $path): string
{
    $path = trim((string)$path);

    if ($path === '') {
        return '';
    }

    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }

    return BASE_URL . ltrim($path, '/');
}

function profile_upload_directory(): string
{
    return dirname(__DIR__) . '/uploads/profile_images';
}

function ensure_profile_upload_directory(): void
{
    $dir = profile_upload_directory();

    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
}

function delete_old_profile_image(?string $path): void
{
    $path = trim((string)$path);

    if ($path === '') {
        return;
    }

    if (preg_match('#^https?://#i', $path)) {
        return;
    }

    $fullPath = dirname(__DIR__) . '/' . ltrim($path, '/');

    if (is_file($fullPath)) {
        @unlink($fullPath);
    }
}

function store_profile_image_upload(array $file, ?string $oldPath = null): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return trim((string)$oldPath);
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Profile image upload failed.');
    }

    $tmp = $file['tmp_name'] ?? '';
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new RuntimeException('Invalid uploaded file.');
    }

    $maxBytes = 2 * 1024 * 1024;
    if ((int)($file['size'] ?? 0) > $maxBytes) {
        throw new RuntimeException('Profile image must be 2MB or smaller.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$finfo->file($tmp);

    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    if (!isset($extensions[$mime])) {
        throw new RuntimeException('Only JPG, PNG, WEBP, and GIF files are allowed.');
    }

    ensure_profile_upload_directory();

    $filename = 'profile_' . (int)current_user_id() . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $extensions[$mime];
    $relativePath = 'uploads/profile_images/' . $filename;
    $destination = dirname(__DIR__) . '/' . $relativePath;

    if (!move_uploaded_file($tmp, $destination)) {
        throw new RuntimeException('Failed to save profile image.');
    }

    if (!empty($oldPath) && trim((string)$oldPath) !== $relativePath) {
        delete_old_profile_image($oldPath);
    }

    return $relativePath;
}