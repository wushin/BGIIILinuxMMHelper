<?php
helper('url');
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= esc($pageTitle ?? 'File') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="<?= base_url('styles.css') ?>" rel="stylesheet">
  <style>
    body { background:#0b0f14; color:#e6edf3; }
    .wrap { max-width:1000px; margin:2rem auto; padding:1rem; }
    pre { background:#0d1117; border:1px solid #21262d; border-radius:.5rem; padding:1rem; }
    img.dynImg { max-width:100%; height:auto; display:block; }
  </style>
</head>
<body>
  <div class="wrap">
    <h1><?= esc($root) ?> / <?= esc($slug) ?> / <?= esc($path) ?></h1>
    <?php if (($kind ?? '') === 'image' && !empty($result['dataUri'])): ?>
      <img class="dynImg" src="<?= esc($result['dataUri']) ?>" alt="preview">
    <?php else: ?>
      <pre><code><?= esc(is_string($raw ?? '') ? $raw : json_encode($result ?? [], JSON_PRETTY_PRINT)) ?></code></pre>
    <?php endif; ?>
  </div>
</body>
</html>

