<?php
// includes/upload_media_helpers.php
// Utilities for auto-generating thumbnails from uploaded media/documents.

function brEnsureDirectory(string $dir): bool
{
    return is_dir($dir) || (mkdir($dir, 0755, true) && is_dir($dir));
}

function brThumbUrl(string $filename): string
{
    return APP_URL . '/uploads/thumbnails/' . rawurlencode($filename);
}

function brCaptureVideoThumbnail(string $sourcePath, string $thumbDir): ?string
{
    if (!function_exists('shell_exec')) {
        return null;
    }

    $filename = uniqid('thumb_video_', true) . '.jpg';
    $targetPath = $thumbDir . '/' . $filename;
    $nullDevice = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? 'NUL' : '/dev/null';

    $cmd = 'ffmpeg -y -ss 00:00:01 -i ' . escapeshellarg($sourcePath)
        . ' -frames:v 1 -q:v 2 ' . escapeshellarg($targetPath)
        . ' 2>' . $nullDevice;

    @shell_exec($cmd);

    if (is_file($targetPath) && filesize($targetPath) > 0) {
        return brThumbUrl($filename);
    }

    return null;
}

function brCapturePdfThumbnail(string $sourcePath, string $thumbDir): ?string
{
    $imagickClass = 'Imagick';
    if (!class_exists($imagickClass)) {
        return null;
    }

    $filename = uniqid('thumb_pdf_', true) . '.jpg';
    $targetPath = $thumbDir . '/' . $filename;

    try {
        $imagick = new $imagickClass();
        $imagick->setResolution(140, 140);
        $imagick->readImage($sourcePath . '[0]');
        $imagick->setImageFormat('jpeg');
        $imagick->setImageCompressionQuality(85);
        $imagick->thumbnailImage(640, 0, true);
        $imagick->writeImage($targetPath);
        $imagick->clear();
        $imagick->destroy();
    } catch (Throwable $e) {
        return null;
    }

    if (is_file($targetPath) && filesize($targetPath) > 0) {
        return brThumbUrl($filename);
    }

    return null;
}

function brCaptureZipImageThumbnail(string $sourcePath, string $thumbDir): ?string
{
    if (!class_exists('ZipArchive')) {
        return null;
    }

    $zip = new ZipArchive();
    if ($zip->open($sourcePath) !== true) {
        return null;
    }

    $preferred = null;
    $fallback = null;

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $entry = $zip->getNameIndex($i);
        if (!$entry || !preg_match('/\.(jpe?g|png|webp)$/i', $entry)) {
            continue;
        }

        if ($fallback === null) {
            $fallback = $entry;
        }

        if (stripos($entry, 'cover') !== false || stripos($entry, 'thumb') !== false) {
            $preferred = $entry;
            break;
        }
    }

    $selected = $preferred ?? $fallback;
    if ($selected === null) {
        $zip->close();
        return null;
    }

    $data = $zip->getFromName($selected);
    $zip->close();

    if ($data === false || $data === '') {
        return null;
    }

    $ext = strtolower(pathinfo($selected, PATHINFO_EXTENSION));
    if ($ext === 'jpeg') {
        $ext = 'jpg';
    }

    $filename = uniqid('thumb_zip_', true) . '.' . $ext;
    $targetPath = $thumbDir . '/' . $filename;

    if (file_put_contents($targetPath, $data) === false) {
        return null;
    }

    if (is_file($targetPath) && filesize($targetPath) > 0) {
        return brThumbUrl($filename);
    }

    return null;
}

function brGenerateFallbackThumbnail(string $extension, string $thumbDir): ?string
{
    if (!function_exists('imagecreatetruecolor')) {
        return null;
    }

    $filename = uniqid('thumb_file_', true) . '.jpg';
    $targetPath = $thumbDir . '/' . $filename;

    $img = imagecreatetruecolor(640, 360);
    if (!$img) {
        return null;
    }

    $bg = imagecolorallocate($img, 24, 28, 36);
    $accent = imagecolorallocate($img, 232, 178, 55);
    $text = imagecolorallocate($img, 242, 243, 245);

    imagefilledrectangle($img, 0, 0, 640, 360, $bg);
    imagefilledrectangle($img, 0, 300, 640, 360, $accent);

    $label = strtoupper(substr($extension ?: 'FILE', 0, 8));
    imagestring($img, 5, 265, 150, $label, $text);
    imagestring($img, 3, 235, 315, 'AUTO THUMBNAIL', $bg);

    imagejpeg($img, $targetPath, 82);
    imagedestroy($img);

    if (is_file($targetPath) && filesize($targetPath) > 0) {
        return brThumbUrl($filename);
    }

    return null;
}

function brAutoCaptureThumbnail(string $sourcePath, string $extension, string $thumbDir): ?string
{
    if (!is_file($sourcePath) || !brEnsureDirectory($thumbDir)) {
        return null;
    }

    $ext = strtolower(trim($extension));
    $thumbnailUrl = null;

    if (in_array($ext, ['mp4', 'webm', 'avi', 'mov', 'mkv'], true)) {
        $thumbnailUrl = brCaptureVideoThumbnail($sourcePath, $thumbDir);
    } elseif ($ext === 'pdf') {
        $thumbnailUrl = brCapturePdfThumbnail($sourcePath, $thumbDir);
    } elseif (in_array($ext, ['epub', 'docx', 'pptx'], true)) {
        $thumbnailUrl = brCaptureZipImageThumbnail($sourcePath, $thumbDir);
    }

    if ($thumbnailUrl !== null) {
        return $thumbnailUrl;
    }

    return brGenerateFallbackThumbnail($ext, $thumbDir);
}
