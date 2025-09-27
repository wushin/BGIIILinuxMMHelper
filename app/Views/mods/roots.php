<?= $this->extend('layouts/default') ?>
<?= $this->section('content') ?>
<h1 style="margin:0 0 .5rem">Roots</h1>
<ul class="list roots">
<?php foreach ($roots as $name => $info): ?>
  <li>
    <a href="/mods/<?= esc($name) ?>"><strong><?= esc($name) ?></strong></a>
    <?php if ($info['configured']): ?>
      <small>(<?= esc($info['count']) ?> mods) — <code><?= esc($info['path']) ?></code></small>
    <?php else: ?>
      <small class="flash-red">(not configured)</small>
    <?php endif; ?>
  </li>
<?php endforeach; ?>
</ul>
<?= $this->endSection() ?>

