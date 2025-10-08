<?= $this->extend('layouts/app') ?>

<?= $this->section('head') ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="card" style="padding:0;">
  <article id="readmeContainer" class="markdown-body">
    <?= $readmeHtml ?: '<p class="muted" style="padding:16px">README not found.</p>' ?>
  </article>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
// Optional: copy button on fenced code (server-rendered HTML)
(function(){
  const root=document.getElementById('readmeContainer');
  if(!root) return;
  root.querySelectorAll('pre').forEach(pre=>{
    const wrap=document.createElement('div'); wrap.className='gh-codeblock';
    pre.parentElement.insertBefore(wrap, pre); wrap.appendChild(pre);
    const btn=document.createElement('button'); btn.className='gh-copybtn'; btn.type='button'; btn.textContent='Copy';
    wrap.appendChild(btn);
    function copy(){
      navigator.clipboard.writeText(pre.innerText).then(()=>{
        const tag=document.createElement('div'); tag.className='gh-copied'; tag.textContent='Copied';
        wrap.appendChild(tag); setTimeout(()=>tag.remove(),1200);
      }).catch(()=>{});
    }
    btn.addEventListener('click', copy);
    pre.addEventListener('click', copy);
  });
})();
</script>
<?= $this->endSection() ?>

