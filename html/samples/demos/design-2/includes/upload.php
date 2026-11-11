<?php
/** Safe image upload handling for listing photos. */
declare(strict_types=1);

/**
 * Validate & store uploaded images from $_FILES[$field].
 * Returns [array $savedFilenames, array $errors].
 */
function save_uploaded_images(string $field): array
{
    $saved = [];
    $errors = [];
    if (empty($_FILES[$field]) || !is_array($_FILES[$field]['name'])) return [$saved, $errors];

    if (!is_dir(UPLOAD_DIR)) @mkdir(UPLOAD_DIR, 0775, true);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;
    $n = count($_FILES[$field]['name']);

    for ($i = 0; $i < $n; $i++) {
        $err = $_FILES[$field]['error'][$i];
        if ($err === UPLOAD_ERR_NO_FILE) continue;
        $name = (string)$_FILES[$field]['name'][$i];
        if (count($saved) >= UPLOAD_MAX_FILES) { $errors[] = 'Only ' . UPLOAD_MAX_FILES . ' photos are allowed; extra photos were skipped.'; break; }
        if ($err !== UPLOAD_ERR_OK) { $errors[] = "“{$name}” could not be uploaded (error {$err})."; continue; }
        $tmp = $_FILES[$field]['tmp_name'][$i];
        if (!is_uploaded_file($tmp)) { $errors[] = "“{$name}” was rejected."; continue; }
        if (filesize($tmp) > UPLOAD_MAX_BYTES) { $errors[] = "“{$name}” is larger than " . (UPLOAD_MAX_BYTES / 1048576) . ' MB.'; continue; }
        $info = @getimagesize($tmp);
        $mime = $finfo ? (string)finfo_file($finfo, $tmp) : (is_array($info) ? (string)$info['mime'] : '');
        if (!isset($allowed[$mime]) || !$info || $info[0] < 50 || $info[1] < 50 || $info[0] > 10000 || $info[1] > 10000) {
            $errors[] = "“{$name}” is not a valid JPG, PNG or WEBP image.";
            continue;
        }
        $file = date('Ymd') . '-' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
        if (!move_uploaded_file($tmp, UPLOAD_DIR . '/' . $file)) { $errors[] = "“{$name}” could not be saved (check folder permissions)."; continue; }
        @chmod(UPLOAD_DIR . '/' . $file, 0644);
        $saved[] = $file;
    }
    if ($finfo) finfo_close($finfo);
    return [$saved, $errors];
}
