<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>

<p class="muted">Showing directories in <strong><?= esc($root) ?></strong>.</p>
<div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(260px,1fr)); gap:12px;">
  <?php foreach ($mods as $name): ?>
    <div class="card" style="padding:.8rem;">
      <a href="<?= site_url('mods/'.$root.'/'.$name) ?>"><?= esc($name) ?></a>
    </div>
  <?php endforeach; ?>
</div>

<?= $this->endSection() ?>

