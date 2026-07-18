<?php
/** @var ImageRepository $images */
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

<?php $filenames = $images->listFilenames(); ?>
<p><strong>Total files:</strong> <?= count($filenames) ?></p>
<ul>
    <?php foreach ($filenames as $filename): ?>
        <li><?= e($filename) ?></li>
    <?php endforeach; ?>
</ul>

<?php require __DIR__ . '/partials/footer.php'; ?>
