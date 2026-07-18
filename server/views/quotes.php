<?php
/** @var QuoteRepository $quotes */
require __DIR__ . '/partials/header.php';
?>

<h2>Upload CSV (will replace goodVibes.csv)</h2>
<form method="post" enctype="multipart/form-data">
    <input type="file" name="csv_file" accept=".csv" required>
    <button type="submit" name="upload_csv">Upload CSV</button>
</form>

<h2>Download CSV</h2>
<a href="?download_csv=1">Download goodVibes.csv</a>

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

<?php require __DIR__ . '/partials/footer.php'; ?>
