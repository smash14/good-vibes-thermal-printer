<?php
/**
 * @var QuoteRepository $quotes
 * @var StringsRepository $strings
 * @var ImageRepository $images
 * @var Logfile $logfile
 * @var array{0: string, 1: string}|null $message
 */
?>
<!DOCTYPE html>
<html>
<head>
<link rel="stylesheet" href="style.css">
</head>
<body>

<?php if ($message !== null): ?>
    <p style="color: <?= $message[0] === 'success' ? 'green' : 'red' ?>;"><?= e($message[1]) ?></p>
<?php endif; ?>

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

<h2>Upload Picture (jpg, png, gif, bmp, webp, tiff - max 10MB)</h2>
<p>Converted to the printable format automatically the next time the printer starts.</p>
<form method="post" enctype="multipart/form-data">
    <input type="file" name="image_file" accept=".jpg,.jpeg,.png,.gif,.bmp,.webp,.tif,.tiff" required>
    <button type="submit" name="upload_image">Upload Picture</button>
</form>

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

<?php if ($quotes->exists()): ?>
    <table>
        <tr><th>Index</th><th>Content</th><th>Actions</th></tr>
        <?php foreach ($quotes->all() as $rowIndex => $text): ?>
            <tr>
                <td><?= $rowIndex ?></td>
                <td><pre style="margin: 0; white-space: pre-wrap; word-wrap: break-word;"><?= e($text) ?></pre></td>
                <td>
                    <button type="button" onclick="editRow(<?= $rowIndex ?>, <?= e((string) json_encode($text)) ?>)">Edit</button>
                    <form method="post" style="display: inline-block; margin: 0;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="row_index" value="<?= $rowIndex ?>">
                        <button type="submit" onclick="return confirm('Delete this entry?');">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php else: ?>
    <p>No CSV file found.</p>
<?php endif; ?>

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

<?php $filenames = $images->listFilenames(); ?>
<p><strong>Total files:</strong> <?= count($filenames) ?></p>
<ul>
    <?php foreach ($filenames as $filename): ?>
        <li><?= e($filename) ?></li>
    <?php endforeach; ?>
</ul>

<hr>

<h2>strings.json Preview</h2>

<?php if ($strings->exists()): ?>
    <pre><?= e($strings->prettyContent()) ?></pre>
<?php else: ?>
    <p>No strings.json file found.</p>
<?php endif; ?>

<hr>

<h2>Delete Logfile</h2>
<form method="post">
    <button type="submit" name="delete_logfile" onclick="return confirm('Are you sure you want to delete logfile.log?')">Delete Logfile</button>
</form>

<hr>

<h2>Logfile Content (logfile.log)</h2>

<?php if ($logfile->exists()): ?>
    <pre><?= e($logfile->content()) ?></pre>
<?php else: ?>
    <p>No logfile.log found.</p>
<?php endif; ?>

</body>
</html>
