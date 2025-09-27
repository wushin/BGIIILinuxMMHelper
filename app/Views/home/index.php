<?= $this->extend('layouts/app') ?>

<?= $this->section('head') ?>
<link rel="stylesheet" href="<?= base_url('assets/vendor/github-markdown.min.css') ?>">
<style>
  .markdown-body{box-sizing:border-box;min-width:200px;max-width:980px;margin:0 auto;padding:24px}
  @media (max-width:768px){.markdown-body{padding:16px}}
  /* Optional: style the permalink symbol more like GitHub’s anchor (hidden until hover) */
  .markdown-body h1 .heading-permalink,
  .markdown-body h2 .heading-permalink,
  .markdown-body h3 .heading-permalink,
  .markdown-body h4 .heading-permalink,
  .markdown-body h5 .heading-permalink,
  .markdown-body h6 .heading-permalink { visibility:hidden; text-decoration:none; margin-right:.25em }
  .markdown-body h1:hover .heading-permalink,
  .markdown-body h2:hover .heading-permalink,
  .markdown-body h3:hover .heading-permalink,
  .markdown-body h4:hover .heading-permalink,
  .markdown-body h5:hover .heading-permalink,
  .markdown-body h6:hover .heading-permalink { visibility:visible }
  /* Optional: GitHub-like copy button on code blocks */
  .gh-codeblock{position:relative}
  .gh-copybtn{position:absolute;top:8px;right:8px;border:1px solid #30363d;border-radius:6px;background:#161b22;color:#c9d1d9;padding:4px 8px;font-size:12px;cursor:pointer}
  .gh-copied{position:absolute;top:8px;right:70px;background:#1f6f3f;border:1px solid #2ea043;color:#fff;padding:2px 6px;border-radius:6px;font-size:12px}
</style>
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

