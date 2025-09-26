<?php
// app/Views/settings/index.php

// Use your existing header/footer so the page picks up your theme/nav.
echo view('templates/header', [
    'title'     => $pageTitle ?? 'Settings',
    'activeTab' => $activeTab ?? 'settings',
]);
?>
<div class="row">
<div class="column left">
<div class="page settings">
  <div id="fileName">
    <h2><?= esc($pageTitle ?? 'Settings') ?></h2>
  </div>

  <form id="settingsForm" method="post" action="<?= site_url('settings/save') ?>">
    <?= csrf_field() ?>

    <div class="form-row">
      <label for="myMods">bg3LinuxHelper.MyMods</label>
      <input id="myMods" type="text" name="bg3LinuxHelper_MyMods"
             value="<?= esc($bg3?->MyMods ?? '') ?>" placeholder="MyMods" class="form-control">
    </div>

    <div class="form-row">
      <label for="allMods">bg3LinuxHelper.AllMods</label>
      <input id="allMods" type="text" name="bg3LinuxHelper_AllMods"
             value="<?= esc($bg3?->AllMods ?? '') ?>" placeholder="AllMods" class="form-control">
    </div>

    <div class="form-row">
      <label for="gameData">bg3LinuxHelper.GameData</label>
      <input id="gameData" type="text" name="bg3LinuxHelper_GameData"
             value="<?= esc($bg3?->GameData ?? '') ?>" placeholder="GameData" class="form-control">
    </div>

    <div class="form-row">
      <label for="mongoUri">mongo.default.uri</label>
      <input id="mongoUri" type="text" name="mongo_default_uri"
             value="<?= esc($mongo?->defaultUri ?? '') ?>" placeholder="mongodb://bg3mmh-mongo:27017" class="form-control">
    </div>

    <div class="form-row">
      <label for="mongoDb">mongo.default.db</label>
      <input id="mongoDb" type="text" name="mongo_default_db"
             value="<?= esc($mongo?->defaultDb ?? '') ?>" placeholder="bg3mmh" class="form-control">
    </div>

    <div class="actions" style="margin-top: .75rem;">
      <button id="saveBtn" type="submit" class="appSystem">Save</button>
      <span id="status" class="muted" style="margin-left:.5rem;"></span>
    </div>
  </form>
</div>
</div>
</div>

<script>
document.getElementById('settingsForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const btn = document.getElementById('saveBtn');
  const out = document.getElementById('status');
  btn.disabled = true;
  out.textContent = 'Saving...';
  out.className = 'muted';

  try {
    const fd = new FormData(e.target);
    const res = await fetch('<?= site_url('settings/save') ?>', {
      method: 'POST',
      body: fd,
      headers: { 'X-Requested-With': 'fetch' }
    });
    const j = await res.json();
    if (j.ok) {
      out.textContent = 'Saved ✓ (reload may be required)';
      out.className = 'ok';
    } else {
      out.textContent = 'Error: ' + (j.error || 'unknown');
      out.className = 'error';
    }
  } catch (err) {
    out.textContent = 'Unexpected error';
    out.className = 'error';
  } finally {
    btn.disabled = false;
  }
});
</script>

<?php
echo view('templates/footer');
