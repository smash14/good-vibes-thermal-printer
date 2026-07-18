<?php
/** @var Logfile $logfile */
require __DIR__ . '/partials/header.php';
?>

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

<?php require __DIR__ . '/partials/footer.php'; ?>
