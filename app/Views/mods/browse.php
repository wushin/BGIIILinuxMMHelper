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
  /* keep list bullets off; indent each depth via padding on nested <ul> */
  .file-tree, .file-tree ul { list-style: none; margin: 0; padding-left: 0; }
  .file-tree ul { padding-left: 1.25rem; }           /* indentation per level */
  .file-tree li { margin: 2px 0; }
  .file-tree li.dir > strong { display: inline-block; }
</style>

<div class="row">
  <!-- LEFT: always-expanded full tree, with Search/Replace inputs (not wired) -->
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

  <!-- RIGHT: intentionally empty to keep original grid CSS happy -->
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

    const kind = data.kind || 'unknown';
    const result = data.result || {};
    const raw = (typeof data.raw === 'string') ? data.raw : '';

    if(kind === 'image' && result.dataUri){
      target.innerHTML = '<img class="dynImg" src="'+escapeHtml(result.dataUri)+'" alt="preview">';
      return;
    }
    if(kind === 'xml' || kind === 'lsx'){
      target.innerHTML = '<pre class="code" style="white-space:pre">'+escapeHtml(JSON.stringify(result, null, 2))+'</pre>';
      return;
    }
    if(kind === 'txt' || kind === 'khn'){
      target.innerHTML = '<pre class="code" style="white-space:pre-wrap">'+escapeHtml(raw || '')+'</pre>';
      return;
    }
    target.innerHTML = '<pre class="code" style="white-space:pre-wrap">'+escapeHtml(raw || JSON.stringify(result,null,2))+'</pre>';
  }catch(e){
    target.innerHTML = '<div class="flash-red">Error: '+escapeHtml(String(e))+'</div>';
  }
}

function escapeHtml(s){
  return String(s).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[m]));
}
</script>
<?= $this->endSection() ?>

