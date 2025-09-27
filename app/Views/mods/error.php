<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= esc($pageTitle ?? 'Error') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body { background:#0b0f14; color:#e6edf3; font-family:system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, Cantarell, Noto Sans, Arial; }
    .wrap { max-width:760px; margin:15vh auto 0; padding:1rem; }
    .card { background:#0d1117; border:1px solid #21262d; border-radius:.5rem; padding:1rem; }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <h1 style="margin-top:0">Not Found</h1>
      <p><?= esc($message ?? 'The requested resource could not be found.') ?></p>
    </div>
  </div>
</body>
</html>

