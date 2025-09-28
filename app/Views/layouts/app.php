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
      background:#0b0f14; color:#e6edf3; width:320px;
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
      <button class="navbtn" id="btn-game-data">Game Data</button>
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

  const uuidInput = $('#fetchUUID');
  const cuidInput = $('#fetchCUID');
  const uuidBtn   = $('#btnFetchUUID');
  const cuidBtn   = $('#btnFetchCUID');

  // Use CI helpers so URLs work with/without index.php
  const URL_UUID = '<?= site_url('uuid') ?>';
  const URL_CUID = '<?= site_url('contentuid') ?>';

  // UUID v4 and ContentUID patterns (be liberal for ContentUID)
  const reUUID    = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;
  const reCUID    = /^h[0-9a-f]{32}$/i;          // h + 32 hex (common)
  const reCUIDAlt = /^h[0-9a-f-]{33,36}$/i;      // allow variants (33–36 incl. hyphens)

  function kindOf(v){
    if (!v) return null;
    if (reUUID.test(v)) return 'uuid';
    if (reCUID.test(v) || reCUIDAlt.test(v)) return 'cuid';
    return null;
  }

  function setVal(inp, val){
    if (!inp) return;
    inp.value = val;
    inp.dataset.kind = kindOf(val) || '';
  }

  async function requestId(url){
    const r = await fetch(url + '?format=json', { headers: { 'Accept': 'application/json' } });
    if (!r.ok) throw new Error('HTTP ' + r.status);
    const j = await r.json();
    return j.id || j.ID || '';
  }

  async function prefill(){
    try { if (uuidInput) setVal(uuidInput, await requestId(URL_UUID)); } catch(_) {}
    try { if (cuidInput) setVal(cuidInput, await requestId(URL_CUID)); } catch(_) {}
  }

  function enableClickToCopy(inp){
    if (!inp) return;
    inp.style.cursor = 'copy';
    inp.title = 'Click to copy';
    inp.addEventListener('click', async () => {
      try {
        await navigator.clipboard.writeText(inp.value);
        inp.classList.add('copied');
        setTimeout(() => inp.classList.remove('copied'), 600);
      } catch (_) {}
    });
  }

  // Prefill both on load
  prefill();

  // Click-to-copy on inputs
  enableClickToCopy(uuidInput);
  enableClickToCopy(cuidInput);

  // Regenerate on button click (also auto-copy & broadcast)
  uuidBtn?.addEventListener('click', async () => {
    try {
      const v = await requestId(URL_UUID);
      setVal(uuidInput, v);
      try { await navigator.clipboard.writeText(v); } catch(_){}
      document.dispatchEvent(new CustomEvent('app:fetch-uuid', { detail: { value: v } }));
    } catch (e) { console.error(e); }
  });

  cuidBtn?.addEventListener('click', async () => {
    try {
      const v = await requestId(URL_CUID);
      setVal(cuidInput, v);
      try { await navigator.clipboard.writeText(v); } catch(_){}
      document.dispatchEvent(new CustomEvent('app:fetch-contentuuid', { detail: { value: v } }));
    } catch (e) { console.error(e); }
  });

  // Press Enter in either input to broadcast whatever value is present (any length)
  function broadcastOnEnter(inp, evt) {
    inp?.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        const v = (inp.value || '').trim();
        if (v) document.dispatchEvent(new CustomEvent(evt, { detail: { value: v } }));
      }
    });
  }
  broadcastOnEnter(uuidInput, 'app:fetch-uuid');
  broadcastOnEnter(cuidInput, 'app:fetch-contentuuid');
})();
</script>

<script>
  // Attach click to your existing function (defined in the big IIFE below)
  document.getElementById('btn-game-data')
    .addEventListener('click', () => window.toggleMongoPopup && window.toggleMongoPopup());
</script>

<script src="/js/filesSectionAutoHeight.js"></script>
<script src="/js/mongoPopup.js"></script>

<?= $this->renderSection('scripts') ?>
</body>
</html>

