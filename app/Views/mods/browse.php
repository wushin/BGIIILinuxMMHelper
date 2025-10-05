<?php
helper('url');
$root   = $root ?? '';
$slug   = $slug ?? '';
$tree   = $tree ?? [];
$path   = $path ?? '';
$myMods = $myMods ?? [];

/** Render recursive tree nodes */
function renderTree(array $nodes, ?string $selectedRel = '', int $depth = 0) {
    foreach ($nodes as $n) {
        $indent = $depth * 14;
        $isDir  = !empty($n['isDir']);
        $name   = $n['name'] ?? '';
        $rel    = $n['rel']  ?? '';
        $ext    = $n['ext']  ?? '';

        $cls = $isDir ? 'node dir' : 'node file ext-'.htmlspecialchars($ext);
        $isActive = (!$isDir && $selectedRel !== '' && $rel === $selectedRel);
        if ($isActive) { $cls .= ' active'; }
        echo '<div class="'.$cls.'" data-rel="'.htmlspecialchars($rel).'" style="margin-left:'.$indent.'px">';
        echo $isDir ? '📁 ' : '📄 ';
        echo '<span class="node-label" data-name="'.htmlspecialchars($name).'">'.htmlspecialchars($name).'</span>';
        echo '</div>';

        if ($isDir && !empty($n['children']) && is_array($n['children'])) {
            renderTree($n['children'], $selectedRel, $depth+1);
        }
    }
}
?>

<?= $this->extend('layouts/app') ?>

