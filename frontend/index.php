<?php
declare(strict_types=1);
$cfg = require __DIR__ . '/config.php';
$h = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $h($cfg['brand']) ?> · Transferencias / Transfers</title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
<main class="container narrow">
    <header class="page-head">
        <h1><?= $h($cfg['brand']) ?></h1>
        <p class="muted">Seleccioná tu país · Select your country</p>
    </header>
    <div class="country-grid">
        <?php foreach ($cfg['countries'] as $slug => $c): ?>
            <a class="country-card" href="formulario.php?pais=<?= $h($slug) ?>">
                <span class="flag"><?= $c['flag'] ?></span>
                <span class="country-name"><?= $h($c['name']) ?></span>
                <span class="muted small"><?= $c['lang'] === 'es' ? 'Español' : 'English' ?></span>
            </a>
        <?php endforeach; ?>
    </div>
    <p class="center small"><a href="panel/login.php">Acceso al panel · Panel sign-in</a></p>
</main>
</body>
</html>
