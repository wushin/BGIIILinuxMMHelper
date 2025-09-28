<?php
helper('url');
$root   = $root ?? '';
$slug   = $slug ?? '';
$tree   = $tree ?? [];
$path   = $path ?? '';
$myMods = $myMods ?? [];

/** Render recursive tree nodes */
function renderTree(array $nodes, int $depth = 0) {
    foreach ($nodes as $n) {
        $indent = $depth * 14;
        $isDir  = !empty($n['isDir']);
        $name   = $n['name'] ?? '';
        $rel    = $n['rel']  ?? '';
        $ext    = $n['ext']  ?? '';

        $cls = $isDir ? 'node dir' : 'node file ext-'.htmlspecialchars($ext);
        echo '<div class="'.$cls.'" data-rel="'.htmlspecialchars($rel).'" style="margin-left:'.$indent.'px">';
        echo $isDir ? '📁 ' : '📄 ';
        echo '<span class="node-label" data-name="'.htmlspecialchars($name).'">'.htmlspecialchars($name).'</span>';
        echo '</div>';

        if ($isDir && !empty($n['children']) && is_array($n['children'])) {
            renderTree($n['children'], $depth+1);
        }
    }
}
?>

<?= $this->extend('layouts/app') ?>

<?= $this->section('head') ?>
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
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="mod-wrap" id="modLayout" data-root="<?= esc($root) ?>" data-slug="<?= esc($slug) ?>">
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
            <?php renderTree($tree, 0); ?>
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
  const rootEl = document.getElementById('modLayout');
  const root  = rootEl.dataset.root;
  const slug  = rootEl.dataset.slug;
  const tree  = document.getElementById('tree');
  const view  = document.getElementById('viewer');
  const meta  = document.getElementById('meta');
  const colLeft = document.getElementById('colLeft');

  function esc(s){ return (s||'').replace(/[&<>"']/g, m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[m])); }
  function badgeBar(obj) {
    const items=[];
    if (obj.kind) items.push(`<span class="badge">kind: ${esc(obj.kind)}</span>`);
    if (obj.ext) items.push(`<span class="badge">ext: ${esc(obj.ext)}</span>`);
    if (obj.regionGroup) items.push(`<span class="badge">group: ${esc(obj.regionGroup)}</span>`);
    if (obj.region) items.push(`<span class="badge">region: ${esc(obj.region)}</span>`);
    return `<div class="badgebar">${items.join(' ')}</div>`;
  }
  function renderJson(obj) { return `<pre><code>${esc(JSON.stringify(obj, null, 2))}</code></pre>`; }
  function relToUrl(rel) {
    const segs = rel.split('/').filter(Boolean).map(encodeURIComponent);
    return `/mods/${encodeURIComponent(root)}/${encodeURIComponent(slug)}/${segs.join('/')}` + `?format=json`;
  }

  async function openRel(rel) {
    try {
      const r = await fetch(relToUrl(rel), { headers: { 'Accept': 'application/json' }});
      if (!r.ok) throw new Error(`HTTP ${r.status}`);
      const data = await r.json();

      meta.innerHTML = badgeBar(data);

      const kind   = data.kind || 'unknown';
      const ext    = data.ext  || '';
      const result = data.result || {};
      const raw    = typeof data.raw === 'string' ? data.raw : '';

      if (kind === 'image') {
        if (result.dataUri) {
          view.innerHTML = badgeBar(data) + `<img class="dynImg" src="${esc(result.dataUri)}" alt="preview">`;
        } else if (ext.toLowerCase() === 'dds') {
          view.innerHTML = badgeBar(data) + `<div class="flash-red">DDS preview unavailable (no Imagick DDS delegate).</div>`;
        } else {
          view.innerHTML = `<div class="muted">No preview.</div>`;
        }
        return;
      }

      if (['xml', 'txt', 'khn', 'lsx'].includes(kind)) {
        view.innerHTML = badgeBar(data) + renderJson(result || raw || data);
        return;
      }

      if (raw) {
        view.innerHTML = badgeBar(data) + `<pre><code>${esc(raw)}</code></pre>`;
      } else {
        view.innerHTML = badgeBar(data) + renderJson(result || data);
      }
    } catch (err) {
      view.innerHTML = `<div class="flash-red">Failed to load: ${esc(err.message||String(err))}</div>`;
    }
  }

  // Open on click (files only)
  tree.addEventListener('click', (e) => {
    const el = e.target.closest('.node');
    if (!el || el.classList.contains('dir')) return;
    openRel(el.getAttribute('data-rel') || '');
  });

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
</script>
<?= $this->endSection() ?>

