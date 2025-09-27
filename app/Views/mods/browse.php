<?= $this->extend('layouts/default') ?>
<?= $this->section('content') ?>
<?php
function h($s){return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');}

/**
 * Recursive renderer for the tree nodes.
 * Node shape: { name, isDir, rel, ext?, children?[] }
 */
$renderNode = function(array $node, string $root, string $slug) use (&$renderNode)
{
    $isDir = !empty($node['isDir']);
    $name  = (string)($node['name'] ?? '');
    $rel   = (string)($node['rel']  ?? '');

    if ($isDir) {
        echo '<li class="dir"><strong>'.h($name).'/</strong>';
        if (!empty($node['children']) && is_array($node['children'])) {
            echo '<ul>';
            foreach ($node['children'] as $child) {
                $renderNode($child, $root, $slug);
            }
            echo '</ul>';
        }
        echo '</li>';
    } else {
        $url = '/mods/'.rawurlencode($root).'/'.rawurlencode($slug).'/'.str_replace('%2F','/',rawurlencode($rel));
        echo '<li class="file">';
        echo h($name).' ';
        echo '<button class="appSystem" onclick="openInMiddle(\'' . h($url) . '\')">Open</button>';
        echo '</li>';
    }
};
?>
<input type="hidden" id="path" value="<?= h($root) ?>/<?= h($slug) ?>">
<h1 style="margin:0 0 .5rem"><?= h($slug) ?></h1>

<!-- Scoped styling to indent each nested level of the tree -->
<style>
  .file-tree, .file-tree ul { list-style: none; margin: 0; padding-left: 0; }
  .file-tree ul { padding-left: 1.25rem; }   /* indentation per level */
  .file-tree li { margin: 2px 0; }
  .file-tree li.dir > strong { display: inline-block; }
  .pill { display:inline-block;margin-right:.5rem;padding:.1rem .5rem;border-radius:999px;background:#444;color:#fff;font-size:.85em; }
  .kv { margin:.25rem 0 0; padding-left:1rem; }
  .kv li { margin:.125rem 0; }
  .table-wrap{ overflow:auto; }
  .table{ width:100%; border-collapse:collapse; }
  .table th, .table td{ border:1px solid #666; padding:.25rem .4rem; text-align:left; vertical-align:top; }
</style>

<div class="row">
  <!-- LEFT: full, always-expanded recursive tree -->
  <div class="column left">
    <div class="form-row">
      <label for="search">Search:</label>
      <input spellcheck="false" id="search">
      <button class="appSystem" disabled>Search</button>
      <button class="appSystem" onclick="clearInput('search')">Clear</button>
    </div>
    <div class="form-row">
      <label for="replace">Replace:</label>
      <input spellcheck="false" id="replace">
      <button class="appSystem" disabled>Replace</button>
      <button class="appSystem" onclick="clearInput('replace')">Clear</button>
    </div>

    <div class="file-list">
      <strong>Files:</strong>
      <ul id="treeUL" class="expanded file-tree">
        <?php foreach (($tree ?? []) as $n) { $renderNode($n, $root, $slug); } ?>
      </ul>
    </div>
  </div>

  <!-- MIDDLE: inline viewer -->
  <div class="column middle" id="displayDiv" style="min-height:400px; overflow:auto;">
    <div class="muted">Select a file on the left and click <em>Open</em>.</div>
  </div>

  <!-- RIGHT: intentionally empty to keep grid happy -->
  <div class="column right" style="display:none"></div>
</div>

<script>
function clearInput(id){ const el = document.getElementById(id); if (el) el.value=''; }

async function openInMiddle(url){
  const target = document.getElementById('displayDiv');
  if(!target) return;
  target.innerHTML = '<div class="muted">Loading…</div>';
  try{
    const resp = await fetch(url + (url.includes('?') ? '&' : '?') + 'format=json', {headers:{'Accept':'application/json'}});
    if(!resp.ok){ target.innerHTML = '<div class="flash-red">Failed to load</div>'; return; }
    const data = await resp.json();

    const kind        = data.kind || 'unknown';
    const region      = data.region || null;
    const regionGroup = data.regionGroup || null;
    const result      = data.result || {};
    const raw         = (typeof data.raw === 'string') ? data.raw : '';

    if (kind === 'image' && result.dataUri) {
      target.innerHTML = '<img class="dynImg" src="'+escapeHtml(result.dataUri)+'" alt="preview">';
      return;
    }

    if (kind === 'lsx') {
      if (regionGroup === 'dialog') {
        const rows = collectTranslatedStrings(result, 500);
        target.innerHTML = renderBadgeBar(kind, region, regionGroup)
          + (rows.length
              ? renderTable(['Handle','Text'], rows.map(r => [r.handle || '', r.text || '']))
              : '<div class="muted">No TranslatedString attributes found.</div>');
        return;
    }

      if (regionGroup === 'gameplay') {
        const stats = summarizeNodeNames(result);
        target.innerHTML = renderBadgeBar(kind, region, regionGroup)
          + renderKeyValue(stats, 'Elements by name');
        return;
      }

      // assets/meta/unknown → pretty JSON
      target.innerHTML = renderBadgeBar(kind, region, regionGroup)
        + '<pre class="code" style="white-space:pre">'+escapeHtml(JSON.stringify(result, null, 2))+'</pre>';
      return;
    }

    if (kind === 'xml') {
      target.innerHTML = renderBadgeBar(kind)
        + '<pre class="code" style="white-space:pre">'+escapeHtml(JSON.stringify(result, null, 2))+'</pre>';
      return;
    }

    if (kind === 'txt' || kind === 'khn') {
      target.innerHTML = renderBadgeBar(kind)
        + '<pre class="code" style="white-space:pre-wrap">'+escapeHtml(raw || '')+'</pre>';
      return;
    }

    // fallback
    target.innerHTML = renderBadgeBar(kind)
      + '<pre class="code" style="white-space:pre-wrap">'+escapeHtml(raw || JSON.stringify(result,null,2))+'</pre>';
  }catch(e){
    target.innerHTML = '<div class="flash-red">Error: '+escapeHtml(String(e))+'</div>';
  }
}

/* ---------- tiny helpers ---------- */

function renderBadgeBar(kind, region=null, group=null){
  const pill = (t)=> '<span class="pill">'+escapeHtml(t)+'</span>';
  let html = '<div style="margin:0 0 .5rem">';
  html += pill('type: '+kind);
  if (region) html += pill('region: '+region);
  if (group)  html += pill('group: '+group);
  html += '</div>';
  return html;
}

function renderTable(headers, rows){
  let h = '<div class="table-wrap"><table class="table"><thead><tr>';
  headers.forEach(th => { h += '<th>'+escapeHtml(th)+'</th>'; });
  h += '</tr></thead><tbody>';
  rows.forEach(r => {
    h += '<tr>' + r.map(c => '<td>'+escapeHtml(String(c))+'</td>').join('') + '</tr>';
  });
  h += '</tbody></table></div>';
  return h;
}

function renderKeyValue(map, title){
  let h = '';
  if (title) h += '<h3 style="margin:.25rem 0 .5rem">'+escapeHtml(title)+'</h3>';
  h += '<ul class="kv">';
  Object.keys(map).sort((a,b)=>a.localeCompare(b)).forEach(k=>{
    h += '<li><code>'+escapeHtml(k)+'</code> — '+escapeHtml(String(map[k]))+'</li>';
  });
  h += '</ul>';
  return h;
}

// Walk the LSX JSON tree and collect TranslatedString attributes
function collectTranslatedStrings(root, limit=1000){
  const out = [];
  (function walk(n){
    if (!n || typeof n !== 'object') return;
    if (n.name === 'attribute' && n.attrs && /translatedstring/i.test(String(n.attrs.type||''))) {
      out.push({ handle: n.attrs.handle || '', text: n.attrs.text || '' });
      if (out.length >= limit) return;
    }
    const kids = Array.isArray(n.children) ? n.children : [];
    for (let i=0;i<kids.length && out.length<limit;i++) walk(kids[i]);
  })(root);
  return out;
}

// Count element names to give a quick feel for gameplay data
function summarizeNodeNames(root){
  const counts = {};
  (function walk(n){
    if (!n || typeof n !== 'object') return;
    if (n.name) counts[n.name] = (counts[n.name]||0) + 1;
    const kids = Array.isArray(n.children) ? n.children : [];
    for (let i=0;i<kids.length;i++) walk(kids[i]);
  })(root);
  return counts;
}

function escapeHtml(s){
  return String(s).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[m]));
}
</script>
<?= $this->endSection() ?>

