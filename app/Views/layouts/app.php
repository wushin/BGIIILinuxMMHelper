<?php
helper('url');
$title = $title ?? 'BG3 Linux Helper';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= esc($title) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="<?= base_url('/css/styles.css') ?>" rel="stylesheet">
  <link href="<?= base_url('/css/searchPopup.css') ?>" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/github-markdown-css@latest/github-markdown.min.css" media="print" onload="this.media='all'">
  <noscript><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/github-markdown-css@latest/github-markdown.min.css"></noscript>

  <style>
    :root { --bg:#0b0f14; --panel:#0d1117; --border:#21262d; --text:#e6edf3; --muted:#8b949e; --header-h: 60px; }
    body { background:var(--bg); color:var(--text); margin:0; }
    a { color:#58a6ff; text-decoration:none; }

    header { height:var(--header-h); display:flex; align-items:center; background:var(--panel);
             border-bottom:1px solid var(--border); padding:0 12px; box-sizing:border-box; }
    .hdr-wrap { margin:0 auto; width:100%; display:grid; grid-template-columns: 1fr auto 1fr; align-items:center; gap:12px; }
    .hdr-left  { display:flex; gap:8px; align-items:center; }
    .hdr-mid   { text-align:center; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .hdr-right { display:flex; gap:8px; justify-content:flex-end; align-items:center; }

    .btn, .navbtn {
      display:inline-flex; gap:6px; align-items:center; justify-content:center;
      padding:.35rem .6rem; border-radius:.35rem; border:1px solid var(--border);
      background:#0c1320; color:#e6edf3; cursor:pointer; user-select:none; text-decoration:none;
    }
    .navbtn.active, .navbtn:hover { background:#161b22; }
    .input {
      height:32px; padding:0 .5rem; border-radius:.35rem; border:1px solid var(--border);
      background:#0b0f14; color:#e6edf3; width:220px;
    }
    main { margin:0 auto; padding:12px; }
    footer { padding:.75rem 1rem; background:var(--panel); border-top:1px solid var(--border); margin-top:24px; }
    .muted { color:var(--muted); }
  </style>
  <?= $this->renderSection('head') ?>
</head>
<body>

<header>
  <div class="hdr-wrap">
    <!-- LEFT: Nav -->
    <div class="hdr-left">
      <a class="navbtn <?= (uri_string()==='')?'active':'' ?>" href="<?= site_url('/') ?>">Home</a>
      <a class="navbtn <?= str_starts_with(uri_string(),'mods')?'active':'' ?>" href="<?= site_url('mods/MyMods/') ?>">Mods</a>
      <a class="navbtn <?= (uri_string()==='mods/GameData')?'active':'' ?>" href="<?= site_url('mods/GameData') ?>">GameData</a>
    </div>

    <!-- MIDDLE: Title (page supplies $title) -->
    <div class="hdr-mid">
      <strong><?= esc($title) ?></strong>
    </div>

    <!-- RIGHT: Widgets + Settings -->
    <div class="hdr-right">
      <input id="fetchUUID" class="input" type="text" placeholder="Fetch UUID">
      <button id="btnFetchUUID" class="btn" type="button">Fetch</button>

      <input id="fetchCUID" class="input" type="text" placeholder="Fetch Content UUID">
      <button id="btnFetchCUID" class="btn" type="button">Fetch</button>

      <a class="btn" href="<?= site_url('settings') ?>">Settings</a>
    </div>
  </div>
</header>

<main>
  <?= $this->renderSection('content') ?>
</main>

<footer>
  <div class="wrap">© <?= date('Y') ?> BG3 Linux Helper</div>
</footer>

<script>
(function(){
  const $ = s => document.querySelector(s);
  const fire = (name, value) => document.dispatchEvent(new CustomEvent(name, { detail: { value } }));

  const uuidInput = $('#fetchUUID');
  const uuidBtn   = $('#btnFetchUUID');
  const cuidInput = $('#fetchCUID');
  const cuidBtn   = $('#btnFetchCUID');

  function hookup(input, btn, eventName) {
    if (!input || !btn) return;
    btn.addEventListener('click', () => {
      const v = input.value.trim();
      if (v) fire(eventName, v);
    });
    input.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        const v = input.value.trim();
        if (v) fire(eventName, v);
      }
    });
  }

  hookup(uuidInput, uuidBtn, 'app:fetch-uuid');
  hookup(cuidInput, cuidBtn, 'app:fetch-contentuuid');
})();
</script>

<script src="<?= base_url('/js/searchPopup.js') ?>"></script>
<script src="<?= base_url('/js/bg3.js') ?>"></script>

<?= $this->renderSection('scripts') ?>
</body>
</html>

