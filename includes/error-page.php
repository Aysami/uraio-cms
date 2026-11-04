<?php
function render_error_page(int $code, string $title, string $message): void
{
    $brand = app_url('index.php');
    ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · Uraio Business OS</title>
<link rel="stylesheet" href="<?= e(asset_url('css/app.css')) ?>">
</head>
<body class="error-body">
<main class="error-card">
  <a class="brand brand-center" href="<?= e($brand) ?>"><span class="brand-mark">U</span><span>Uraio</span></a>
  <p class="error-code"><?= (int) $code ?></p>
  <h1><?= e($title) ?></h1>
  <p><?= e($message) ?></p>
  <div class="error-actions">
    <a class="btn btn-primary" href="<?= e(app_url('app/dashboard.php')) ?>">Go to dashboard</a>
    <a class="btn btn-ghost" href="<?= e($brand) ?>">Back to home</a>
  </div>
</main>
</body>
</html>
    <?php
}
