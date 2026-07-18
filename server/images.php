<?php
declare(strict_types=1);

$config = require __DIR__ . '/lib/bootstrap.php';
$images = new ImageRepository($config['imgDir'], $config['allowedImageExtensions'], $config['maxImageUploadBytes']);
$converter = new ImageConverterRunner($config['pythonBinary'], $config['imageConverterScript']);

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
            $images->stageUpload($_FILES['image_file'], $converter, $config['imageMaxWidth']);
            $message = ['success', 'Picture uploaded. Review the preview below and adjust contrast/threshold before saving.'];
        } elseif (isset($_POST['action']) && $_POST['action'] === 'regenerate_preview') {
            $contrast = (float) ($_POST['contrast'] ?? 1.0);
            $useThreshold = isset($_POST['use_threshold']);
            $threshold = $useThreshold ? (int) ($_POST['threshold'] ?? 128) : null;
            $rotation = (int) ($_POST['rotation'] ?? 0);
            if (!in_array($rotation, [0, 90, 180, 270], true)) {
                throw new RuntimeException('Invalid rotation value.');
            }
            $brightness = (float) ($_POST['brightness'] ?? 1.0);
            $autoContrast = isset($_POST['auto_contrast']);
            $images->regeneratePreview($converter, $contrast, $threshold, $config['imageMaxWidth'], $rotation, $brightness, $autoContrast);
            $message = ['success', 'Preview updated.'];
        } elseif (isset($_POST['action']) && $_POST['action'] === 'save_review') {
            $savedStem = $images->saveReview();
            $message = ['success', "Image saved as \"{$savedStem}\"."];
        } elseif (isset($_POST['action']) && $_POST['action'] === 'discard_review') {
            $images->discardReview();
            $message = ['success', 'Review discarded.'];
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
