<?= $this->extend('layouts/app') ?>

<?= $this->section('head') ?>
<style>
  .settings-wrap { display:flex; gap:24px; align-items:flex-start; }
  .card { background:#0d1117; border:1px solid #30363d; border-radius:10px; padding:16px 18px; }
  .card h2 { margin:0 0 12px; font-size:18px; }
  .muted { color:#8b949e; font-size:12px; }
  .grid { display:grid; gap:12px; max-width:960px; }
  .two-cols { display:grid; grid-template-columns: 1fr 1fr; gap:14px; }
  .row { display:grid; grid-template-columns: 220px 1fr; gap:14px; align-items:center; }
  input[type="text"], input[type="number"], select {
    width:100%; padding:9px 10px; background:#0d1117; border:1px solid #21262d; border-radius:.35rem; color:#e6edf3;
  }
  .save-bar { display:flex; align-items:center; gap:12px; margin-top:8px; }
  .btn { background:#238636; color:#fff; border:none; border-radius:6px; padding:8px 14px; cursor:pointer; }
  .btn:disabled { opacity:.6; cursor:not-allowed; }
  .msg { font-size:13px; }
  .section-title { margin:12px 0 6px; font-weight:600; font-size:14px; color:#c9d1d9; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="settings-wrap">
  <div class="card" style="flex:1;">
    <h2>Settings</h2>
    <form id="settingsForm" action="<?= site_url('settings') ?>" method="post">
      <?= csrf_field() ?>
      <div class="grid">

        <div class="section-title">Paths</div>

        <div class="row">
          <div class="lbl"><strong>GameData</strong> <span class="muted">(<?= esc($cfg->envGameData) ?>)</span></div>
          <input name="GameData" type="text" value="<?= esc($values['GameData'] ?? '') ?>">
        </div>

        <div class="row">
          <div class="lbl"><strong>MyMods</strong> <span class="muted">(<?= esc($cfg->envMyMods) ?>)</span></div>
          <input name="MyMods" type="text" value="<?= esc($values['MyMods'] ?? '') ?>">
        </div>

        <div class="row">
          <div class="lbl"><strong>UnpackedMods</strong> <span class="muted">(<?= esc($cfg->envUnpackedMods) ?>)</span></div>
          <input name="UnpackedMods" type="text" value="<?= esc($values['UnpackedMods'] ?? '') ?>">
        </div>

        <div class="section-title">MongoDB</div>

        <div class="row">
          <div class="lbl"><strong>URI</strong> <span class="muted">(mongo.default.uri)</span></div>
          <input name="mongo.default.uri" type="text" value="<?= esc($values['mongo.default.uri'] ?? '') ?>">
        </div>

        <div class="row">
          <div class="lbl"><strong>Database</strong> <span class="muted">(mongo.default.db)</span></div>
          <input name="mongo.default.db" type="text" value="<?= esc($values['mongo.default.db'] ?? '') ?>">
        </div>

        <div class="row">
          <div class="lbl"><strong>Collection</strong> <span class="muted">(mongo.default.collection)</span></div>
          <input name="mongo.default.collection" type="text" value="<?= esc($values['mongo.default.collection'] ?? '') ?>">
        </div>

        <div class="row">
          <div class="lbl"><strong>appName</strong> <span class="muted">(mongo.client.appName)</span></div>
          <input name="mongo.client.appName" type="text" value="<?= esc($values['mongo.client.appName'] ?? '') ?>">
        </div>

        <?php
          $rwRaw = $values['mongo.client.retryWrites'] ?? '';
          $rwStr = is_bool($rwRaw) ? ($rwRaw ? 'true' : 'false') : strtolower((string)$rwRaw);
        ?>
        <div class="row">
          <div class="lbl"><strong>retryWrites</strong> <span class="muted">(mongo.client.retryWrites)</span></div>
          <select name="mongo.client.retryWrites" id="retryWrites">
            <option value="">(leave unchanged)</option>
            <option value="true" <?= $rwStr === 'true' ? 'selected' : '' ?>>True</option>
            <option value="false" <?= $rwStr === 'false' ? 'selected' : '' ?>>False</option>
            <option value="1" <?= $rwStr === '1' ? 'selected' : '' ?>>1</option>
            <option value="0" <?= $rwStr === '0' ? 'selected' : '' ?>>0</option>
          </select>
        </div>

        <div class="row">
          <div class="lbl"><strong>serverSelectionTimeoutMS</strong> <span class="muted">(mongo.client.serverSelectionTimeoutMS)</span></div>
          <input name="mongo.client.serverSelectionTimeoutMS" type="number" value="<?= esc($values['mongo.client.serverSelectionTimeoutMS'] ?? '') ?>">
        </div>

        <div class="row">
          <div class="lbl"><strong>connectTimeoutMS</strong> <span class="muted">(mongo.client.connectTimeoutMS)</span></div>
          <input name="mongo.client.connectTimeoutMS" type="number" value="<?= esc($values['mongo.client.connectTimeoutMS'] ?? '') ?>">
        </div>

        <div class="row">
          <div class="lbl"><strong>socketTimeoutMS</strong> <span class="muted">(mongo.client.socketTimeoutMS)</span></div>
          <input name="mongo.client.socketTimeoutMS" type="number" value="<?= esc($values['mongo.client.socketTimeoutMS'] ?? '') ?>">
        </div>

        <?php
          $wRaw = (string) ($values['mongo.client.w'] ?? '');
          $wLower = strtolower($wRaw);
          $isCustom = !in_array($wLower, ['0','1','majority']) && $wRaw !== '';
        ?>
        <div class="row">
          <div class="lbl"><strong>w</strong> <span class="muted">(mongo.client.w)</span></div>
          <div class="two-cols">
            <select id="wSelect">
              <option value="">(leave unchanged)</option>
              <option value="1" <?= $wLower === '1' ? 'selected' : '' ?>>1 (primary)</option>
              <option value="0" <?= $wLower === '0' ? 'selected' : '' ?>>0 (unacknowledged)</option>
              <option value="majority" <?= $wLower === 'majority' ? 'selected' : '' ?>>majority</option>
              <option value="custom" <?= $isCustom ? 'selected' : '' ?>>custom…</option>
            </select>
            <input id="wCustom" name="mongo.client.w" type="text" placeholder="e.g., 2" value="<?= esc($isCustom ? $wRaw : '') ?>" style="<?= $isCustom ? '' : 'display:none' ?>">
          </div>
        </div>

        <div class="row">
          <div class="lbl"><strong>readPreference</strong> <span class="muted">(mongo.client.readPreference)</span></div>
          <input name="mongo.client.readPreference" type="text" value="<?= esc($values['mongo.client.readPreference'] ?? '') ?>">
        </div>

        <div class="row">
          <div class="lbl"><strong>username</strong> <span class="muted">(mongo.client.username)</span></div>
          <input name="mongo.client.username" type="text" value="<?= esc($values['mongo.client.username'] ?? '') ?>">
        </div>

        <div class="row">
          <div class="lbl"><strong>password</strong> <span class="muted">(mongo.client.password)</span></div>
          <input name="mongo.client.password" type="text" value="<?= esc($values['mongo.client.password'] ?? '') ?>">
        </div>

        <div class="row">
          <div class="lbl"><strong>authSource</strong> <span class="muted">(mongo.client.authSource)</span></div>
          <input name="mongo.client.authSource" type="text" value="<?= esc($values['mongo.client.authSource'] ?? '') ?>">
        </div>

        <div class="section-title">App</div>

        <div class="row">
          <div class="lbl"><strong>baseURL</strong> <span class="muted">(app.baseURL)</span></div>
          <input name="app.baseURL" type="text" value="<?= esc($values['app.baseURL'] ?? '') ?>">
        </div>

        <div class="row">
          <div class="lbl"><strong>indexPage</strong> <span class="muted">(app.indexPage)</span></div>
          <input name="app.indexPage" type="text" value="<?= esc($values['app.indexPage'] ?? '') ?>">
        </div>

        <div class="save-bar">
          <button class="btn" type="submit" id="saveBtn">Save</button>
          <span class="msg" id="saveMsg"></span>
        </div>
      </div>
    </form>
  </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
// Map wSelect to actual posted field 'mongo.client.w'
(function(){
  const sel = document.getElementById('wSelect');
  const custom = document.getElementById('wCustom');
  if (!sel || !custom) return;
  const sync = () => {
    if (sel.value === '' ) { custom.style.display = 'none'; custom.value = ''; }
    else if (sel.value === 'custom') { custom.style.display = ''; /* keep whatever user types */ }
    else { custom.style.display = 'none'; custom.value = sel.value; }
  };
  sel.addEventListener('change', sync);
  sync();
})();

document.getElementById('settingsForm').addEventListener('submit', async function(ev){
  ev.preventDefault();
  const form = ev.target;
  const btn = document.getElementById('saveBtn');
  const msg = document.getElementById('saveMsg');
  btn.disabled = true; msg.textContent = 'Saving…'; msg.style.color = '#8b949e';
  try {
    const data = new FormData(form);
    const r = await fetch(form.action, { method: 'POST', body: data, headers: { 'Accept':'application/json' } });
    if (!r.ok) throw new Error('HTTP ' + r.status);
    const json = await r.json();
    if (json && json.ok) { msg.textContent = 'Saved'; msg.style.color = '#7ee787'; }
    else { msg.textContent = 'Save failed'; msg.style.color = '#ffa198'; }
  } catch (e) {
    console.error(e);
    msg.textContent = 'Save failed'; msg.style.color = '#ffa198';
  } finally {
    btn.disabled = false;
    setTimeout(() => { msg.textContent = ''; }, 2000);
  }
});
</script>
<?= $this->endSection() ?>
