<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');
ini_set('error_log', sys_get_temp_dir() . '/php_error.log');

require_once __DIR__ . '/lib/html.php';
require_once __DIR__ . '/lib/QuoteFormatter.php';
require_once __DIR__ . '/lib/QuoteRepository.php';
require_once __DIR__ . '/lib/StringsRepository.php';
require_once __DIR__ . '/lib/ImageRepository.php';
require_once __DIR__ . '/lib/Logfile.php';

$config = require __DIR__ . '/config.php';

$quotes = new QuoteRepository($config['csvFile'], $config['maxLineLength']);
$strings = new StringsRepository($config['stringsFile'], $config['stringsDefaultFile']);
$images = new ImageRepository($config['imgDir'], $config['allowedImageExtensions'], $config['maxImageUploadBytes']);
$logfile = new Logfile($config['logFile']);

// --- Downloads send their own headers and exit, so they must run before any HTML output. ---

if (isset($_GET['download_csv'])) {
    if (!$quotes->exists()) {
        echo 'CSV file not found.';
        exit;
    }
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="goodVibes.csv"');
    readfile($config['csvFile']);
    exit;
}

if (isset($_GET['download_strings'])) {
    if (!$strings->exists()) {
        echo 'strings.json file not found.';
        exit;
    }
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="strings.json"');
    readfile($config['stringsFile']);
    exit;
}

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

// --- Actions mutate state and produce a single status message for the page. ---

$message = null; // ['success'|'error', string]

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['upload_csv'], $_FILES['csv_file'])) {
            if ($_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
                throw new RuntimeException('CSV upload failed.');
            }
            $quotes->replaceWithUploadedFile($_FILES['csv_file']['tmp_name']);
            $message = ['success', 'CSV uploaded successfully.'];
        } elseif (isset($_POST['upload_strings'], $_FILES['strings_file'])) {
            if ($_FILES['strings_file']['error'] !== UPLOAD_ERR_OK) {
                throw new RuntimeException('strings.json upload failed.');
            }
            $strings->replaceWithUploadedFile($_FILES['strings_file']['tmp_name']);
            $message = ['success', 'strings.json uploaded successfully.'];
        } elseif (isset($_POST['restore_strings'])) {
            $strings->restoreDefault();
            $message = ['success', 'strings.json restored to default.'];
        } elseif (isset($_POST['upload_bin'], $_FILES['bin_file'])) {
            $images->uploadBinFile($_FILES['bin_file']);
            $message = ['success', 'BIN file uploaded successfully.'];
        } elseif (isset($_POST['upload_image'], $_FILES['image_file'])) {
            $images->uploadPicture($_FILES['image_file']);
            $message = ['success', 'Picture uploaded successfully. It will be converted automatically the next time the printer starts.'];
        } elseif (isset($_POST['delete_all'])) {
            $images->deleteAll();
            $message = ['success', 'All files deleted.'];
        } elseif (isset($_POST['delete_logfile'])) {
            $logfile->delete();
            $message = ['success', 'Logfile deleted.'];
        } elseif (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add':
                    $quotes->add((string) ($_POST['new_entry'] ?? ''));
                    $message = ['success', 'Entry added successfully!'];
                    break;
                case 'update':
                    $quotes->update((int) ($_POST['row_index'] ?? -1), (string) ($_POST['updated_entry'] ?? ''));
                    $message = ['success', 'Entry updated successfully!'];
                    break;
                case 'delete':
                    $quotes->delete((int) ($_POST['row_index'] ?? -1));
                    $message = ['success', 'Entry deleted successfully!'];
                    break;
            }
        }
    } catch (Throwable $e) {
        $message = ['error', $e->getMessage()];
    }
}

require __DIR__ . '/views/page.php';
