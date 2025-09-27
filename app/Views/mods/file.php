<?= $this->extend('layouts/default') ?>
<?= $this->section('content') ?>
<?php
use App\Helpers\FormatHelper;
$crumbs = [];
$acc = '';
foreach (explode('/', $path) as $seg) {
  if ($seg==='') continue;
  $acc = ($acc === '' ? $seg : $acc.'/'.$seg);
  $crumbs[] = ['name' => $seg, 'href' => "/mods/{$root}/{$slug}/{$acc}"];
}
?>
<input type="hidden" id="path" value="<?= esc($root) ?>/<?= esc($slug) ?>">
<h1 style="margin:0 0 .5rem"><?= esc($slug) ?> — <?= esc($path) ?></h1>

<div class="row">
  <div class="column left">
    <div class="form-row">
      <label for="search">Search:</label>
      <input spellcheck="false" id="search">
      <button class="appSystem" onclick="search('search')">Search</button>
      <button class="appSystem" onclick="clearInput('search')">Clear</button>
    </div>
    <div class="file-list">
      <strong>File:</strong>
      <div id="fileName"><?= esc(basename($path)) ?></div>
    </div>
  </div>

  <div class="column middle" id="searchDiv"><!-- results --></div>

  <div class="column right">
    <section class="viewer" style="margin-bottom:.75rem">
      <?php if ($kind === 'image' && isset($result['dataUri'])): ?>
        <img class="dynImg" src="<?= esc($result['dataUri']) ?>" alt="<?= esc($path) ?>">
      <?php elseif ($kind === 'xml' && is_array($result)): ?>
        <?= FormatHelper::renderLangEditorFromJson(json_encode($result, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)) ?>
      <?php elseif ($kind === 'txt' || $kind === 'khn'): ?>
        <pre class="code" style="white-space:pre-wrap"><?= esc($raw) ?></pre>
      <?php elseif ($kind === 'lsx' && is_array($result)): ?>
        <pre class="code" style="white-space:pre"><?= esc(json_encode($result, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)) ?></pre>
      <?php else: ?>
        <pre class="code" style="white-space:pre-wrap"><?= esc($raw) ?></pre>
      <?php endif; ?>
    </section>

    <form method="post" action="/mods/<?= esc($root) ?>/<?= esc($slug) ?>/file/<?= esc($path) ?>" class="editor">
      <h3>Save</h3>
      <?php if ($kind === 'xml' && is_array($result)): ?>
        <textarea name="data_json" rows="12" style="width:100%"><?= esc(json_encode($result, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)) ?></textarea>
      <?php else: ?>
        <textarea name="data" rows="12" style="width:100%"><?= esc($raw) ?></textarea>
      <?php endif; ?>
      <div style="margin-top:8px"><button type="submit">Save</button></div>
    </form>
  </div>
</div>
<?= $this->endSection() ?>

