<?php

// Shared setup for every entry-point page (index.php, quotes.php, images.php,
// strings.php, logfile.php): error reporting, loading the lib classes, and
// resolving config. Returns the config array so callers can do
// `$config = require __DIR__ . '/lib/bootstrap.php';`.

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');
ini_set('error_log', sys_get_temp_dir() . '/php_error.log');

require_once __DIR__ . '/html.php';
require_once __DIR__ . '/QuoteFormatter.php';
require_once __DIR__ . '/QuoteRepository.php';
require_once __DIR__ . '/StringsRepository.php';
require_once __DIR__ . '/ImageRepository.php';
require_once __DIR__ . '/Logfile.php';

return require __DIR__ . '/../config.php';