<?= $this->section('head') ?>
<meta name="csrf-token" content="<?= csrf_hash() ?>">
<style>
  :root {
    --left-col: 360px;   /* JS will auto-size this */
    --right-col: 400px;
  }

  /* Wrap that matches main width & keeps columns together */
  .mod-wrap { margin:0 auto; padding:12px; }
  .columns {
    display:grid;
    grid-template-columns: var(--left-col) 1fr var(--right-col);
    gap:12px;
    align-items:stretch;
  }

  /* LEFT COLUMN: stack MyMods card + Files tree panel */
  #colLeft {
    display:flex;
    flex-direction:column;
    gap:12px;
    height: calc(100vh - var(--header-h) - 24px);
    min-height: 0; /* allow inner scrollers */
  }

  .sidebar-card,
  .panel {
    background:#0d1117; border:1px solid #21262d; border-radius:.5rem;
    box-sizing:border-box; display:flex; flex-direction:column;
    min-height:0;
    overflow:visible;       /* neutralize any global .panel { overflow:auto } */
  }
  .sidebar-card { flex:0 0 auto; } /* don’t let it be squeezed by flex */

  .sidebar-card .head,
  .panel > .head {
    padding:.6rem .8rem; border-bottom:1px solid #21262d; font-weight:600;
    display:flex; align-items:center; justify-content:space-between; gap:.5rem;
  }

  /* MyMods body can scroll if long */
  .sidebar-card .body {
    padding:.6rem .6rem .8rem;
    overflow:auto;
    min-height:0;
  }

  /* Tree panel: only #tree may scroll */
  .tree-panel { flex:1 1 auto; min-height:0; overflow:hidden; }
  .tree-panel .body {
    padding:0;
    overflow:hidden;   /* wrapper does NOT scroll */
    flex:1 1 auto;
    min-height:0;
  }
  #tree {
    height:100%;
    padding:.6rem .4rem .8rem;
  }

  /* MyMods list as vertical list, no wrap */
  .mods-ul { list-style:none; margin:0; padding:0; }
  .mods-ul li { margin:0; }
  .mod-link {
    display:block; padding:.22rem .36rem; border-radius:.25rem; text-decoration:none;
    color:#c9d1d9; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
  }
  .mod-link:hover { background:#161b22; }
  .mod-link.active { background:#14324a; outline:1px solid #1f6feb; }

  /* File tree items */
  .node { padding:.2rem .4rem; border-radius:.25rem; cursor:pointer; user-select:none; }
  .node:hover { background:#161b22; }
  .node.dir  { color:#c9d1d9; }
  .node.file { color:#a5d6ff; }
  .node.highlight { background:#14324a; outline:1px solid #1f6feb; }
  .node-label { display:inline-block; }

  /* Middle viewer & right details panels */
  .panel.tall { height: calc(100vh - var(--header-h) - 24px); min-height:0; }
  .panel > .body { padding:.8rem; overflow:auto; min-height:0; }

  /* Small utilities */
  .hidden{display:none !important;}
  .muted { color:#8b949e; }
  pre, code { white-space: pre-wrap; word-break: break-word; }
  img.dynImg { max-width:100%; height:auto; display:block; }
  .badgebar { display:flex; gap:8px; margin-bottom:.5rem; flex-wrap:wrap;}
  .badge { font-size:.75rem; padding:.15rem .4rem; border:1px solid #21262d; border-radius:.25rem; background:#0c1320;}
  .flash-red { background:#3a1114; border:1px solid #5a1a1f; padding:.4rem; border-radius:.25rem;}

  .btn-icon {
    background:#0b1624; border:1px solid #21262d; color:#c9d1d9;
    border-radius:.35rem; padding:.25rem .5rem; font-size:.85rem; cursor:pointer;
  }
  .btn-icon:hover { background:#111a2a; }

  /* Highlight for the selected file in the tree */
  .node.active {
    background: rgba(59, 130, 246, 0.15);
    outline: 1px solid rgba(59, 130, 246, 0.65);
    border-radius: 6px;
  }
  .node.active .node-label { font-weight: 600; }
  .tree-panel .node:hover { background: rgba(255, 255, 255, 0.06); }

  /* ---- Dialog meta (lives directly in #dialogMeta) ---- */
  #dialogMeta .dlg-h4, #dialogMeta .dlg-h5 { 
    margin: 10px 0 6px; font-weight: 600; color:#c9d1d9;
  }
  #dialogMeta .dlg-row{
    display:grid;
    gap:8px 12px;
    align-items:center;
    padding:4px 0;
  }
  #dialogMeta .dlg-label{ color:#8b949e; font-size:.85rem; }
  #dialogMeta .dlg-val{ color:#c9d1d9; font-size:.92rem; }

  /* IDs look mono + copyable */
  #dialogMeta .dlg-val.mono{
    font-family: ui-monospace,SFMono-Regular,Menlo,monospace;
    background:#21262d; border:1px solid #30363d; border-radius:8px;
    padding:3px 8px; line-height:1.25;
  }
  #dialogMeta .dlg-val.mono.copyable{ cursor:pointer; position:relative; }
  #dialogMeta .dlg-val.mono.copyable::after{
    content:"Copy"; position:absolute; right:6px; top:50%; transform:translateY(-50%);
    font-size:.7rem; color:#8b949e; opacity:0; transition:.15s;
  }
  #dialogMeta .dlg-val.mono.copyable:hover::after{ opacity:.9; }

  /* Inline editing */
  #dialogMeta .dlg-val.editable{ cursor:text; }
  #dialogMeta .dlg-val[contenteditable="true"]{
    outline:1px dashed #58a6ff; background:#0f1620; border-radius:8px; border:1px solid #1f6feb;
  }

  /* Chips & table */
  #dialogMeta .dlg-chiprow{ display:flex; flex-wrap:wrap; gap:6px; }
  #dialogMeta .chip{
    display:inline-flex; gap:6px; padding:4px 8px; border-radius:999px;
    background:#21262d; border:1px solid #30363d; color:#c9d1d9; font-size:.85rem;
  }
  #dialogMeta .chip-narrator{ background:rgba(210,168,255,.12); color:#d2a8ff; border-color:rgba(210,168,255,.35); }

  #dialogMeta .dlg-table{ width:100%; border-collapse:collapse; font-size:.88rem; }
  #dialogMeta .dlg-table th,#dialogMeta .dlg-table td{ padding:6px 8px; border-bottom:1px solid #30363d; color:#c9d1d9; }
  #dialogMeta .dlg-table th{ color:#8b949e; text-align:left; }

  #dialogMeta .dlg-problems{ display:flex; flex-wrap:wrap; gap:6px; margin-top:6px; }
  #dialogMeta .dlg-problems .pill{
    padding:3px 8px; border-radius:999px; font-size:.8rem;
    background:rgba(48,54,61,.55); color:#c9d1d9; border:1px solid #30363d;
  }
  /* Dialog node shell */
  .dlg-node {
    border: 1px solid #21262d;
    border-radius: 10px;
    background: #0d1117;
    margin: 6px 0;
  }

  /* Header row: click to toggle */
  .dlg-node-head {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 8px;
    border-bottom: 1px solid #21262d;
    cursor: pointer;
  }

  /* Hide body/meta/children when collapsed */
  .dlg-node.collapsed .dlg-node-meta,
  .dlg-node.collapsed .dlg-node-body,
  .dlg-node.collapsed .dlg-node-children {
    display: none;
  }

  /* Tiny caret indicator */
  .dlg-node-head .caret {
    width: 0; height: 0;
    border-left: 5px solid transparent;
    border-right: 5px solid transparent;
    border-top: 6px solid #8b949e;
    transition: transform .15s ease;
  }
  .dlg-node:not(.collapsed) .dlg-node-head .caret { transform: rotate(180deg); }

  /* Hide body when collapsed */
  .dlg-node.collapsed .dlg-node-body { display: none; }

  /* (optional) nicer shell & clickable header */
  .dlg-node { border:1px solid #21262d; border-radius:10px; background:#0d1117; margin:6px 0; }
  .dlg-node-hd { display:flex; align-items:center; gap:8px; padding:6px 8px; border-bottom:1px solid #21262d; cursor:pointer; }
  .dlg-node-hd .toggle { margin-left:auto; } /* keep your button on the right */
  /* --- Dialog node body formatting --- */
  .dlg-node-body { padding: 8px 10px; }

  /* key–value rows */
  .kv {
    display: grid;
    grid-template-columns: 120px 1fr;
    gap: 6px 12px;
    align-items: center;
    margin: 4px 0;
  }
  .kv .k { color: #8b949e; font-size: .85rem; }
  .kv .v { color: #c9d1d9; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }

  /* children list → chips */
  .children { margin-top: 10px; }
  .children .k { display: inline-block; margin-right: 8px; color: #8b949e; }
  .children ul {
    list-style: none;
    padding: 0;
    margin: 6px 0 0;
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
  }
  .chip-uuid {
    display: inline-flex; align-items: center; gap: 6px; padding: 4px 8px;
    border-radius: 999px;
    background: #21262d; border: 1px solid #30363d;
    color: #c9d1d9;
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: .85rem; text-decoration: none;
  }
  .chip-uuid .short {
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
  }
  .endnote { margin-top: 6px; color: #8b949e; font-style: italic; }

  /* flags group */
  .flag-group { margin-top: 10px; border: 1px solid #30363d; border-radius: 8px; background: #0f1319; }
  .flag-title {
    padding: 6px 8px; border-bottom: 1px solid #30363d; font-weight: 600; color: #c9d1d9;
    display: flex; justify-content: space-between; align-items: center;
  }
  .flag-title .count { color: #8b949e; font-weight: 400; }
  .flag-group ul { list-style: none; margin: 0; padding: 6px 8px; display: flex; flex-direction: column; gap: 8px; }
  .flag-li {
    display: grid;
    grid-template-columns: repeat(4, max-content) 1fr;
    gap: 6px 12px; align-items: center;
  }
  .flag-li code {
    background: #21262d; border: 1px solid #30363d; border-radius: 6px;
    padding: 2px 6px; font-size: .8rem;
  }
  .flag-target { color: #c9d1d9; }

</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div
  class="mod-wrap"
  id="modLayout"
  data-root="<?= esc($root) ?>"
  data-slug="<?= esc($slug) ?>"
  data-basekey="<?= esc($root . '/' . $slug) ?>"
  data-selectedfile="<?= esc($selectedFile ?? '') ?>"
>
  <div class="columns">
    <!-- LEFT: MyMods card + Files tree panel -->
    <div id="colLeft">
      <div class="sidebar-card" id="modsCard">
        <div class="head">
          <span>MyMods — Directories</span>
        </div>
        <div class="body" id="modsList">
          <?php if (!empty($myMods) && is_array($myMods)): ?>
            <ul class="mods-ul">
              <?php foreach ($myMods as $name): ?>
                <?php $isActive = isset($slug) && $slug === $name; ?>
                <li>
                  <a
                    class="mod-link <?= $isActive ? 'active' : '' ?>"
                    href="<?= site_url('mods/' . ($root ?? 'MyMods') . '/' . $name) ?>"
                    aria-current="<?= $isActive ? 'page' : 'false' ?>"
                    title="<?= esc($name) ?>"
                  ><?= esc($name) ?></a>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <div class="muted">No mods found in MyMods.</div>
          <?php endif; ?>
        </div>
      </div>

      <aside class="panel tree-panel" id="treePanel">
        <div class="head">
          <span id="filesTitle">Files &amp; Folders (all depths)</span>
          <button id="toggleSidebar" class="btn-icon" type="button" title="Collapse/Expand">⇔</button>
        </div>
        <div class="body">
          <div id="tree">
            <?php renderTree($tree, $selectedFile ?? '', 0); ?>
          </div>
        </div>
      </aside>
    </div>

    <!-- MIDDLE: Viewer -->
    <section class="panel tall">
      <div class="head">Viewer</div>
      <div class="body" id="viewer">
        <p class="muted">Select a file to preview here.</p>
      </div>
    </section>

    <!-- RIGHT: Details -->
    <aside class="panel tall">
      <div class="head">Tags</div>
      <div class="body" id="meta">
        <div class="muted">Region, type, and other metadata will appear here.</div>
      </div>
      <div class="head hidden" id="dlgHead">Dialog</div>
      <div class="body hidden" id="dialogMeta">
        <div class="muted">Dialog-specific tags and speakers will appear here.</div>
      </div>
    </aside>
  </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
// --- HTML escape helper (global) ---
window.esc = window.esc || function esc(v) {
  const s = String(v ?? '');
  const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;', '`': '&#96;' };
  return s.replace(/[&<>"'`]/g, ch => map[ch]);
};

(function(){
  const rootEl  = document.getElementById('modLayout');
  const root    = rootEl?.dataset?.root || '';
  const slug    = rootEl?.dataset?.slug || '';

  
  const tree    = document.getElementById('tree');
  const view    = document.getElementById('viewer');
  const meta    = document.getElementById('meta');
  const colLeft = document.getElementById('colLeft');

    function hideDialogSection(){ dlgHead?.classList.add('hidden'); dialogMeta?.classList.add('hidden'); dialogMeta.innerHTML=''; }
  const dlgHead = document.getElementById('dlgHead');
  const dialogMeta = document.getElementById('dialogMeta');

  function badgeBar(obj) {
    const items=[];
    if (obj.kind)        items.push(`<span class="badge">kind: ${esc(obj.kind)}</span>`);
    if (obj.ext)         items.push(`<span class="badge">ext: ${esc(obj.ext)}</span>`);
    if (obj.regionGroup) items.push(`<span class="badge">group: ${esc(obj.regionGroup)}</span>`);
    if (obj.region)      items.push(`<span class="badge">region: ${esc(obj.region)}</span>`);
    return `<div class="badgebar">${items.join(' ')}</div>`;
  }
  function renderJson(obj) { return `<pre><code>${esc(JSON.stringify(obj, null, 2))}</code></pre>`; }
  function relToUrl(rel) {
    const segs = rel.split('/').filter(Boolean).map(encodeURIComponent);
    return `/mods/${encodeURIComponent(root)}/${encodeURIComponent(slug)}/${segs.join('/')}` + `?format=json`;
  }

  function setUrlSelection(rel) {
    try {
      const url = new URL(window.location.href);
      if (rel) {
        url.searchParams.set('sel', rel);
      } else {
        url.searchParams.delete('sel');
      }
      history.replaceState(null, '', url);
    } catch (_) {}
  }

  function getUrlSelection() {
    try {
      const url = new URL(window.location.href);
      return url.searchParams.get('sel') || '';
    } catch (_) { return ''; }
  }

  async function openRel(rel) {
  try {
    view.innerHTML = `<div class="muted">Loading…</div>`;

    const r = await fetch(relToUrl(rel), { headers: { 'Accept': 'application/json' } });
    if (!r.ok) throw new Error(`HTTP ${r.status}`);
    const data = await r.json();

    meta.innerHTML = badgeBar(data);
    persistSelection(rel); 
    saveLocal(rel);
    setUrlSelection(rel);

    const result = data.result ?? data.payload ?? data.data ?? {};
    const kind = (data.kind ?? result.kind ?? 'unknown').toLowerCase();
    const ext  = (data.ext  ?? result.ext  ?? '').toLowerCase();
    const metaObj = (data.meta ?? result.meta ?? {});
    const regionGroup = (metaObj.regionGroup ?? '').toLowerCase();
    const dlg = metaObj.dialog ?? null;

    const raw = (typeof data.raw === 'string')
      ? data.raw
      : (typeof result.raw === 'string' ? result.raw : '');

    const textLikeExts  = ['txt','khn','anc','ann','cln','clc'];
    const textLikeKinds = ['txt','khn'];
    const isTextLike    = textLikeExts.includes(ext) || textLikeKinds.includes(kind);

    if (kind === 'lsx' && regionGroup === 'dialog' && dlg) {
      meta.innerHTML = badgeBar(data);
      // dialog section handled separately below
    if (dlg) {
        dlgHead?.classList.remove('hidden');
        dialogMeta?.classList.remove('hidden');
        dialogMeta.innerHTML = renderDialogTags(dlg);
        enhanceDialogMeta();
      } else {
        dlgHead?.classList.add('hidden');
        dialogMeta?.classList.add('hidden');
        dialogMeta.innerHTML = '';
      }
      view.innerHTML = renderDialogNodes(dlg, metaObj);

      // Delegate clicks from the viewer once
      if (!view.__dlgBound) {
        view.addEventListener('click', (e) => {
          // 1) Click on a local node link: open the target node and scroll to it
          const aLocal = e.target.closest('a.link-local');
          if (aLocal) {
            e.preventDefault();
            const href = aLocal.getAttribute('href') || '';
            const id = href.charAt(0) === '#' ? href.slice(1) : href; // e.g. "node-<uuid>"
            const target = document.getElementById(id);
            if (target) {
              target.classList.remove('collapsed');
              target.setAttribute('aria-expanded', 'true');
              try { target.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); }
              catch (_) { target.scrollIntoView(); }
            }
            return;
          }

          // 2) Click header (or its Toggle button): toggle open/closed
          const headOrBtn = e.target.closest('.dlg-node-hd, .dlg-node-hd .toggle');
          if (!headOrBtn) return;
          const node = headOrBtn.closest('.dlg-node');
          if (!node) return;
          node.classList.toggle('collapsed');
          node.setAttribute('aria-expanded', node.classList.contains('collapsed') ? 'false' : 'true');
        });
        view.__dlgBound = true;
      }

      // Delegate header or button click to toggle that node
      if (!view.__dlgBound) {
        view.addEventListener('click', (e) => {
          const headOrBtn = e.target.closest('.dlg-node-hd, .dlg-node-hd .toggle');
          if (!headOrBtn) return;
          const node = e.target.closest('.dlg-node');
          if (!node) return;
          node.classList.toggle('collapsed');
          node.setAttribute('aria-expanded', node.classList.contains('collapsed') ? 'false' : 'true');
        });
        view.__dlgBound = true;
      }

      // Expand / Collapse all
      document.getElementById('dlg-expand-all')?.addEventListener('click', () => {
        document.querySelectorAll('.dlg-node').forEach(n => n.classList.remove('collapsed'));
      });
      document.getElementById('dlg-collapse-all')?.addEventListener('click', () => {
        document.querySelectorAll('.dlg-node').forEach(n => n.classList.add('collapsed'));
      });
      return;
    }

    hideDialogSection();
      if (kind === 'image') {
      if (result.dataUri) {
        view.innerHTML = `<img class="dynImg" src="${esc(result.dataUri)}" alt="preview">`;
      } else if (ext === 'dds') {
        view.innerHTML = `<div class="muted">DDS preview unavailable (no Imagick DDS delegate).</div>`;
      } else {
        view.innerHTML = `<div class="muted">No preview.</div>`;
      }
      return;
    }

    hideDialogSection();
      if (isTextLike && raw) {
      view.innerHTML = `<pre><code>${esc(raw)}</code></pre>`;
      return;
    }

    if (kind === 'xml' || kind === 'lsx') {
      view.innerHTML = renderJson(result || data);
      return;
    }

    if (raw) {
      view.innerHTML = `<pre><code>${esc(raw)}</code></pre>`;
    } else {
      view.innerHTML = renderJson(result || data);
    }
  } catch (err) {
    view.innerHTML = `<div class="flash-red">Failed to load: ${esc(err.message || String(err))}</div>`;
  }
}

  // expose openRel if other code uses it
  window.openRel = openRel;

  // Open on click (files only)
  
  // Persist selection with CSRF; non-blocking
  
  // LocalStorage helpers for persistence fallback
  function lsKey() {
    const baseKey = document.getElementById('modLayout')?.dataset?.basekey || '';
    return baseKey ? ('mods:last:' + baseKey) : '';
  }
  function saveLocal(rel) {
    const k = lsKey();
    if (k && rel) try { localStorage.setItem(k, rel); } catch (_) {}
  }
  function loadLocal() {
    const k = lsKey();
    if (!k) return '';
    try { return localStorage.getItem(k) || ''; } catch (_) { return ''; }
  }
  function clearLocal() {
    const k = lsKey();
    if (!k) return;
    try { localStorage.removeItem(k); } catch (_) {}
  }
  function persistSelection(rel) {
    const baseKey = document.getElementById('modLayout')?.dataset?.basekey || '';
    if (!baseKey || !rel) return;
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    try {
      navigator.sendBeacon('/mods/selection', new Blob([new URLSearchParams({
        '<?= csrf_token() ?>': token,
        base: baseKey,
        path: rel
      })], { type: 'application/x-www-form-urlencoded' }));
    } catch (e) {
      fetch('/mods/selection', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
        body: new URLSearchParams({ '<?= csrf_token() ?>': token, base: baseKey, path: rel })
      }).catch(()=>{});
    }
  }
  tree.addEventListener('click', (e) => {
    const el = e.target.closest('.node');
    if (!el || el.classList.contains('dir')) return;
    document.querySelectorAll('.node.active').forEach(n => n.classList.remove('active'));
    el.classList.add('active');
    try { el.scrollIntoView({block:'nearest', inline:'nearest'}); } catch(_) {}
    const rel = el.getAttribute('data-rel') || '';
    persistSelection(rel); saveLocal(rel);
    openRel(rel);
  });

  // Auto-open remembered selection on load (server value, then ?sel=, then localStorage)
  (function(){
    const layout = document.getElementById('modLayout');
    const serverSelected = layout?.dataset?.selectedfile || '';

    // Build a set of available file rel paths under THIS root
    const available = new Set(
      Array.from(document.querySelectorAll('.node.file[data-rel]'))
        .map(n => n.getAttribute('data-rel') || '')
    );

    let rel = serverSelected;

    if (!rel) {
      // 1) Prefer URL deep-link first
      const urlRel = getUrlSelection();
      if (urlRel && available.has(urlRel)) {
        rel = urlRel;
      } else {
        // If URL has a selection that doesn't exist in this root, clean it up
        if (urlRel && !available.has(urlRel)) setUrlSelection('');

        // 2) Fall back to LocalStorage
        const saved = loadLocal();
        if (saved && available.has(saved)) {
          rel = saved;
        } else if (saved && !available.has(saved)) {
          // Clear stale cross-root value
          clearLocal();
        }
      }
    }

    if (rel) {
      const node = document.querySelector('.node[data-rel="' + rel.replace(/"/g,'\\"') + '"]');
      if (node) {
        node.classList.add('active');
        try { node.scrollIntoView({ block: 'nearest', inline: 'nearest' }); } catch (_) {}
      }
      if (typeof openRel === 'function') openRel(rel);
    }
  })();

  // Highlight helper for Fetch UUID widgets
  function clearHighlights() {
    document.querySelectorAll('.node.highlight').forEach(n=> n.classList.remove('highlight'));
  }
  function highlightAndScroll(query) {
    clearHighlights();
    if (!query) return;
    const nodes = Array.from(document.querySelectorAll('.node.file'));
    const hit = nodes.find(n => (n.textContent || '').toLowerCase().includes(query.toLowerCase()));
    if (hit) {
      hit.classList.add('highlight');
      hit.scrollIntoView({ block: 'center' });
    }
  }
  document.addEventListener('app:fetch-uuid', (e)=> highlightAndScroll(e.detail?.value || ''));
  document.addEventListener('app:fetch-contentuuid', (e)=> highlightAndScroll(e.detail?.value || ''));

  // -------- Auto-size LEFT width to longest item (mods + files) ----------
  function measureLeftWidth() {
    if (!colLeft) return;
    let widest = 0;

    // MyMods anchors
    colLeft.querySelectorAll('.mod-link').forEach(a => {
      const textW = Math.ceil(a.scrollWidth);
      const ROW_PAD = 16;
      const CARD_PAD = 14;
      const GUTTER  = 24;
      const lineW = textW + ROW_PAD + CARD_PAD + GUTTER;
      if (lineW > widest) widest = lineW;
    });

    // File nodes
    colLeft.querySelectorAll('.node-label').forEach(label => {
      const row = label.closest('.node');
      if (!row) return;
      const ml = parseFloat(row.style.marginLeft || '0') || 0;
      const textW = Math.ceil(label.scrollWidth);
      const ICON_W = 20, ROW_PAD = 16, BODY_PAD = 14, GUTTER = 24;
      const lineW = ml + ICON_W + ROW_PAD + BODY_PAD + textW + GUTTER;
      if (lineW > widest) widest = lineW;
    });

    const MIN = 360;
    const MAX = Math.floor(window.innerWidth * 0.48);
    const finalW = Math.max(MIN, Math.min(widest || MIN, MAX));
    document.documentElement.style.setProperty('--left-col', finalW + 'px');
  }
  measureLeftWidth();
  setTimeout(measureLeftWidth, 50);
  if (document.fonts && document.fonts.ready) {
    document.fonts.ready.then(measureLeftWidth).catch(()=>{});
  }
  window.addEventListener('resize', () => requestAnimationFrame(measureLeftWidth));

  // -------- Collapse/Expand left width to header width ----------
  const toggleBtn  = document.getElementById('toggleSidebar');
  const filesTitle = document.getElementById('filesTitle');
  let collapsed = false;

  function collapseToHeader() {
    const btnW   = toggleBtn ? toggleBtn.offsetWidth : 32;
    const titleW = filesTitle ? filesTitle.scrollWidth : 160;
    const PAD    = 32; // head padding + small gutter
    const w = Math.max(240, titleW + btnW + PAD);
    document.documentElement.style.setProperty('--left-col', w + 'px');
    collapsed = true;
  }
  function expandAuto() {
    collapsed = false;
    measureLeftWidth();
  }
  if (toggleBtn) {
    toggleBtn.addEventListener('click', () => {
      if (collapsed) expandAuto(); else collapseToHeader();
    });
  }
})();

function renderDialogTags(dlg) {
  const escHtml = (s) => esc(String(s ?? ''));
  const speakerChip = (s) => `<span class="chip">#${s.index} (${escHtml(s.mappingId)})</span>`;
  const narratorPresent = dlg?.speakers?.narrator?.present === true;

  const addressedRows = (dlg?.speakers?.addressed ?? []).map(m => {
    const to = (m.toIndex === -1) ? 'none' : (m.toIndex === -666 ? 'Narrator' : `#${m.toIndex}`);
    return `<tr><td>#${m.fromIndex}</td><td>${escHtml(to)}</td></tr>`;
  }).join('') || `<tr><td colspan="2" class="muted">none</td></tr>`;

  const chips = (dlg?.speakers?.list ?? []).map(speakerChip).join(' ')
    + (narratorPresent ? ' <span class="chip chip-narrator">Narrator</span>' : '');

  const category   = dlg?.category ?? '—';
  const timelineId = dlg?.timelineId ?? '—';
  const dialogUuid = dlg?.dialogUuid ?? dlg?.uuid ?? '—';

  // exactly the structure requested (no extra wrapper)
  return [
    `<div class="dlg-row"><span class="dlg-label">Category</span><span class="dlg-val editable">${escHtml(category)}</span></div>`,
    `<div class="dlg-row"><span class="dlg-label">TimelineID</span><span class="dlg-val mono copyable" data-copy="${escHtml(timelineId)}">${escHtml(timelineId)}</span></div>`,
    `<div class="dlg-row"><span class="dlg-label">Dialog UUID</span><span class="dlg-val mono copyable" data-copy="${escHtml(dialogUuid)}">${escHtml(dialogUuid)}</span></div>`,
    `<h4 class="dlg-h4">Speakers</h4>`,
    `<div class="dlg-chiprow">${chips}</div>`,
    `<h5 class="dlg-h5">Default Addressed</h5>`,
    `<table class="dlg-table"><thead><tr><th>From</th><th>To</th></tr></thead><tbody>${addressedRows}</tbody></table>`,
    `<div class="dlg-problems"></div>`
  ].join('');
}

function renderDialogNodes(dlg, meta){
  const order = (dlg && dlg.roots && Array.isArray(dlg.roots.ordered)) ? dlg.roots.ordered : [];
  const nodes = Object.assign({}, (dlg && dlg.nodes) ? dlg.nodes : {});
  const seen = new Set(); const seq = [];
  for (var i=0;i<order.length;i++){ var u=order[i]; if (nodes[u]) { seq.push([u, nodes[u]]); seen.add(u); } }
  for (var u in nodes){ if (!seen.has(u)) seq.push([u, nodes[u]]); }

  const spMap = new Map(((dlg && dlg.speakers && Array.isArray(dlg.speakers.list)) ? dlg.speakers.list : []).map(s => [s.index, s.mappingId || '']));

  const flagList = (arr, title) => {
    const list = Array.isArray(arr) ? arr : [];
    const cnt = list.length;
    if (!cnt) return '';
    const lis = list.map(f => {
      const t = f.target || {};
      const tgt = t.kind === 'narrator' ? 'Narrator'
                : t.kind === 'none'     ? 'none'
                : t.kind === 'speaker'  ? `#${t.index} (${esc(t.mappingId || '')})`
                : t.kind === 'invalid'  ? `invalid (#${t.index})` : '';
      return `<li class="flag-li">
        <code>type=${esc(f.type)}</code>
        <code>UUID=${esc(f.UUID)}</code>
        <code>value=${f.value ? 'true' : 'false'}</code>
        <code>paramval=${(f.paramval|0)}</code>
        <span class="flag-target">→ ${esc(tgt)}</span>
      </li>`;
    }).join('');
    return `<div class="flag-group">
      <div class="flag-title">${esc(title)} <span class="count">(${cnt})</span></div>
      <ul>${lis}</ul>
    </div>`;
  };

  const cards = seq.map(([uuid, n]) => {
    const ctor = n.constructor || '';
    const isRoot = !!n.isRoot;
    const isEnd  = !!n.isEnd;
    const spk    = n.speakerIndex;

    var speaker = '—';
    if (spk === -666) speaker = 'Narrator';
    else if (spk === -1 || spk == null) speaker = '—';
    else if (spMap.has(spk)) speaker = `#${spk} (${esc(spMap.get(spk))})`;
    else speaker = `<span class="warn">#${spk} (not in speakerlist)</span>`;

  const texts = (Array.isArray(n.texts) ? n.texts : []).map(t => {
    const parts = [];

    if (t.lineId) parts.push(`<div class="line-id mono">UUID: ${esc(t.lineId)}</div>`);
    const resolved = typeof resolveDialogLineText === 'function' ? resolveDialogLineText(t, meta) : '';
    if (resolved) parts.push(`<div class="line-text">${esc(resolved)}</div>`);
    if (t.handle) parts.push(`<div class="line-handle mono">${esc(t.handle)}</div>`);
    if (t.text && !resolved) parts.push(`<div class="line-text">${esc(t.text)}</div>`);
    if (!parts.length) parts.push(`<div class="line-missing">Missing text</div>`);
    return `<div class="line">${parts.join('')}</div>`;
  }).join('');

    const children = (Array.isArray(n.children) ? n.children : []).map(c => {
      const uid = String(c.uuid || '');
      if (c.type === 'local') {
        return `<li>
          <a href="#node-${esc(uid)}" class="link-local chip-uuid" title="${esc(uid)}">
            <span class="short">${esc(uid)}</span>
          </a>
        </li>`;
      }
      if (c.type === 'nested') {
        return `<li>
          <span class="chip-uuid link-nested" data-target-uuid="${esc(uid)}" title="Nested → ${esc(uid)}">
            <span class="short">Nested → ${esc(uid)}</span>
          </span>
        </li>`;
      }
      return `<li>
        <span class="chip-uuid link-orphan" title="Orphan → ${esc(uid)}">
          <span class="short">Orphan → ${esc(uid)}</span>
        </span>
      </li>`;
    }).join('');

    const checks = flagList(n.flags && n.flags.checks, 'checkflags');
    const sets   = flagList(n.flags && n.flags.sets,   'setflags');

    const classes = ['dlg-node'];
    classes.push('collapsed');

    return `
      <article class="${classes.join(' ')}" id="node-${esc(uuid)}" aria-expanded="${isRoot ? 'true' : 'false'}">
        <header class="dlg-node-hd">
          <div class="head" >${esc(String(uuid))}</div>
          ${isRoot ? '<span class="badge root">Root</span>' : ''}
          ${isEnd  ? '<span class="badge end">End</span>'  : ''}
          ${String(ctor).toLowerCase()==='nested' ? '<span class="badge nested">Nested</span>' : ''}
          ${ctor ? `<span class="badge ctor">${esc(ctor)}</span>` : ''}
        </header>
        <section class="dlg-node-body">
          ${texts ? `<div class="texts">${texts}</div>` : ''}
          <div class="kv"><span class="k">Speaker</span><span class="v">${speaker}</span></div>
          <div class="children">
            <span class="k">Children</span>
            <ul>${children || '<li><span class="muted">none</span></li>'}</ul>
            ${isEnd ? `<div class="endnote">Stops here.</div>` : ''}
          </div>
          ${checks}${sets}
        </section>
      </article>`;
  }).join('');

  return `
    <div class="dlg-controls">
      <button class="btn btn-sm" id="dlg-expand-all" type="button">Expand all</button>
      <button class="btn btn-sm" id="dlg-collapse-all" type="button">Collapse all</button>
    </div>
    <div class="dlg-nodes">${cards}</div>`;
}

function enhanceDialogMeta() {
  const root = document.getElementById('dialogMeta');
  if (!root) return;

  // Click-to-copy for any .copyable
  root.addEventListener('click', async (e) => {
    const el = e.target.closest('.copyable');
    if (!el) return;
    const txt = (el.dataset.copy ?? el.textContent ?? '').trim();
    if (!txt) return;
    try { await navigator.clipboard.writeText(txt); } catch {}
    el.style.opacity = '0.75';
    setTimeout(() => { el.style.opacity = ''; }, 220);
  });

  // Double-click to edit .editable (Category)
  root.addEventListener('dblclick', (e) => {
    const el = e.target.closest('.editable');
    if (!el) return;
    if (el.getAttribute('contenteditable') !== 'true') {
      el.setAttribute('contenteditable', 'true');
      const sel = window.getSelection(), range = document.createRange();
      range.selectNodeContents(el); sel.removeAllRanges(); sel.addRange(range);
      el.focus();
    }
  });

  // Commit on Enter/blur
  root.addEventListener('keydown', (e) => {
    const el = e.target.closest('.editable[contenteditable="true"]');
    if (!el) return;
    if (e.key === 'Enter') { e.preventDefault(); el.blur(); }
  });
  root.addEventListener('blur', (e) => {
    const el = e.target.closest('.editable[contenteditable="true"]');
    if (!el) return;
    el.removeAttribute('contenteditable');
    const newVal = (el.textContent || '').trim();
    // TODO: save hook here if desired:
    // window.dispatchEvent(new CustomEvent('dialogMetaChanged', { detail: { field: 'category', value: newVal } }));
  }, true);
}

</script>
<?= $this->endSection() ?>
