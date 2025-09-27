<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="card">
  <h2 style="margin-top:0">Paths</h2>
  <form id="settingsForm" action="<?= site_url('settings') ?>" method="post">
    <?= csrf_field() ?>
    <div style="display:grid; gap:12px; max-width:900px;">
      <label>
        <div><strong>GameData</strong> <span class="muted">(<?= esc($cfg->envGameData) ?>)</span></div>
        <input name="GameData" type="text" value="<?= esc($values['GameData']) ?>" style="width:100%;padding:.5rem;background:#0b0f14;border:1px solid #21262d;border-radius:.35rem;color:#e6edf3;">
      </label>
      <label>
        <div><strong>MyMods</strong> <span class="muted">(<?= esc($cfg->envMyMods) ?>)</span></div>
        <input name="MyMods" type="text" value="<?= esc($values['MyMods']) ?>" style="width:100%;padding:.5rem;background:#0b0f14;border:1px solid #21262d;border-radius:.35rem;color:#e6edf3;">
      </label>
      <label>
        <div><strong>UnpackedMods</strong> <span class="muted">(<?= esc($cfg->envUnpackedMods) ?>)</span></div>
        <input name="UnpackedMods" type="text" value="<?= esc($values['UnpackedMods']) ?>" style="width:100%;padding:.5rem;background:#0b0f14;border:1px solid #21262d;border-radius:.35rem;color:#e6edf3;">
      </label>
      <div>
        <button type="submit" style="padding:.5rem .8rem;background:#238636;border:1px solid #2ea043;border-radius:.35rem;color:#fff;cursor:pointer;">Save</button>
        <span id="saveMsg" class="muted" style="margin-left:8px;"></span>
      </div>
    </div>
  </form>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
document.getElementById('settingsForm').addEventListener('submit', async function(ev){
  ev.preventDefault();
  const form = ev.target;
  const data = new FormData(form);
  const r = await fetch(form.action, { method: 'POST', body: data, headers: { 'Accept':'application/json' }});
  const msg = document.getElementById('saveMsg');
  if (!r.ok) { msg.textContent = 'Save failed'; msg.style.color = '#ffa198'; return; }
  const json = await r.json().catch(()=>null);
  if (json && json.ok) { msg.textContent = 'Saved'; msg.style.color = '#7ee787'; }
  else { msg.textContent = 'Save failed'; msg.style.color = '#ffa198'; }
});
</script>
<?= $this->endSection() ?>

