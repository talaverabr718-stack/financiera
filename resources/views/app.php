<?php

use Illuminate\Foundation\Vite;

$vite = app(Vite::class);
$encodedPage = json_encode($page, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
$appearance = is_array($page['props']['appearance'] ?? null) ? $page['props']['appearance'] : [];
$theme = (($appearance['theme'] ?? 'day') === 'night') ? 'night' : 'day';
?>
<!DOCTYPE html>
<html lang="es" class="theme-<?= e($theme) ?>" data-theme="<?= e($theme) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title inertia>Financiera</title>
    <?= $vite(['resources/css/app.css', 'resources/css/sv-shell.css', 'resources/js/app.js']) ?>
</head>
<body>
    <script data-page="app" type="application/json"><?= $encodedPage ?></script>
    <div id="app"></div>
</body>
</html>
