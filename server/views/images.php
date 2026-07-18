<?php
/**
 * @var ImageRepository $images
 * @var array{imgDir: string} $config
 */
require __DIR__ . '/partials/header.php';
?>

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

<h2>Header Images Folder</h2>

<?php $entries = $images->listEntries(); ?>
<p><strong>Total images:</strong> <?= count($entries) ?></p>
<div class="image-grid">
    <?php foreach ($entries as $entry): ?>
        <div class="image-card">
            <?php if ($entry['thumbnail'] !== null): ?>
                <img src="<?= e($config['imgDir'] . $entry['thumbnail']) ?>" alt="<?= e($entry['stem']) ?>">
            <?php elseif ($entry['kind'] === 'converted'): ?>
                <p class="image-card-placeholder">Preview pending next restart</p>
            <?php else: ?>
                <p class="image-card-placeholder">No preview available</p>
            <?php endif; ?>
            <p class="image-card-stem"><?= e($entry['stem']) ?></p>
            <?php if ($entry['kind'] === 'pending'): ?>
                <p class="image-card-status">Pending conversion</p>
            <?php endif; ?>
            <form method="post">
                <input type="hidden" name="delete_entry" value="1">
                <input type="hidden" name="stem" value="<?= e($entry['stem']) ?>">
                <button type="submit" onclick="return confirm('Delete this image?')">Delete</button>
            </form>
        </div>
    <?php endforeach; ?>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
