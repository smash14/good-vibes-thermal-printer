<?php
/**
 * @var string $activePage one of: menu, quotes, images, strings, logfile
 * @var array{0: string, 1: string}|null $message
 */

$navLinks = [
    'menu' => ['index.php', 'Menu'],
    'quotes' => ['quotes.php', 'Quotes'],
    'images' => ['images.php', 'Images'],
    'strings' => ['strings.php', 'Settings'],
    'logfile' => ['logfile.php', 'Logfile'],
];
?>
<!DOCTYPE html>
<html>
<head>
<meta name="color-scheme" content="light">
<link rel="stylesheet" href="style.css">
</head>
<body>

<header>
    <h1>Good Vibes Printer Admin</h1>
    <nav>
        <?php foreach ($navLinks as $page => [$href, $label]): ?>
            <a href="<?= e($href) ?>" class="<?= $page === $activePage ? 'active' : '' ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </nav>
</header>

<?php if (($message ?? null) !== null): ?>
    <p style="color: <?= $message[0] === 'success' ? 'green' : 'red' ?>;"><?= e($message[1]) ?></p>
<?php endif; ?>
