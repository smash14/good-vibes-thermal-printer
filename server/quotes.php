<?php
declare(strict_types=1);

$config = require __DIR__ . '/lib/bootstrap.php';
$quotes = new QuoteRepository($config['csvFile'], $config['maxLineLength']);

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

$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['upload_csv'], $_FILES['csv_file'])) {
            if ($_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
                throw new RuntimeException('CSV upload failed.');
            }
            $quotes->replaceWithUploadedFile($_FILES['csv_file']['tmp_name']);
            $message = ['success', 'CSV uploaded successfully.'];
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

$activePage = 'quotes';
require __DIR__ . '/views/quotes.php';
