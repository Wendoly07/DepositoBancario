<?php
declare(strict_types=1);
$cfg  = require __DIR__ . '/config.php';
$slug = (string) ($_GET['pais'] ?? '');
if (!isset($cfg['countries'][$slug])) {
    header('Location: index.php');
    exit;
}
date_default_timezone_set($cfg['timezone']);
$country = $cfg['countries'][$slug];
$t = require __DIR__ . '/lang/' . $country['lang'] . '.php';
$h = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$today = date('Y-m-d');

$jsConfig = [
    'apiBase' => rtrim($cfg['api_base'], '/'),
    'country' => $slug,
    'today'   => $today,
    'msgs'    => [
        'required' => $t['v_required'], 'email' => $t['v_email'], 'amount' => $t['v_amount'],
        'date' => $t['v_date'], 'file_size' => $t['v_file_size'], 'file_type' => $t['v_file_type'],
        'ownership' => $t['v_ownership'], 'fix' => $t['v_fix'], 'network' => $t['v_network'],
        'submit' => $t['submit'], 'sending' => $t['sending'], 'none' => $t['q9_none'],
    ],
];
$req = '<span class="req" aria-hidden="true">*</span>';
?>
<!DOCTYPE html>
<html lang="<?= $h($country['lang']) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $h($t['title']) ?> · <?= $h($country['name']) ?></title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
<main class="container narrow">
    <a class="back-link small" href="index.php">← <?= $h($t['back']) ?></a>
    <header class="page-head">
        <span class="badge"><?= $country['flag'] ?> <?= $h($country['name']) ?></span>
        <h1><?= $h($t['title']) ?></h1>
        <p class="muted"><?= $h($t['subtitle']) ?></p>
        <p class="small req-note"><?= $h($t['required_note']) ?></p>
    </header>

    <div id="form-alert" class="alert alert-error" role="alert" hidden></div>

    <form id="transfer-form" class="card form" novalidate enctype="multipart/form-data">
        <input type="hidden" name="country" value="<?= $h($slug) ?>">

        <div class="field">
            <label for="email">1. <?= $h($t['q1']) ?><?= $req ?></label>
            <input type="email" id="email" name="email" maxlength="190" autocomplete="email" required>
            <div class="error" data-error-for="email"></div>
        </div>

        <div class="field">
            <label for="full_name">2. <?= $h($t['q2']) ?><?= $req ?></label>
            <input type="text" id="full_name" name="full_name" maxlength="150" autocomplete="name" required>
            <div class="error" data-error-for="full_name"></div>
        </div>

        <div class="field">
            <label for="dni">3. <?= $h($t['q3']) ?><?= $req ?></label>
            <input type="text" id="dni" name="dni" maxlength="30" required>
            <div class="error" data-error-for="dni"></div>
        </div>

        <div class="field">
            <label for="deposit_code">4. <?= $h($t['q4']) ?><?= $req ?></label>
            <input type="text" id="deposit_code" name="deposit_code" maxlength="60" required>
            <div class="error" data-error-for="deposit_code"></div>
        </div>

        <div class="field">
            <label for="bank_id">5. <?= $h($t['q5']) ?><?= $req ?></label>
            <select id="bank_id" name="bank_id" required disabled>
                <option value=""><?= $h($t['q5_placeholder']) ?></option>
            </select>
            <div id="bank-load-error" class="alert alert-warning small" hidden>
                <?= $h($t['q5_error']) ?>
                <button type="button" id="retry-banks" class="btn btn-link"><?= $h($t['retry']) ?></button>
            </div>
            <div class="error" data-error-for="bank_id"></div>
        </div>

        <div class="field">
            <label for="transfer_date">6. <?= $h($t['q6']) ?><?= $req ?></label>
            <input type="date" id="transfer_date" name="transfer_date" max="<?= $h($today) ?>" required>
            <div class="error" data-error-for="transfer_date"></div>
        </div>

        <div class="field">
            <label for="amount">7. <?= $h($t['q7']) ?><?= $req ?></label>
            <p class="help"><?= $h($t['q7_help']) ?></p>
            <div class="input-prefix">
                <span><?= $h($country['symbol']) ?></span>
                <input type="text" id="amount" name="amount" inputmode="decimal" placeholder="0.00" maxlength="18" required>
            </div>
            <div class="error" data-error-for="amount"></div>
        </div>

        <div class="field">
            <label for="reference_number">8. <?= $h($t['q8']) ?><?= $req ?></label>
            <p class="help"><?= $h($t['q8_help']) ?></p>
            <input type="text" id="reference_number" name="reference_number" maxlength="80" required>
            <div class="error" data-error-for="reference_number"></div>
        </div>

        <div class="field">
            <span class="label">9. <?= $h($t['q9']) ?><?= $req ?></span>
            <div class="file-box">
                <label for="receipt" class="btn btn-secondary"><?= $h($t['q9_button']) ?></label>
                <input type="file" id="receipt" name="receipt" accept="application/pdf,image/*" class="visually-hidden" required>
                <span id="file-name" class="muted small"><?= $h($t['q9_none']) ?></span>
            </div>
            <p class="help"><?= $h($t['q9_help']) ?></p>
            <div class="error" data-error-for="receipt"></div>
        </div>

        <div class="field">
            <span class="label">10. <?= $h($t['q10']) ?><?= $req ?></span>
            <label class="checkbox">
                <input type="checkbox" id="ownership" name="ownership" value="1" required>
                <strong><?= $h($t['q10_text']) ?></strong>
            </label>
            <div class="error" data-error-for="ownership"></div>
        </div>

        <button type="submit" class="btn btn-primary btn-block" id="submit-btn"><?= $h($t['submit']) ?></button>
    </form>

    <section id="success-panel" class="card success" hidden>
        <div class="success-icon">✔</div>
        <h2><?= $h($t['success_title']) ?></h2>
        <p><?= $h($t['success_text']) ?></p>
        <p class="folio" id="success-folio"></p>
        <button type="button" class="btn btn-secondary" id="another-btn"><?= $h($t['another']) ?></button>
    </section>
</main>
<script>window.APP = <?= json_encode($jsConfig, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<script src="assets/js/form.js"></script>
</body>
</html>
