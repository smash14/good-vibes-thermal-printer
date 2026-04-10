<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', sys_get_temp_dir() . '/php_error.log');

$csvFile = "goodVibes.csv";
$imgDir = "header_images/";
$stringsFile = "strings.json";
$stringsDefaultFile = "strings_default.json";
$maxLineLength = 42; // Maximum characters per line (configurable)

// Function to format quotes with proper line breaks
function formatQuoteLineLength($text, $maxLength) {
    $lines = explode("\n", $text);
    $result = [];
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (strlen($line) <= $maxLength) {
            $result[] = $line;
        } else {
            // Need to break this line
            while (strlen($line) > $maxLength) {
                // Find the last space before maxLength
                $substring = substr($line, 0, $maxLength);
                $lastSpace = strrpos($substring, ' ');
                
                if ($lastSpace === false) {
                    // No space found, add the whole thing (can't break word)
                    $result[] = $line;
                    break;
                } else {
                    // Add the part up to the last space
                    $result[] = substr($line, 0, $lastSpace);
                    $line = trim(substr($line, $lastSpace));
                }
            }
            if (!empty($line)) {
                $result[] = $line;
            }
        }
    }
    
    return implode("\n", $result);
}

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

// Handle CSV row operations (add, edit, delete)
if (isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'add' && isset($_POST['new_entry'])) {
        // Add new row
        $newEntry = $_POST['new_entry'];
        if (!empty($newEntry)) {
            $newEntry = formatQuoteLineLength($newEntry, $maxLineLength);
            $fp = fopen($csvFile, 'a');
            fputcsv($fp, [$newEntry]);
            fclose($fp);
            echo "<p style='color: green;'>Entry added successfully!</p>";
        }
    } 
    elseif ($action === 'delete' && isset($_POST['row_index'])) {
        // Delete row by index
        $rowIndex = (int)$_POST['row_index'];
        if (file_exists($csvFile)) {
            $rows = [];
            if (($handle = fopen($csvFile, 'r')) !== FALSE) {
                while (($data = fgetcsv($handle, 0, ",")) !== FALSE) {
                    $rows[] = $data;
                }
                fclose($handle);
            }
            
            if (isset($rows[$rowIndex])) {
                unset($rows[$rowIndex]);
                $rows = array_values($rows); // Re-index array
                
                // Write back to CSV
                $fp = fopen($csvFile, 'w');
                foreach ($rows as $row) {
                    fputcsv($fp, $row);
                }
                fclose($fp);
                echo "<p style='color: green;'>Entry deleted successfully!</p>";
            }
        }
    }
    elseif ($action === 'update' && isset($_POST['row_index']) && isset($_POST['updated_entry'])) {
        // Update row by index
        $rowIndex = (int)$_POST['row_index'];
        $updatedEntry = $_POST['updated_entry'];
        
        if (file_exists($csvFile)) {
            $rows = [];
            if (($handle = fopen($csvFile, 'r')) !== FALSE) {
                while (($data = fgetcsv($handle, 0, ",")) !== FALSE) {
                    $rows[] = $data;
                }
                fclose($handle);
            }
            
            if (isset($rows[$rowIndex]) && !empty($updatedEntry)) {
                $updatedEntry = formatQuoteLineLength($updatedEntry, $maxLineLength);
                $rows[$rowIndex] = [$updatedEntry];
                
                // Write back to CSV
                $fp = fopen($csvFile, 'w');
                foreach ($rows as $row) {
                    fputcsv($fp, $row);
                }
                fclose($fp);
                echo "<p style='color: green;'>Entry updated successfully!</p>";
            }
        }
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

// Delete logfile
if (isset($_POST['delete_logfile'])) {
    if (file_exists('logfile.log')) {
        unlink('logfile.log');
        echo "Logfile deleted.<br>";
    } else {
        echo "Logfile does not exist.<br>";
    }
}

// Download all files as ZIP
if (isset($_GET['download_all'])) {
    try {
        // Check if ZipArchive is available
        if (!extension_loaded('zip')) {
            throw new Exception("ZIP extension is not installed on this server.");
        }

        if (!is_dir($imgDir)) {
            throw new Exception("Image directory does not exist: " . realpath($imgDir));
        }

        $files = glob($imgDir . '*');
        error_log("DEBUG: glob returned: " . var_export($files, true));
        
        if ($files === false) {
            throw new Exception("Could not read directory.");
        }

        if (empty($files)) {
            throw new Exception("No files found in header_images directory.");
        }

        $zip = new ZipArchive();
        $tempDir = sys_get_temp_dir();
        $zipName = $tempDir . '/header_images_' . time() . '.zip';
        error_log("DEBUG: Creating ZIP at: " . $zipName);

        if (!$zip->open($zipName, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
            throw new Exception("Could not create ZIP archive at " . $zipName);
        }

        foreach ($files as $file) {
            if (is_file($file)) {
                $localName = basename($file);
                error_log("DEBUG: Adding file: " . $file . " as " . $localName);
                if (!$zip->addFile($file, $localName)) {
                    throw new Exception("Could not add file to ZIP: " . $localName);
                }
            }
        }

        if (!$zip->close()) {
            throw new Exception("Could not close ZIP archive.");
        }

        if (!file_exists($zipName)) {
            throw new Exception("ZIP file was not created properly at: " . $zipName);
        }

        $fileSize = filesize($zipName);
        error_log("DEBUG: ZIP file created, size: " . $fileSize);

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="header_images.zip"');
        header('Content-Length: ' . $fileSize);
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        ob_clean();
        flush();
        
        if (!readfile($zipName)) {
            error_log("Failed to read ZIP file: " . $zipName);
        }

        @unlink($zipName);
        exit;
    } catch (Exception $e) {
        error_log("ZIP Download Error: " . $e->getMessage());
        http_response_code(400);
        header('Content-Type: text/plain');
        echo "Download error: " . htmlspecialchars($e->getMessage()) . "\n\n";
        echo "Debug info:\n";
        echo "- Image directory: " . $imgDir . "\n";
        echo "- Directory exists: " . (is_dir($imgDir) ? "yes" : "no") . "\n";
        echo "- ZIP extension loaded: " . (extension_loaded('zip') ? "yes" : "no") . "\n";
        exit;
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

<h3>Add New Entry</h3>
<form method="post" style="margin-bottom: 20px;">
    <textarea name="new_entry" placeholder="Enter new quote..." style="width: 100%; height: 80px; padding: 8px; border-radius: 4px; border: 1px solid #ddd; font-family: Arial, sans-serif;" required></textarea>
    <button type="submit" name="action" value="add" style="margin-top: 10px;">Add Entry</button>
</form>

<?php
if (file_exists($csvFile)) {
    if (($handle = fopen($csvFile, "r")) !== FALSE) {
        echo "<table>";
        echo "<tr><th>Index</th><th>Content</th><th>Actions</th></tr>";
        $rowIndex = 0;
        while (($data = fgetcsv($handle, 0, ",")) !== FALSE) {
            $rawContent = isset($data[0]) ? $data[0] : '';
            // Replace literal \n with actual newlines for display and functionality
            $rawContent = str_replace('\\n', "\n", $rawContent);
            $content = htmlspecialchars($rawContent);
            $jsonContent = htmlspecialchars(json_encode($rawContent), ENT_QUOTES, 'UTF-8');
            echo "<tr>";
            echo "<td>" . $rowIndex . "</td>";
            echo "<td><pre style='margin: 0; white-space: pre-wrap; word-wrap: break-word;'>" . $content . "</pre></td>";
            echo "<td>";
            echo "<button type='button' onclick='editRow(" . $rowIndex . ", " . $jsonContent . ")'>Edit</button> ";
            echo "<form method='post' style='display: inline-block; margin: 0;'>";
            echo "<input type='hidden' name='action' value='delete'>";
            echo "<input type='hidden' name='row_index' value='" . $rowIndex . "'>";
            echo "<button type='submit' onclick=\"return confirm('Delete this entry?');\">Delete</button>";
            echo "</form>";
            echo "</td>";
            echo "</tr>";
            $rowIndex++;
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

<!-- Edit Modal -->
<div id="editModal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4);">
    <div style="background-color: white; margin: 10% auto; padding: 20px; border-radius: 6px; width: 90%; max-width: 600px; box-shadow: 0 4px 8px rgba(0,0,0,0.2);">
        <span style="color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer;" onclick="closeEditModal()">&times;</span>
        <h3>Edit Entry</h3>
        <form method="post">
            <input type="hidden" name="action" value="update">
            <input type="hidden" id="editRowIndex" name="row_index" value="">
            <textarea id="editEntryContent" name="updated_entry" style="width: 100%; height: 100px; padding: 8px; border-radius: 4px; border: 1px solid #ddd; font-family: Arial, sans-serif;"></textarea>
            <div style="margin-top: 15px;">
                <button type="submit" style="background-color: #27ae60; margin-right: 10px;">Save Changes</button>
                <button type="button" onclick="closeEditModal()" style="background-color: #95a5a6;">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function editRow(rowIndex, content) {
    document.getElementById('editRowIndex').value = rowIndex;
    document.getElementById('editEntryContent').value = content;
    document.getElementById('editModal').style.display = 'block';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

window.onclick = function(event) {
    var modal = document.getElementById('editModal');
    if (event.target === modal) {
        modal.style.display = 'none';
    }
}
</script>

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

<hr>

<h2>Delete Logfile</h2>
<form method="post">
    <button type="submit" name="delete_logfile" onclick="return confirm('Are you sure you want to delete logfile.log?')">Delete Logfile</button>
</form>

<hr>

<h2>Logfile Content (logfile.log)</h2>

<?php
if (file_exists('logfile.log')) {
    $logContent = file_get_contents('logfile.log');
    echo "<pre>" . htmlspecialchars($logContent) . "</pre>";
} else {
    echo "No logfile.log found.";
}
?>

</body>
</html>