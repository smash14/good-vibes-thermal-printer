<?php
// Central place for the admin page's file locations and limits.
// Paths are relative to the directory the PHP server is run from
// (see CLAUDE.md: "www_path override" — this must stay in sync with
// where main.py's resolve_file_paths() looks for uploaded content).

return [
    'csvFile' => 'goodVibes.csv',
    'imgDir' => 'header_images/',
    'stringsFile' => 'strings.json',
    'stringsDefaultFile' => 'strings_default.json',
    'logFile' => 'logfile.log',
    'maxLineLength' => 42,
    'maxImageUploadBytes' => 10 * 1024 * 1024,
    'allowedImageExtensions' => ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'tif', 'tiff'],
    'imageMaxWidth' => 384,
    'pythonBinary' => 'python3',
    'imageConverterScript' => __DIR__ . '/scripts/image_converter.py',
];
