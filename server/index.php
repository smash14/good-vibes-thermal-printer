<?php
$csvFile = "goodVibes.csv";
$imgDir = "header_images/";
$stringsFile = "strings.json";
$stringsDefaultFile = "strings_default.json";

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

// Handle strings.json upload
if (isset($_POST['upload_strings']) && isset($_FILES['strings_file'])) {
    if ($_FILES['strings_file']['error'] === 0) {
        move_uploaded_file($_FILES['strings_file']['tmp_name'], $stringsFile);
        echo "strings.json uploaded successfully.<br>";
    }
}

// Handle strings.json download
if (isset($_GET['download_strings'])) {
    if (file_exists($stringsFile)) {
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="strings.json"');
        readfile($stringsFile);
        exit;
    } else {
        echo "strings.json file not found.<br>";
    }
}

// Handle restore to default
if (isset($_POST['restore_strings'])) {
    if (file_exists($stringsDefaultFile)) {
        copy($stringsDefaultFile, $stringsFile);
        echo "strings.json restored to default.<br>";
    } else {
        echo "Default strings.json file not found.<br>";
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

<h2>Upload strings.json (will replace strings.json)</h2>
<form method="post" enctype="multipart/form-data">
    <input type="file" name="strings_file" accept=".json" required>
    <button type="submit" name="upload_strings">Upload strings.json</button>
</form>

<h2>Download strings.json</h2>
<a href="?download_strings=1">Download strings.json</a>

<h2>Restore strings.json to Default</h2>
<form method="post">
    <button type="submit" name="restore_strings" onclick="return confirm('Are you sure you want to restore to default?')">Restore to Default</button>
</form>

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

<hr>

<h2>strings.json Preview</h2>

<?php
if (file_exists($stringsFile)) {
    $jsonContent = file_get_contents($stringsFile);
    $jsonPretty = json_encode(json_decode($jsonContent, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    echo "<pre>" . htmlspecialchars($jsonPretty) . "</pre>";
} else {
    echo "No strings.json file found.";
}
?>

</body>
</html>