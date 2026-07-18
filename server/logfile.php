<?php
declare(strict_types=1);

$config = require __DIR__ . '/lib/bootstrap.php';
$logfile = new Logfile($config['logFile']);

$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['delete_logfile'])) {
            $logfile->delete();
            $message = ['success', 'Logfile deleted.'];
        }
    } catch (Throwable $e) {
        $message = ['error', $e->getMessage()];
    }
}

$activePage = 'logfile';
require __DIR__ . '/views/logfile.php';
