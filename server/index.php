<?php
$csvFile = "goodVibes.csv";
$imgDir = "header_images/";

// Ensure directory exists
if (!file_exists($imgDir)) {
    mkdir($imgDir, 0777, true);
}

// Handle CSV upload
if (isset($_POST['upload_csv']) && isset($_FILES['csv_file'])) {
    if ($_FILES['csv_file']['error'] === 0) {
        move_uploaded_file($_FILES['csv_file']['tmp_name'], $csvFile);
        echo "CSV uploaded successfully.<br>";
    }
}

// Handle CSV download
if (isset($_GET['download_csv'])) {
    if (file_exists($csvFile)) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="goodVibes.csv"');
        readfile($csvFile);
        exit;
    } else {
        echo "CSV file not found.<br>";
    }
}

// Handle BIN upload
if (isset($_POST['upload_bin']) && isset($_FILES['bin_file'])) {
    if ($_FILES['bin_file']['error'] === 0) {
        $filename = basename($_FILES['bin_file']['name']);
        move_uploaded_file($_FILES['bin_file']['tmp_name'], $imgDir . $filename);
        echo "BIN file uploaded successfully.<br>";
    }
}

// Delete all files in header_images
if (isset($_POST['delete_all'])) {
    $files = glob($imgDir . '*');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    echo "All files deleted.<br>";
}

// Download all files as ZIP
if (isset($_GET['download_all'])) {
    $zip = new ZipArchive();
    $zipName = "header_images.zip";

    if ($zip->open($zipName, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
        $files = glob($imgDir . '*');

        foreach ($files as $file) {
            if (is_file($file)) {
                $zip->addFile($file, basename($file));
            }
        }

        $zip->close();

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $zipName . '"');
        readfile($zipName);
        unlink($zipName);
        exit;
    } else {
        echo "Could not create ZIP.<br>";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<link rel="stylesheet" href="style.css">
</head>
<body>

<h2>Upload CSV (will replace goodVibes.csv)</h2>
<form method="post" enctype="multipart/form-data">
    <input type="file" name="csv_file" accept=".csv" required>
    <button type="submit" name="upload_csv">Upload CSV</button>
</form>

<h2>Download CSV</h2>
<a href="?download_csv=1">Download goodVibes.csv</a>

<hr>

<h2>Upload BIN file (to header_images/)</h2>
<form method="post" enctype="multipart/form-data">
    <input type="file" name="bin_file" required>
    <button type="submit" name="upload_bin">Upload BIN</button>
</form>

<h2>Delete all files in header_images</h2>
<form method="post">
    <button type="submit" name="delete_all" onclick="return confirm('Are you sure?')">Delete All</button>
</form>

<h2>Download all files in header_images</h2>
<a href="?download_all=1">Download as ZIP</a>

<hr>

<h2>CSV Content (goodVibes.csv)</h2>

<?php
if (file_exists($csvFile)) {
    if (($handle = fopen($csvFile, "r")) !== FALSE) {
        echo "<table>";
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            echo "<tr>";
            foreach ($data as $cell) {
                echo "<td>" . htmlspecialchars($cell) . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
        fclose($handle);
    } else {
        echo "Could not read CSV file.";
    }
} else {
    echo "No CSV file found.";
}
?>

<hr>

<h2>Header Images Folder</h2>

<?php
$files = glob($imgDir . '*');
$fileCount = 0;

if ($files !== false) {
    echo "<p><strong>Total files:</strong> " . count($files) . "</p>";
    echo "<ul>";

    foreach ($files as $file) {
        if (is_file($file)) {
            $fileCount++;
            echo "<li>" . htmlspecialchars(basename($file)) . "</li>";
        }
    }

    echo "</ul>";
} else {
    echo "Could not read directory.";
}
?>

</body>
</html>