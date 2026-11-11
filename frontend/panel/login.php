<?php
declare(strict_types=1);
$cfg = require __DIR__ . '/../config.php';
$h = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Panel de transferencias · Sign in</title>
    <link rel="stylesheet" href="../assets/css/app.css">
</head>
<body class="login-body">
<main class="login-wrap">
    <form id="login-form" class="card form login-card" novalidate>
        <h1><?= $h($cfg['brand']) ?></h1>
        <p class="muted">Panel de transferencias · Transfers panel</p>
        <div id="login-alert" class="alert alert-error" role="alert" hidden></div>
        <div class="field">
            <label for="username">Usuario · Username</label>
            <input type="text" id="username" name="username" autocomplete="username" required autofocus>
        </div>
        <div class="field">
            <label for="password">Contraseña · Password</label>
            <input type="password" id="password" name="password" autocomplete="current-password" required>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Ingresar · Sign in</button>
        <p class="small muted center">Cada usuario sólo ve la información de su país.<br>Each user only sees data from their own country.</p>
    </form>
</main>
<script>window.APP = <?= json_encode(['apiBase' => rtrim($cfg['api_base'], '/')], JSON_UNESCAPED_SLASHES) ?>;</script>
<script src="../assets/js/login.js"></script>
</body>
</html>
