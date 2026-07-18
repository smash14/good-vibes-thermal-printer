<?php
/** @var StringsRepository $strings */
require __DIR__ . '/partials/header.php';
?>

<h2>Settings</h2>

<?php if ($strings->exists()): ?>
    <?php foreach ($strings->getEntries() as $key => $entry): ?>
        <?php
            $text = $entry['text'] ?? '';
            $displayValue = is_array($text) ? implode("\n", $text) : (string) $text;
            $rows = max(2, substr_count($displayValue, "\n") + 1);
        ?>
        <form method="post" style="margin-bottom: 20px;">
            <label for="entry_<?= e($key) ?>"><strong><?= e($key) ?></strong></label>
            <?php if (!empty($entry['description'])): ?>
                <p style="margin: 4px 0; color: #666; font-size: 0.9em;"><?= e($entry['description']) ?></p>
            <?php endif; ?>
            <textarea id="entry_<?= e($key) ?>" name="entry_value" rows="<?= $rows ?>" style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #ddd; font-family: Arial, sans-serif;"><?= e($displayValue) ?></textarea>
            <input type="hidden" name="entry_key" value="<?= e($key) ?>">
            <button type="submit" name="action" value="update_entry" style="margin-top: 6px;">Save</button>
        </form>
    <?php endforeach; ?>
<?php else: ?>
    <p>No strings.json file found.</p>
<?php endif; ?>

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

<h2>strings.json Preview</h2>

<?php if ($strings->exists()): ?>
    <pre><?= e($strings->prettyContent()) ?></pre>
<?php else: ?>
    <p>No strings.json file found.</p>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
