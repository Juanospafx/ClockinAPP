<?php
declare(strict_types=1);
?>
<?php
$script = $_SERVER['SCRIPT_NAME'] ?? '';
$baseRoot = preg_replace('#/pages/.*$#', '', $script);
if ($baseRoot === null || $baseRoot === $script) {
    $baseRoot = rtrim(dirname($script), '/');
}
$baseRoot = $baseRoot === '' ? '' : $baseRoot;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title ?? 'ClockinAPP', ENT_QUOTES) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($baseRoot, ENT_QUOTES) ?>/assets/css/global_login.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($baseRoot, ENT_QUOTES) ?>/assets/css/registros.css">
</head>
<body>
<main class="container py-4">
