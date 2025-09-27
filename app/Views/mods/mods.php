<?= $this->extend('layouts/default') ?>
<?= $this->section('content') ?>
<input type="hidden" id="path" value="<?= esc($root) ?>">
<h1 style="margin:0 0 .5rem"><?= esc($root) ?></h1>
<div class="row">
  <div class="column left">
    <div class="form-row">
      <label for="search">Search:</label>
      <input spellcheck="false" id="search">
      <button class="appSystem" onclick="search('search')">Search</button>
      <button class="appSystem" onclick="clearInput('search')">Clear</button>
    </div>
    <div class="file-list">
      <strong>Mods:</strong>
      <ul id="myUL">
        <?php foreach ($mods as $m): ?>
          <li><a href="/mods/<?= esc($root) ?>/<?= esc($m['slug']) ?>"><?= esc($m['slug']) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
  <div class="column middle" id="searchDiv"><!-- results --></div>
  <div class="column right" id="displayDiv"><!-- file view --></div>
</div>
<?= $this->endSection() ?>

