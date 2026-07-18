<?php
declare(strict_types=1);

$config = require __DIR__ . '/lib/bootstrap.php';
$images = new ImageRepository($config['imgDir'], $config['allowedImageExtensions'], $config['maxImageUploadBytes']);

if (isset($_GET['download_all'])) {
    try {
        $zipPath = $images->createZipArchive();
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="header_images.zip"');
        header('Content-Length: ' . (string) filesize($zipPath));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        readfile($zipPath);
        unlink($zipPath);
    } catch (RuntimeException $e) {
        error_log('ZIP download error: ' . $e->getMessage());
        http_response_code(400);
        header('Content-Type: text/plain');
        echo 'Download error: ' . $e->getMessage() . "\n";
    }
    exit;
}

$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['upload_bin'], $_FILES['bin_file'])) {
            $images->uploadBinFile($_FILES['bin_file']);
            $message = ['success', 'BIN file uploaded successfully.'];
        } elseif (isset($_POST['upload_image'], $_FILES['image_file'])) {
            $images->uploadPicture($_FILES['image_file']);
            $message = ['success', 'Picture uploaded successfully. It will be converted automatically the next time the printer starts.'];
        } elseif (isset($_POST['delete_all'])) {
            $images->deleteAll();
            $message = ['success', 'All files deleted.'];
        } elseif (isset($_POST['delete_entry'], $_POST['stem'])) {
            $images->deleteEntry((string) $_POST['stem']);
            $message = ['success', 'Image deleted.'];
        }
    } catch (Throwable $e) {
        $message = ['error', $e->getMessage()];
    }
}

$activePage = 'images';
require __DIR__ . '/views/images.php';
