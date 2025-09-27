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
        echo htmlspecialchars($name);
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
  :root { --sidebar-w: 360px; }
  /* Fixed LEFT sidebar below the shared header */
  .sidebar {
    position: fixed; top: var(--header-h); left: 0; width: var(--sidebar-w);
    height: calc(100vh - var(--header-h));
    background: #0d1117; border-right:1px solid #21262d;
    overflow: auto; white-space: nowrap; box-sizing: border-box;
  }
  .sidebar .head { position: sticky; top:0; background:#0d1117; border-bottom:1px solid #21262d; padding:.6rem .8rem; font-weight:600; }
  .sidebar .body { padding:.6rem .4rem .8rem; }
  .node { padding:.2rem .4rem; border-radius:.25rem; cursor:pointer; user-select:none; }
  .node:hover { background:#161b22; }
  .node.dir { color:#c9d1d9; }
  .node.file { color:#a5d6ff; }
  .node.highlight { background:#14324a; outline:1px solid #1f6feb; }

  /* Main content to the right of sidebar */
  .mod-layout { margin-left: var(--sidebar-w); padding:12px; box-sizing:border-box; }
  .columns { max-width:1600px; margin:0 auto; display:grid; grid-template-columns: 1fr 320px; gap:12px; }
  .panel { background:#0d1117; border:1px solid #21262d; border-radius:.5rem; height: calc(100vh - var(--header-h) - 24px); overflow:auto; box-sizing: border-box; display:flex; flex-direction:column; }
  .panel > .head { padding:.6rem .8rem; border-bottom:1px solid #21262d; font-weight:600; }
  .panel > .body { padding:.8rem; flex:1 1 auto; overflow:auto; }
  .muted { color:#8b949e; }
  pre, code { white-space: pre-wrap; word-break: break-word; }
  img.dynImg { max-width:100%; height:auto; display:block; }
  .badgebar { display:flex; gap:8px; margin-bottom:.5rem; flex-wrap:wrap;}
  .badge { font-size:.75rem; padding:.15rem .4rem; border:1px solid #21262d; border-radius:.25rem; background:#0c1320;}
  .flash-red { background:#3a1114; border:1px solid #5a1a1f; padding:.4rem; border-radius:.25rem;}
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<!-- FIXED LEFT SIDEBAR -->
<aside class="sidebar">
  <div class="head">Files &amp; Folders (all depths)</div>
  <div class="body" id="tree">
    <?php renderTree($tree, 0); ?>
  </div>
</aside>

<!-- MAIN CONTENT TO THE RIGHT -->
<div class="mod-layout" id="modLayout" data-root="<?= esc($root) ?>" data-slug="<?= esc($slug) ?>">
  <div class="columns">
    <section class="panel">
      <div class="head">Viewer</div>
      <div class="body" id="viewer">
        <p class="muted">Select a file to preview here.</p>
      </div>
    </section>
    <aside class="panel">
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

  // File open
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

  // Listen to layout header events
  document.addEventListener('app:fetch-uuid', (e)=> highlightAndScroll(e.detail?.value || ''));
  document.addEventListener('app:fetch-contentuuid', (e)=> highlightAndScroll(e.detail?.value || ''));
})();
</script>
<?= $this->endSection() ?>

