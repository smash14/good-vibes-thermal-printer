<?php
declare(strict_types=1);

$config = require __DIR__ . '/lib/bootstrap.php';
$strings = new StringsRepository($config['stringsFile'], $config['stringsDefaultFile']);

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

$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['upload_strings'], $_FILES['strings_file'])) {
            if ($_FILES['strings_file']['error'] !== UPLOAD_ERR_OK) {
                throw new RuntimeException('strings.json upload failed.');
            }
            $strings->replaceWithUploadedFile($_FILES['strings_file']['tmp_name']);
            $message = ['success', 'strings.json uploaded successfully.'];
        } elseif (isset($_POST['restore_strings'])) {
            $strings->restoreDefault();
            $message = ['success', 'strings.json restored to default.'];
        } elseif (isset($_POST['action']) && $_POST['action'] === 'update_entry') {
            $key = (string) ($_POST['entry_key'] ?? '');
            $strings->updateEntryValue($key, (string) ($_POST['entry_value'] ?? ''));
            $message = ['success', "Updated \"{$key}\"."];
        }
    } catch (Throwable $e) {
        $message = ['error', $e->getMessage()];
    }
}

$activePage = 'strings';
require __DIR__ . '/views/strings.php';
