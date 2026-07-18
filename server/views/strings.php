<?php
/** @var StringsRepository $strings */
require __DIR__ . '/partials/header.php';
?>

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

<h2>strings.json Preview</h2>

<?php if ($strings->exists()): ?>
    <pre><?= e($strings->prettyContent()) ?></pre>
<?php else: ?>
    <p>No strings.json file found.</p>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
