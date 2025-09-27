<?php
helper('url');
$root = $root ?? '';
$slug = $slug ?? '';
$tree = $tree ?? [];
$path = $path ?? '';

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
  :root { --side-w: 380px; --details-w: 340px; }

  /* Keep .mod-wrap aligned to <main> by nesting it in .wrap (below) */
  .mod-wrap { padding: 0; margin: 0; } /* .wrap supplies the outer padding/width */
  .columns3 {
    width: 100%;
    margin: 0;
    display: grid;
    grid-template-columns: var(--side-w) minmax(0, 1fr) var(--details-w);
    gap: 12px;
  }

  .panel {
    background: #0d1117;
    border: 1px solid #21262d;
    border-radius: .5rem;
    height: calc(100vh - var(--header-h));
    display: flex;
    flex-direction: column;
    overflow: hidden;
  }
  .panel > .head {
    padding: .6rem .8rem;
    border-bottom: 1px solid #21262d;
    font-weight: 600;
    flex: 0 0 auto;
  }
  .panel > .body {
    padding: .8rem;
    flex: 1 1 auto;
    overflow: auto;
  }

  .tree-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .5rem;
  }
  .tree-title { color: #c9d1d9; }
  .toggle-btn{
    display:inline-flex; align-items:center; justify-content:center;
    background:#161b22; border:1px solid #30363d; color:#c9d1d9;
    padding:.25rem .6rem; border-radius:.35rem; cursor:pointer;
    font-size:.85rem; line-height:1; min-width:2rem; user-select:none;
  }
  .toggle-btn:hover{ background:#1f2630; }

  .node { padding:.2rem .4rem; border-radius:.25rem; cursor:pointer; user-select:none; }
  .node:hover { background:#161b22; }
  .node.dir { color:#c9d1d9; }
  .node.file { color:#a5d6ff; }
  .node.highlight { background:#14324a; outline:1px solid #1f6feb; }
  .node-label { display:inline-block; }

  .muted { color:#8b949e; }
  pre, code { white-space: pre-wrap; word-break: break-word; }
  img.dynImg { max-width:100%; height:auto; display:block; }
  .badgebar { display:flex; gap:8px; margin-bottom:.5rem; flex-wrap:wrap;}
  .badge { font-size:.75rem; padding:.15rem .4rem; border:1px solid #21262d; border-radius:.25rem; background:#0c1320;}
  .flash-red { background:#3a1114; border:1px solid #5a1a1f; padding:.4rem; border-radius:.25rem;}
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<!-- Use the global .wrap so mod-wrap matches <main>'s width/padding -->
<div class="wrap">
  <div class="mod-wrap" id="modLayout" data-root="<?= esc($root) ?>" data-slug="<?= esc($slug) ?>">
    <div class="columns3">
      <!-- LEFT -->
      <aside class="panel" id="treePanel">
        <div class="head tree-head" id="treeHead">
          <span class="tree-title" id="treeTitle">Files &amp; Folders (all depths)</span>
          <button class="toggle-btn" id="btnToggleTree" type="button" title="Collapse/Expand">⇔</button>
        </div>
        <div class="body" id="tree">
          <?php renderTree($tree, 0); ?>
        </div>
      </aside>

      <!-- CENTER -->
      <section class="panel">
        <div class="head">Viewer</div>
        <div class="body" id="viewer">
          <p class="muted">Select a file to preview here.</p>
        </div>
      </section>

      <!-- RIGHT -->
      <aside class="panel">
        <div class="head">Details</div>
        <div class="body" id="meta">
          <div class="muted">Region, type, and other metadata will appear here.</div>
        </div>
      </aside>
    </div>
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

  tree.addEventListener('click', (e) => {
    const el = e.target.closest('.node');
    if (!el || el.classList.contains('dir')) return;
    openRel(el.getAttribute('data-rel') || '');
  });

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

  // Auto-size left column + collapse/expand
  const treePanel = document.getElementById('treePanel');
  const treeHead  = document.getElementById('treeHead');
  const treeTitle = document.getElementById('treeTitle');
  const toggleBtn = document.getElementById('btnToggleTree');

  let collapsed = false;

  function setSideWidth(px) {
    document.documentElement.style.setProperty('--side-w', Math.max(260, Math.floor(px)) + 'px');
  }

  function measureExpandedWidth() {
    const labels = treePanel.querySelectorAll('.node-label');
    if (!labels.length) return;
    let widest = 0;
    labels.forEach(label => {
      const row = label.closest('.node'); if (!row) return;
      const ml = parseFloat(row.style.marginLeft || '0') || 0;
      const textW = Math.ceil(label.scrollWidth);
      const ICON_W = 20, ROW_PAD = 16, BODY_PAD = 16, GUTTER = 24;
      const lineW = ml + ICON_W + ROW_PAD + BODY_PAD + textW + GUTTER;
      widest = Math.max(widest, lineW);
    });
    const MIN = 320, MAX = Math.floor(window.innerWidth * 0.45);
    setSideWidth(Math.max(MIN, Math.min(widest, MAX)));
  }

  function measureCollapsedWidth() {
    if (!treeHead || !treeTitle || !toggleBtn) return setSideWidth(300);
    const s = getComputedStyle(treeHead);
    const padX = parseFloat(s.paddingLeft) + parseFloat(s.paddingRight);
    const titleW = Math.ceil(treeTitle.scrollWidth);
    const btnW   = Math.ceil(toggleBtn.getBoundingClientRect().width);
    const GUTTER = 12;
    setSideWidth(titleW + btnW + padX + GUTTER);
  }

  function applySideWidth() {
    collapsed ? measureCollapsedWidth() : measureExpandedWidth();
  }

  applySideWidth();
  setTimeout(applySideWidth, 60);
  if (document.fonts && document.fonts.ready) { document.fonts.ready.then(applySideWidth).catch(()=>{}); }
  window.addEventListener('resize', () => requestAnimationFrame(applySideWidth));

  toggleBtn && toggleBtn.addEventListener('click', () => {
    collapsed = !collapsed;
    toggleBtn.textContent = collapsed ? '⇤' : '⇔';
    applySideWidth();
  });
})();
</script>
<?= $this->endSection() ?>

