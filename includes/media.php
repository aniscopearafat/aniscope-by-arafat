<?php
declare(strict_types=1);

function normalize_image_reference(string $value): string
{
    $value = trim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($value === '') return '';

    if (preg_match('/<img\b[^>]*\bsrc\s*=\s*(["\'])(.*?)\1/is', $value, $match)) {
        $value = trim($match[2]);
    }

    if (preg_match('#^https?://#i', $value) && filter_var($value, FILTER_VALIDATE_URL)) return $value;
    if (preg_match('#^/?assets/[A-Za-z0-9_./-]+$#', $value)) return '/' . ltrim($value, '/');

    throw new RuntimeException('Use a direct http(s) image URL, an ImgBB HTML image snippet, or upload a file.');
}

function process_image_input(string $fieldName, string $textValue, string $existing = ''): string
{
    $upload = $_FILES[$fieldName] ?? null;
    if (!$upload || (int)$upload['error'] === UPLOAD_ERR_NO_FILE) {
        $reference = trim($textValue) !== '' ? $textValue : $existing;
        return normalize_image_reference($reference);
    }

    if ((int)$upload['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('The image upload failed. Please try a smaller file.');
    if ((int)$upload['size'] > 5 * 1024 * 1024) throw new RuntimeException('Images must be 5 MB or smaller.');

    $details = @getimagesize($upload['tmp_name']);
    $mime = $details['mime'] ?? '';
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    if (!isset($extensions[$mime])) throw new RuntimeException('Upload a JPG, PNG, WebP, or GIF image.');

    $uploadDirectory = dirname(__DIR__) . '/assets/uploads';
    if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
        throw new RuntimeException('The uploads folder could not be created.');
    }

    $filename = date('YmdHis') . '-' . bin2hex(random_bytes(8)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($upload['tmp_name'], $uploadDirectory . '/' . $filename)) {
        throw new RuntimeException('The uploaded image could not be saved.');
    }
    return '/assets/uploads/' . $filename;
}
