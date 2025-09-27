<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>

<div class="card">
  <h2 style="margin-top:0">Roots</h2>
  <ul>
    <?php foreach (['MyMods','UnpackedMods','GameData'] as $rk): $r = $roots[$rk] ?? null; ?>
      <li>
        <strong><?= esc($rk) ?></strong>
        <?php if ($r && $r['configured']): ?>
          — <a href="<?= site_url('mods/'.$rk) ?>"><?= esc($r['path']) ?></a>
          <span class="muted">(<?= (int)$r['count'] ?> mod dirs)</span>
        <?php else: ?>
          <span class="muted">not configured</span>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
</div>

<?= $this->endSection() ?>

