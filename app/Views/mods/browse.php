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
    --right-col: 320px;
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
  .panel > .body { padding:.8rem; flex:1 1 auto; overflow:auto; min-height:0; }

  /* Small utilities */
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
      <div class="head">Details</div>
      <div class="body" id="meta">
        <div class="muted">Region, type, and other metadata will appear here.</div>
      </div>
    </aside>
  </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function(){
  const rootEl  = document.getElementById('modLayout');
  const root    = rootEl?.dataset?.root || '';
  const slug    = rootEl?.dataset?.slug || '';

  
  const tree    = document.getElementById('tree');
  const view    = document.getElementById('viewer');
  const meta    = document.getElementById('meta');
  const colLeft = document.getElementById('colLeft');

  function esc(s){ return (s||'').replace(/[&<>"']/g, m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[m])); }
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
      meta.innerHTML = badgeBar(data) + renderDialogTags(dlg);
      view.innerHTML = renderDialogNodes(dlg, metaObj);
      document.getElementById('dlg-expand-all')?.addEventListener('click', () => {
        document.querySelectorAll('.dlg-node').forEach(n => n.classList.remove('collapsed'));
      });
      document.getElementById('dlg-collapse-all')?.addEventListener('click', () => {
        document.querySelectorAll('.dlg-node').forEach(n => n.classList.add('collapsed'));
      });
      return;
    }

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


function resolveDialogLineText(tt, meta) {
  if (tt && typeof tt.text === 'string' && tt.text.trim()) {
    return esc(tt.text);
  }
  const handles = meta?.localization?.handles || {};
  if (tt?.handle && handles[tt.handle]?.text) {
    return esc(handles[tt.handle].text);
  }
  if (tt?.handle) return `<span class="muted">[${esc(tt.handle)}]</span>`;
  return `<span class="muted">(no text)</span>`;
}
function renderDialogTags(dlg) {
  const esc = (s)=>String(s??'').replace(/[&<>"]/g, m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;' }[m]));
  const cat = esc(dlg.category || '');
  const duu = esc(dlg.dialogUuid || '');
  const tid = esc(dlg.timelineId || '');

  const chips = (dlg.speakers?.list || [])
    .map(s => `<span class="chip">#${s.index}${s.mappingId ? ' ('+esc(s.mappingId)+')' : ''}</span>`).join(' ')
    + ` <span class="chip chip-narrator">Narrator</span>`;

  const rows = (dlg.speakers?.addressed || []).map(r => {
    const to = r.toIndex === -666 ? 'Narrator' : (r.toIndex === -1 ? 'none' : `#${r.toIndex}`);
    return `<tr><td>#${r.fromIndex}</td><td>${to}</td></tr>`;
  }).join('');

  const p = dlg.problems || {};
  const pills = [
    (p.edges?.orphans || []).length ? `<span class="pill err">Orphan links: ${(p.edges.orphans || []).length}</span>` : '',
    (p.constructors?.unknown || []).length ? `<span class="pill err">Unknown ctors: ${(p.constructors.unknown || []).length}</span>` : '',
    (p.speakers?.unmapped || []).length ? `<span class="pill warn">Unmapped speakers: ${(p.speakers.unmapped || []).length}</span>` : '',
    (p.flags?.invalidParamIndex || []).length ? `<span class="pill warn">Flags invalid: ${(p.flags.invalidParamIndex || []).length}</span>` : '',
  ].join('');

  return `
    <section class="dlg-tags card">
      <h3 class="dlg-h3">Dialog</h3>
      <div class="dlg-row"><span class="dlg-label">Category</span><span class="dlg-val">${cat}</span></div>
      <div class="dlg-row"><span class="dlg-label">Dialog UUID</span><span class="dlg-val mono">${duu}</span></div>
      <div class="dlg-row"><span class="dlg-label">TimelineID</span><span class="dlg-val mono">${tid}</span></div>
      <h4 class="dlg-h4">Speakers</h4>
      <div class="dlg-chiprow">${chips}</div>
      <h5 class="dlg-h5">Default Addressed</h5>
      <table class="dlg-table">
        <thead><tr><th>From</th><th>To</th></tr></thead>
        <tbody>${rows}</tbody>
      </table>
      <div class="dlg-problems">${pills}</div>
    </section>`;
}

function renderDialogNodes(dlg) {
  const esc = (s)=>String(s??'').replace(/[&<>"]/g, m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;' }[m]));
  const order = dlg.roots?.ordered || [];
  const nodes = Object.assign({}, dlg.nodes || {});
  const seen = new Set();
  const seq  = [];
  for (const u of order) if (nodes[u]) { seq.push([u, nodes[u]]); seen.add(u); }
  for (const u in nodes) if (!seen.has(u)) seq.push([u, nodes[u]]);

  const spMap = new Map((dlg.speakers?.list || []).map(s => [s.index, s.mappingId || '']));

  const flagList = (arr, title) => {
    if (!arr || !arr.length) return '';
    const lis = arr.map(f => {
      const t = f.target || {};
      const tgt = t.kind === 'narrator' ? 'Narrator'
                : t.kind === 'none'     ? 'none'
                : t.kind === 'speaker'  ? `#${t.index} (${esc(t.mappingId || '')})`
                : t.kind === 'invalid'  ? `<span class="warn">invalid (#${t.index})</span>` : '';
      return `<li class="flag-li">
        <code>type=${esc(f.type)}</code>
        <code>UUID=${esc(f.UUID)}</code>
        <code>value=${f.value ? 'true' : 'false'}</code>
        <code>paramval=${(f.paramval|0)}</code>
        <span class="flag-target">→ ${tgt}</span>
      </li>`;
    }).join('');
    return `<div class="flag-group"><div class="flag-title">${title}</div><ul>${lis}</ul></div>`;
  };

  const cards = seq.map(([uuid, n]) => {
    const ctor = n.constructor || '';
    const isRoot = !!n.isRoot;
    const isEnd  = !!n.isEnd;
    const spk    = n.speakerIndex;

    let speaker = '—';
    if (spk === -666) speaker = 'Narrator';
    else if (spk === -1 || spk === null || spk === undefined) speaker = '—';
    else if (spMap.has(spk)) speaker = `#${spk} (${esc(spMap.get(spk))})`;
    else speaker = `<span class="warn">#${spk} (not in speakerlist)</span>`;

    const texts = (n.texts || []).map(t => {
      if (t.text)   return `<div class="line"><div class="line-text">${esc(t.text)}</div></div>`;
      if (t.handle) return `<div class="line"><div class="line-handle mono">${esc(t.handle)}</div></div>`;
      if (t.lineId) return `<div class="line"><div class="line-missing">Missing text (LineId ${esc(t.lineId)})</div></div>`;
      return '';
    }).join('');

    const children = (n.children || []).map(c => {
      if (c.type === 'local')  return `<li><a href="#node-${esc(c.uuid)}" class="link-local">${esc(c.uuid)}</a></li>`;
      if (c.type === 'nested') return `<li><span class="link-nested" data-target-uuid="${esc(c.uuid)}">Nested → ${esc(c.uuid)}</span></li>`;
      return `<li><span class="link-orphan">Orphan → ${esc(c.uuid)}</span></li>`;
    }).join('');

    const checks = flagList(n.flags?.checks, 'checkflags');
    const sets   = flagList(n.flags?.sets,   'setflags');

    return `
      <article class="dlg-node${isRoot ? ' root' : ''}" id="node-${esc(uuid)}">
        <header class="dlg-node-hd">
          ${isRoot ? '<span class="badge root">Root</span>' : ''}
          ${isEnd  ? '<span class="badge end">End</span>'  : ''}
          ${String(ctor).toLowerCase()==='nested' ? '<span class="badge nested">Nested</span>' : ''}
          ${ctor ? `<span class="badge ctor">${esc(ctor)}</span>` : ''}
          <a class="uuid mono" href="#node-${esc(uuid)}">${esc(String(uuid).slice(0,8))}…</a>
          <button class="btn btn-xxs toggle">Toggle</button>
        </header>
        <section class="dlg-node-body">
          <div class="kv"><span class="k">Speaker</span><span class="v">${speaker}</span></div>
          ${texts ? `<div class="texts">${texts}</div>` : ''}
          <div class="children">
            <span class="k">Children</span>
            <ul>${children}</ul>
            ${isEnd ? `<div class="endnote">Stops here.</div>` : ''}
          </div>
          ${checks}${sets}
        </section>
      </article>`;
  }).join('');

  return `
    <div class="dlg-controls">
      <button class="btn btn-sm" id="dlg-expand-all">Expand all</button>
      <button class="btn btn-sm" id="dlg-collapse-all">Collapse all</button>
    </div>
    <div class="dlg-nodes">${cards}</div>`;
}

</script>
<?= $this->endSection() ?>
