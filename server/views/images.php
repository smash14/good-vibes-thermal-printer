<?php
/**
 * @var ImageRepository $images
 * @var array{imgDir: string} $config
 */
require __DIR__ . '/partials/header.php';

$pendingReview = $images->getPendingReview();
?>

<?php if ($pendingReview !== null): ?>
    <h2>Review &amp; Adjust: <?= e($pendingReview['stem']) ?></h2>
    <?php if ($pendingReview['hasPreview']): ?>
        <img src="<?= e($config['imgDir'] . '.review/' . $pendingReview['stem'] . '.jpg') ?>" alt="Preview" style="max-width: 300px; display: block; margin-bottom: 10px;">
    <?php else: ?>
        <p>Preview not available.</p>
    <?php endif; ?>

    <form method="post">
        <label for="rotation">Rotation (use for wide/landscape images)</label><br>
        <select id="rotation" name="rotation">
            <?php foreach ([0 => 'No rotation', 90 => '90° clockwise', 180 => '180°', 270 => '90° counter-clockwise'] as $degrees => $label): ?>
                <option value="<?= $degrees ?>" <?= $pendingReview['rotation'] === $degrees ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <br><br>
        <label>
            <input type="checkbox" name="auto_contrast" <?= $pendingReview['autoContrast'] ? 'checked' : '' ?>>
            Auto-contrast (stretch to full black/white range - good one-click fix for flat/washed-out photos)
        </label>
        <br><br>
        <label for="brightness">Brightness (0.5&ndash;2.0, 1.0 = unchanged)</label><br>
        <input type="number" id="brightness" name="brightness" step="0.1" min="0.5" max="2.0" value="<?= e((string) $pendingReview['brightness']) ?>">
        <br><br>
        <label for="contrast">Contrast (0.5&ndash;2.0, 1.0 = unchanged)</label><br>
        <input type="number" id="contrast" name="contrast" step="0.1" min="0.5" max="2.0" value="<?= e((string) $pendingReview['contrast']) ?>">
        <br><br>
        <label>
            <input type="checkbox" name="use_threshold" <?= $pendingReview['threshold'] !== null ? 'checked' : '' ?>>
            Use fixed threshold instead of automatic dithering
        </label>
        <br>
        <label for="threshold">Threshold (0&ndash;255)</label><br>
        <input type="number" id="threshold" name="threshold" min="0" max="255" value="<?= e((string) ($pendingReview['threshold'] ?? 128)) ?>">
        <br><br>
        <button type="submit" name="action" value="regenerate_preview">Regenerate Preview</button>
    </form>

    <form method="post" style="display: inline-block; margin-right: 10px;">
        <button type="submit" name="action" value="save_review">Save Image</button>
    </form>
    <form method="post" style="display: inline-block;">
        <button type="submit" name="action" value="discard_review" onclick="return confirm('Discard this staged image?')">Discard</button>
    </form>
    <p style="color: #777; font-size: 13px;">If an image with this name already exists, saving will keep both (e.g. as "<?= e($pendingReview['stem']) ?>_2") instead of overwriting it.</p>
<?php else: ?>
    <h2>Upload Picture (jpg, png, gif, bmp, webp, tiff - max 10MB)</h2>
    <p>You'll be able to preview the converted result and adjust contrast/threshold before it's saved.</p>
    <form method="post" enctype="multipart/form-data">
        <input type="file" name="image_file" accept=".jpg,.jpeg,.png,.gif,.bmp,.webp,.tif,.tiff" required>
        <button type="submit" name="upload_image">Upload Picture</button>
    </form>
<?php endif; ?>

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
                <p class="image-card-placeholder">Preview pending update</p>
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
