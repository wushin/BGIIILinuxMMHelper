<?= $this->extend('layouts/app') ?>

<?= $this->section('head') ?>
<style>
  .root-card {
    background: #0d1117;
    border: 1px solid #21262d;
    border-radius: .5rem;
    overflow: hidden;
  }
  .root-card .head {
    padding: .6rem .8rem;
    border-bottom: 1px solid #21262d;
    font-weight: 600;
  }
  .dirlist {
    list-style: none;
    margin: 0;
    padding: 0;
  }
  .diritem {
    border-bottom: 1px solid #21262d;
  }
  .diritem:last-child {
    border-bottom: 0;
  }
  /* No wrapping per item; let each row scroll horizontally if needed */
  .dirlink {
    display: block;
    padding: .5rem .75rem;
    color: #c9d1d9;
    text-decoration: none;
    white-space: nowrap;    /* << no wrap */
    overflow-x: auto;       /* allow horizontal scroll for long names */
  }
  .dirlink:hover {
    background: #161b22;
  }
  .dirlink .icon { margin-right: .4rem; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="wrap">
  <div class="root-card">
    <div class="head"><?= esc($root) ?> — Directories</div>
    <ul class="dirlist">
      <?php if (!empty($mods)): ?>
        <?php foreach ($mods as $name):
          $name = (string) $name;
          if ($name === '') continue;
          $url  = site_url('mods/' . rawurlencode($root) . '/' . rawurlencode($name));
        ?>
          <li class="diritem">
            <a class="dirlink" href="<?= $url ?>">
              <span class="icon">📁</span><?= esc($name) ?>
            </a>
          </li>
        <?php endforeach; ?>
      <?php else: ?>
        <li class="diritem">
          <span class="dirlink" style="cursor:default;color:#8b949e">No directories found.</span>
        </li>
      <?php endif; ?>
    </ul>
  </div>
</div>
<?= $this->endSection() ?>

