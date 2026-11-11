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
    <title>Panel de transferencias</title>
    <link rel="stylesheet" href="../assets/css/app.css">
</head>
<body class="panel-body" hidden>
<header class="topbar">
    <div class="topbar-left">
        <strong><?= $h($cfg['brand']) ?></strong>
        <span data-i18n="panel_title"></span>
        <span class="badge" id="country-badge"></span>
    </div>
    <div class="topbar-right">
        <span id="user-name" class="small"></span>
        <button type="button" id="logout-btn" class="btn btn-light btn-sm" data-i18n="logout"></button>
    </div>
</header>

<main class="container wide">
    <section class="stats">
        <div class="stat"><span class="stat-num" id="stat-total">0</span><span data-i18n="stat_total"></span></div>
        <div class="stat stat-pending"><span class="stat-num" id="stat-pending">0</span><span data-i18n="stat_pending"></span></div>
        <div class="stat stat-checked"><span class="stat-num" id="stat-checked">0</span><span data-i18n="stat_checked"></span></div>
    </section>

    <form id="filters" class="card filters">
        <div class="filters-grid">
            <div class="field highlight-field">
                <label for="f-reference" data-i18n="f_reference"></label>
                <input type="search" id="f-reference" name="reference" data-i18n-ph="ph_reference">
            </div>
            <div class="field">
                <label for="f-deposit" data-i18n="f_deposit"></label>
                <input type="search" id="f-deposit" name="deposit_code">
            </div>
            <div class="field">
                <label for="f-dni" data-i18n="f_dni"></label>
                <input type="search" id="f-dni" name="dni">
            </div>
            <div class="field">
                <label for="f-q" data-i18n="f_q"></label>
                <input type="search" id="f-q" name="q">
            </div>
            <div class="field">
                <label for="f-folio" data-i18n="f_folio"></label>
                <input type="search" id="f-folio" name="folio">
            </div>
            <div class="field">
                <label for="f-bank" data-i18n="f_bank"></label>
                <select id="f-bank" name="bank_id"><option value="" data-i18n="all"></option></select>
            </div>
            <div class="field">
                <label for="f-status" data-i18n="f_status"></label>
                <select id="f-status" name="status">
                    <option value="" data-i18n="all"></option>
                    <option value="pending" data-i18n="pending"></option>
                    <option value="checked" data-i18n="checked"></option>
                </select>
            </div>
            <div class="field">
                <label for="f-amount" data-i18n="f_amount"></label>
                <input type="text" id="f-amount" name="amount" inputmode="decimal">
            </div>
            <div class="field">
                <label for="f-from" data-i18n="f_from"></label>
                <input type="date" id="f-from" name="date_from">
            </div>
            <div class="field">
                <label for="f-to" data-i18n="f_to"></label>
                <input type="date" id="f-to" name="date_to">
            </div>
        </div>
        <div class="filters-actions">
            <button type="submit" class="btn btn-primary" data-i18n="search"></button>
            <button type="button" id="clear-btn" class="btn btn-secondary" data-i18n="clear"></button>
            <button type="button" id="export-btn" class="btn btn-light" data-i18n="export"></button>
        </div>
    </form>

    <div id="panel-alert" class="alert alert-error" role="alert" hidden></div>

    <section class="card">
        <div class="table-wrap">
            <table class="table" id="results">
                <thead>
                <tr>
                    <th data-i18n="col_check"></th>
                    <th data-i18n="col_folio"></th>
                    <th data-i18n="col_received"></th>
                    <th data-i18n="col_name"></th>
                    <th data-i18n="col_email"></th>
                    <th data-i18n="col_dni"></th>
                    <th data-i18n="col_deposit"></th>
                    <th data-i18n="col_bank"></th>
                    <th data-i18n="col_date"></th>
                    <th class="num" data-i18n="col_amount"></th>
                    <th data-i18n="col_reference"></th>
                    <th data-i18n="col_receipt"></th>
                    <th data-i18n="col_status"></th>
                </tr>
                </thead>
                <tbody id="results-body"></tbody>
            </table>
        </div>
        <div class="pager">
            <button type="button" class="btn btn-light btn-sm" id="prev-btn">‹</button>
            <span id="page-info" class="small"></span>
            <button type="button" class="btn btn-light btn-sm" id="next-btn">›</button>
        </div>
    </section>
</main>

<dialog id="confirm-dialog" class="dialog">
    <form method="dialog">
        <h3 data-i18n="confirm_title"></h3>
        <p id="confirm-text"></p>
        <p class="small warn" data-i18n="confirm_warn"></p>
        <div class="dialog-actions">
            <button value="cancel" class="btn btn-secondary" data-i18n="cancel"></button>
            <button value="ok" class="btn btn-primary" data-i18n="confirm_ok"></button>
        </div>
    </form>
</dialog>

<script>window.APP = <?= json_encode(['apiBase' => rtrim($cfg['api_base'], '/')], JSON_UNESCAPED_SLASHES) ?>;</script>
<script src="../assets/js/panel.js"></script>
</body>
</html>
