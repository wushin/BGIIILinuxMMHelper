<?php
use Config\LsxRegions;
$cfg = config(LsxRegions::class);
$dlg_tags = json_encode($cfg->dlg_tags);
$flagTypes = json_encode($cfg->flagTypes);
helper('url');
$root   = $root ?? '';
$slug   = $slug ?? '';
$tree   = $tree ?? [];
$path   = $path ?? '';
$myMods = $myMods ?? [];

/** Render recursive tree nodes */
function renderTree(array $nodes, ?string $selectedRel = '', int $depth = 0) {
    foreach ($nodes as $n) {
        $indent = $depth * 14;
        $isDir  = !empty($n['isDir']);
        $name   = $n['name'] ?? '';
        $rel    = $n['rel']  ?? '';
        $ext    = $n['ext']  ?? '';

        $cls = $isDir ? 'node dir' : 'node file ext-'.htmlspecialchars($ext);
        $isActive = (!$isDir && $selectedRel !== '' && $rel === $selectedRel);
        if ($isActive) { $cls .= ' active'; }
        echo '<div class="'.$cls.'" data-rel="'.htmlspecialchars($rel).'" style="margin-left:'.$indent.'px">';
        echo $isDir ? '📁 ' : '📄 ';
        echo '<span class="node-label" data-name="'.htmlspecialchars($name).'">'.htmlspecialchars($name).'</span>';
        echo '</div>';

        if ($isDir && !empty($n['children']) && is_array($n['children'])) {
            renderTree($n['children'], $selectedRel, $depth+1);
        }
    }
}
?>

<?= $this->extend('layouts/app') ?>

<?= $this->section('head') ?>
<meta name="csrf-token" content="<?= csrf_hash() ?>">
<style>
:root{--left-col:360px;--right-col:400px}

/* Layout shell */
.mod-wrap{margin:0 auto;padding:12px}
.columns{display:grid;grid-template-columns:var(--left-col) 1fr var(--right-col);gap:12px;align-items:stretch}
#colLeft{display:flex;flex-direction:column;gap:12px;height:calc(100vh - var(--header-h) - 24px);min-height:0}

.sidebar-card,.panel{background:#0d1117;border:1px solid #21262d;border-radius:.5rem;box-sizing:border-box;display:flex;flex-direction:column;min-height:0;overflow:visible}
.sidebar-card{flex:0 0 auto}
.sidebar-card .head,.panel>.head{padding:.6rem .8rem;border-bottom:1px solid #21262d;font-weight:600;display:flex;align-items:center;justify-content:space-between;gap:.5rem}
.sidebar-card .body{padding:.6rem .6rem .8rem;overflow:auto;min-height:0}

.tree-panel{flex:1 1 auto;min-height:0;overflow:hidden}
.tree-panel .body{padding:0;overflow:hidden;flex:1 1 auto;min-height:0}
#tree{height:100%;padding:.6rem .4rem .8rem}

/* Left list / tree */
.mods-ul{list-style:none;margin:0;padding:0}
.mods-ul li{margin:0}
.mod-link{display:block;padding:.22rem .36rem;border-radius:.25rem;text-decoration:none;color:#c9d1d9;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.mod-link:hover{background:#161b22}
.mod-link.active{background:#14324a;outline:1px solid #1f6feb}

.node{padding:.2rem .4rem;border-radius:.25rem;cursor:pointer;user-select:none}
.node:hover{background:#161b22}
.node.dir{color:#c9d1d9}
.node.file{color:#a5d6ff}
.node.highlight{background:#14324a;outline:1px solid #1f6feb}
.node-label{display:inline-block}

.panel.tall{height:calc(100vh - var(--header-h) - 24px);min-height:0}
.panel>.body{padding:.8rem;overflow:auto;min-height:0}

/* Utils */
.hidden{display:none!important}
.muted{color:#8b949e}
pre,code{white-space:pre-wrap;word-break:break-word}
img.dynImg{max-width:100%;height:auto;display:block}
.badgebar{display:flex;gap:8px;margin-bottom:.5rem;flex-wrap:wrap}
.badge{font-size:.75rem;padding:.15rem .4rem;border:1px solid #21262d;border-radius:.25rem;background:#0c1320}
.flash-red{background:#3a1114;border:1px solid #5a1a1f;padding:.4rem;border-radius:.25rem}
.btn-icon{background:#0b1624;border:1px solid #21262d;color:#c9d1d9;border-radius:.35rem;padding:.25rem .5rem;font-size:.85rem;cursor:pointer}
.btn-icon:hover{background:#111a2a}

/* Selected node highlight */
.node.active{background:rgba(59,130,246,.15);outline:1px solid rgba(59,130,246,.65);border-radius:6px}
.node.active .node-label{font-weight:600}
.tree-panel .node:hover{background:rgba(255,255,255,.06)}

/* ---- Dialog meta (left/top meta panel) ---- */
#dialogMeta .dlg-h4,#dialogMeta .dlg-h5{margin:10px 0 6px;font-weight:600;color:#c9d1d9}
#dialogMeta .dlg-row{display:grid;gap:8px 12px;align-items:center;padding:4px 0}
#dialogMeta .dlg-label{color:#8b949e;font-size:.85rem}
#dialogMeta .dlg-val{color:#c9d1d9;font-size:.92rem}
#dialogMeta .dlg-val.mono{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;background:#21262d;border:1px solid #30363d;border-radius:8px;padding:3px 8px;line-height:1.25}
#dialogMeta .dlg-val.mono.copyable{cursor:pointer;position:relative}
#dialogMeta .dlg-val.mono.copyable::after{content:"Copy";position:absolute;right:6px;top:50%;transform:translateY(-50%);font-size:.7rem;color:#8b949e;opacity:0;transition:.15s}
#dialogMeta .dlg-val.mono.copyable:hover::after{opacity:.9}
#dialogMeta .dlg-val.editable{cursor:text}
#dialogMeta .dlg-val[contenteditable="true"]{outline:1px dashed #58a6ff;background:#0f1620;border-radius:8px;border:1px solid #1f6feb}
#dialogMeta .dlg-chiprow{display:flex;flex-wrap:wrap;gap:6px}
#dialogMeta .chip{display:inline-flex;gap:6px;padding:4px 8px;border-radius:999px;background:#21262d;border:1px solid #30363d;color:#c9d1d9;font-size:.85rem}
#dialogMeta .chip-narrator{background:rgba(210,168,255,.12);color:#d2a8ff;border-color:rgba(210,168,255,.35)}
#dialogMeta .dlg-table{width:100%;border-collapse:collapse;font-size:.88rem}
#dialogMeta .dlg-table th,#dialogMeta .dlg-table td{padding:6px 8px;border-bottom:1px solid #30363d;color:#c9d1d9}
#dialogMeta .dlg-table th{text-align:left;color:#8b949e}
#dialogMeta .dlg-problems{display:flex;flex-wrap:wrap;gap:6px;margin-top:6px}
#dialogMeta .dlg-problems .pill{padding:3px 8px;border-radius:999px;font-size:.8rem;background:rgba(48,54,61,.55);color:#c9d1d9;border:1px solid #30363d}

/* ---- Dialog node (article) ---- */
.dlg-node{background:#0d1117;border:1px solid #21262d;border-radius:10px;box-shadow:0 0 0 1px rgba(48,54,61,.35),0 8px 24px rgba(1,4,9,.35);margin:6px 0}
.dlg-node:hover{border-color:#30363d;box-shadow:0 0 0 1px rgba(48,54,61,.55),0 8px 24px rgba(1,4,9,.55)}
.dlg-node.collapsed .dlg-node-body{display:none}

.dlg-node-hd{display:flex;align-items:center;gap:8px;padding:8px 10px;border-bottom:1px solid #21262d;background:#0f141b;cursor:pointer}
.dlg-node-hd .head{font-weight:600;color:#c9d1d9;padding:2px 6px;border-radius:6px}
.dlg-node-hd .head.copyable:hover{background:rgba(88,166,255,.12)}

.dlg-node-body{padding:10px 12px;display:grid;gap:12px}

/* Row style inside article */
.dlg-node-body .dlg-val.mono{display:flex;align-items:center;gap:10px;padding:4px 0;color:#c9d1d9;font-family:ui-monospace,SFMono-Regular,Menlo,monospace}
.dlg-node-body .dlg-val.mono .k,.dlg-node-body .dlg-val.mono .k-speaker{color:#8b949e;font-weight:600;min-width:120px}

/* Inline-edit (article) */
.dlg-node-body .editable[contenteditable]{display:inline-block;min-width:6ch;padding:4px 6px;border:1px dashed transparent;border-radius:6px;transition:border-color .15s,background-color .15s,box-shadow .15s}
.dlg-node-body .editable[contenteditable]:hover{border-color:#30363d;background:rgba(88,166,255,.06)}
.dlg-node-body .editable[contenteditable]:focus{border-color:#58a6ff;border-style:solid;background:#0b1320;box-shadow:0 0 0 3px rgba(88,166,255,.25)}

/* Inputs/selects in article */
.dlg-node select.dlg-tag-select{min-width:14ch;background:#0d1117;color:#c9d1d9;border:1px solid #30363d;border-radius:6px;padding:6px 28px 6px 8px;outline:0;appearance:none;background-image:linear-gradient(45deg,transparent 50%,#8b949e 50%),linear-gradient(135deg,#8b949e 50%,transparent 50%),linear-gradient(to right,transparent,transparent);background-position:calc(100% - 16px) 50%,calc(100% - 10px) 50%,0 0;background-size:6px 6px,6px 6px,100% 100%;background-repeat:no-repeat}
.dlg-node select.dlg-tag-select:focus{border-color:#58a6ff;box-shadow:0 0 0 3px rgba(88,166,255,.3);background:#0b1320}

.dlg-node input[type="text"],.dlg-node input:not([type]),.dlg-node textarea{background:#0d1117;color:#c9d1d9;border:1px solid #30363d;border-radius:6px;padding:6px 8px;outline:0}
.dlg-node input:focus,.dlg-node textarea:focus{border-color:#58a6ff;box-shadow:0 0 0 3px rgba(88,166,255,.3);background:#0b1320}
.dlg-node input[type="checkbox"]{accent-color:#1f6feb;transform:translateY(1px)}

/* Children chips (article) */
.dlg-node-body .dlg-val.mono ul{list-style:none;margin:0;padding:0;display:flex;flex-wrap:wrap;gap:6px}
.chip-uuid{display:inline-flex;align-items:center;gap:6px;padding:4px 8px;border-radius:999px;background:#21262d;border:1px solid #30363d;color:#c9d1d9;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:.85rem;text-decoration:none}
.chip-uuid .short{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.chip-uuid:hover{border-color:#58a6ff;background:rgba(88,166,255,.12)}
.endnote{margin-top:6px;color:#8b949e;font-style:italic}

/* Flags block */
.flag-group{margin-top:10px;border:1px solid #30363d;border-radius:8px;background:#0f1319}
.flag-title{padding:6px 8px;border-bottom:1px solid #30363d;font-weight:600;color:#c9d1d9;display:flex;justify-content:space-between;align-items:center}
.flag-title .count{color:#8b949e;font-weight:400}
.flag-group ul{list-style:none;margin:0;padding:8px;display:grid;gap:8px}
.flag-li{display:grid;grid-template-columns:repeat(4,max-content) 1fr;gap:6px 12px;align-items:center}
.flag-li code{display:inline-flex;align-items:center;gap:6px;background:#21262d;border:1px solid #30363d;border-radius:6px;padding:2px 6px;font-size:.8rem;color:#c9d1d9}
.flag-type-select.mono{background:#0d1117;color:#c9d1d9;border:1px solid #30363d;border-radius:6px;padding:4px 6px}
.flag-li .editable-toggle{cursor:pointer;padding:2px 6px;border-radius:6px;background:#10243a;border:1px solid #30363d}
.flag-li .editable-toggle:hover{border-color:#58a6ff;background:rgba(88,166,255,.12)}

/* Text lines block inside article */
.texts{display:grid;gap:10px}
.texts .line{display:grid;gap:8px;padding:10px;background:#0f1620;border:1px solid #30363d;border-radius:10px}
.texts .line:hover{border-color:#3b82f6}
.texts .dlg-val{display:flex;align-items:center;gap:8px;color:#c9d1d9;font-size:.9rem}
.texts .dlg-val .mono{font-family:ui-monospace,SFMono-Regular,Menlo,monospace}
.texts .dlg-val input[type="text"]{flex:1 1 auto;min-width:24ch}
.texts .line-text{flex:1 1 100%;margin-top:2px;padding-left:10px;border-left:2px solid #30363d;color:#c9d1d9;line-height:1.35;word-break:break-word}
.texts .line-missing{flex:1 1 100%;padding-left:10px;border-left:2px solid #5a1a1f;color:#ffa198;font-style:italic}

/* Speaker label tweak */
.k-speaker{font-size:1rem;font-weight:600}

/* Copy affordance on header id */
.copyable{position:relative}
.copyable:hover::after{content:"Click to copy";position:absolute;left:0;top:100%;transform:translateY(4px);background:#111827;color:#8b949e;border:1px solid #30363d;border-radius:6px;padding:2px 6px;font-size:11px;white-space:nowrap}

/* Quick highlight pulse utility */
.chip-flash{box-shadow:0 0 0 2px #1f6feb,0 0 12px rgba(31,111,235,.55);background:rgba(31,111,235,.18);transition:box-shadow .2s ease,background .2s ease}
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div
  class="mod-wrap"
  id="modLayout"
  data-root="<?= esc($root) ?>"
  data-slug="<?= esc($slug) ?>"
  data-basekey="<?= esc($root . '/' . $slug) ?>"
  data-selectedfile="<?= esc($selectedFile ?? '') ?>"
>
  <div class="columns">
    <!-- LEFT: MyMods card + Files tree panel -->
    <div id="colLeft">
      <div class="sidebar-card" id="modsCard">
        <div class="head">
          <span>MyMods — Directories</span>
        </div>
        <div class="body" id="modsList">
          <?php if (!empty($myMods) && is_array($myMods)): ?>
            <ul class="mods-ul">
              <?php foreach ($myMods as $name): ?>
                <?php $isActive = isset($slug) && $slug === $name; ?>
                <li>
                  <a
                    class="mod-link <?= $isActive ? 'active' : '' ?>"
                    href="<?= site_url('mods/' . ($root ?? 'MyMods') . '/' . $name) ?>"
                    aria-current="<?= $isActive ? 'page' : 'false' ?>"
                    title="<?= esc($name) ?>"
                  ><?= esc($name) ?></a>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <div class="muted">No mods found in MyMods.</div>
          <?php endif; ?>
        </div>
      </div>

      <aside class="panel tree-panel" id="treePanel">
        <div class="head">
          <span id="filesTitle">Files &amp; Folders (all depths)</span>
          <button id="toggleSidebar" class="btn-icon" type="button" title="Collapse/Expand">⇔</button>
        </div>
        <div class="body">
          <div id="tree">
            <?php renderTree($tree, $selectedFile ?? '', 0); ?>
          </div>
        </div>
      </aside>
    </div>

    <!-- MIDDLE: Viewer -->
    <section class="panel tall">
      <div class="head">Viewer</div>
      <div class="body" id="viewer">
        <p class="muted">Select a file to preview here.</p>
      </div>
    </section>

    <!-- RIGHT: Details -->
    <aside class="panel tall">
      <div class="head">Tags</div>
      <div class="body" id="meta">
        <div class="muted">Region, type, and other metadata will appear here.</div>
      </div>
      <div class="head hidden" id="dlgHead">Dialog</div>
      <div class="body hidden" id="dialogMeta">
        <div class="muted">Dialog-specific tags and speakers will appear here.</div>
      </div>
    </aside>
  </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
// --- HTML escape helper (global) ---
window.esc = window.esc || function esc(v) {
  const s = String(v ?? '');
  const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;', '`': '&#96;' };
  return s.replace(/[&<>"'`]/g, ch => map[ch]);
};

(function(){
  const rootEl  = document.getElementById('modLayout');
  const root    = rootEl?.dataset?.root || '';
  const slug    = rootEl?.dataset?.slug || '';
  window.__modsRoot = root;
  window.__modsSlug = slug;
  
  const tree    = document.getElementById('tree');
  const view    = document.getElementById('viewer');
  const meta    = document.getElementById('meta');
  const colLeft = document.getElementById('colLeft');

  function hideDialogSection(){ dlgHead?.classList.add('hidden'); dialogMeta?.classList.add('hidden'); dialogMeta.innerHTML=''; }
  const dlgHead = document.getElementById('dlgHead');
  const dialogMeta = document.getElementById('dialogMeta');

  function badgeBar(obj) {
    const items=[];
    if (obj.kind)        items.push(`<span class="badge">kind: ${esc(obj.kind)}</span>`);
    if (obj.ext)         items.push(`<span class="badge">ext: ${esc(obj.ext)}</span>`);
    if (obj.regionGroup) items.push(`<span class="badge">group: ${esc(obj.regionGroup)}</span>`);
    if (obj.region)      items.push(`<span class="badge">region: ${esc(obj.region)}</span>`);
    return `<div class="badgebar">${items.join(' ')}</div>`;
  }
  function renderJson(obj) { return `<pre><code>${esc(JSON.stringify(obj, null, 2))}</code></pre>`; }
  function relToUrl(rel) {
    const segs = rel.split('/').filter(Boolean).map(encodeURIComponent);
    return `/mods/${encodeURIComponent(root)}/${encodeURIComponent(slug)}/${segs.join('/')}` + `?format=json`;
  }
  window.relToUrl = relToUrl;

  function setUrlSelection(rel) {
    try {
      const url = new URL(window.location.href);
      if (rel) {
        url.searchParams.set('sel', rel);
      } else {
        url.searchParams.delete('sel');
      }
      history.replaceState(null, '', url);
    } catch (_) {}
  }

  function getUrlSelection() {
    try {
      const url = new URL(window.location.href);
      return url.searchParams.get('sel') || '';
    } catch (_) { return ''; }
  }

  async function openRel(rel) {
  try {
    view.innerHTML = `<div class="muted">Loading…</div>`;

    const r = await fetch(relToUrl(rel), { headers: { 'Accept': 'application/json' } });
    if (!r.ok) throw new Error(`HTTP ${r.status}`);
    const data = await r.json();

    meta.innerHTML = badgeBar(data);
    persistSelection(rel); 
    saveLocal(rel);
    setUrlSelection(rel);

    const result = data.result ?? data.payload ?? data.data ?? {};
    const kind = (data.kind ?? result.kind ?? 'unknown').toLowerCase();
    const ext  = (data.ext  ?? result.ext  ?? '').toLowerCase();
    const metaObj = (data.meta ?? result.meta ?? {});
    const regionGroup = (metaObj.regionGroup ?? '').toLowerCase();
    const dlg = metaObj.dialog ?? null;

    const raw = (typeof data.raw === 'string')
      ? data.raw
      : (typeof result.raw === 'string' ? result.raw : '');

    const textLikeExts  = ['txt','khn','anc','ann','cln','clc'];
    const textLikeKinds = ['txt','khn'];
    const isTextLike    = textLikeExts.includes(ext) || textLikeKinds.includes(kind);

    if (kind === 'lsx' && regionGroup === 'dialog' && dlg) {
      meta.innerHTML = badgeBar(data);
      // dialog section handled separately below
    if (dlg) {
        dlgHead?.classList.remove('hidden');
        dialogMeta?.classList.remove('hidden');
        dialogMeta.innerHTML = renderDialogTags(dlg);
        enhanceDialogMeta();
      } else {
        dlgHead?.classList.add('hidden');
        dialogMeta?.classList.add('hidden');
        dialogMeta.innerHTML = '';
      }
      view.innerHTML = renderDialogNodes(dlg, metaObj);

      // Wire up dialog edit state & save binding (Step 4)
      setCurrentFileForDialogEditing(data, {relPath: rel});

      bindDialogSaveButton();

      // Delegate clicks from the viewer once
      if (!view.__dlgBound) {
        view.addEventListener('click', (e) => {
          // Click on paramval to highlight the speaker chip
          const pv = e.target.closest('code.paramval-link');
          if (pv) {
            const idx = parseInt(pv.getAttribute('data-spk'), 10);
            const scope = document.getElementById('dialogMeta');
            if (scope) {
              let chip = null;
              if (idx === -666) {
                // Narrator
                chip = scope.querySelector('.chip-narrator');
              } else {
                // Find the chip whose text starts with "#<idx> "
                const chips = scope.querySelectorAll('.dlg-chiprow .chip');
                for (let i = 0; i < chips.length; i++) {
                  const txt = (chips[i].textContent || '').trim();
                  if (txt.indexOf(`#${idx} `) === 0) { chip = chips[i]; break; }
                }
              }
              if (chip) {
                chip.classList.add('chip-flash');
                try { chip.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); }
                catch (_) { chip.scrollIntoView(); }
                setTimeout(() => chip.classList.remove('chip-flash'), 900);
              }
            }
            return;
          }

          // 1) Click on a local node link: open the target node and scroll to it
          const aLocal = e.target.closest('a.link-local');
          if (aLocal) {
            e.preventDefault();
            const href = aLocal.getAttribute('href') || '';
            const id = href.charAt(0) === '#' ? href.slice(1) : href; // e.g. "node-<uuid>"
            const target = document.getElementById(id);
            if (target) {
              target.classList.remove('collapsed');
              target.setAttribute('aria-expanded', 'true');
              try { target.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); }
              catch (_) { target.scrollIntoView(); }
            }
            return;
          }

          // 2) Click header (or its Toggle button): toggle open/closed
          const headOrBtn = e.target.closest('.dlg-node-hd, .dlg-node-hd .toggle');
          if (!headOrBtn) return;
          const node = headOrBtn.closest('.dlg-node');
          if (!node) return;
          node.classList.toggle('collapsed');
          node.setAttribute('aria-expanded', node.classList.contains('collapsed') ? 'false' : 'true');
        });
        view.__dlgBound = true;
      }

      // Delegate header or button click to toggle that node
      if (!view.__dlgBound) {
        view.addEventListener('click', (e) => {
          const headOrBtn = e.target.closest('.dlg-node-hd, .dlg-node-hd .toggle');
          if (!headOrBtn) return;
          const node = e.target.closest('.dlg-node');
          if (!node) return;
          node.classList.toggle('collapsed');
          node.setAttribute('aria-expanded', node.classList.contains('collapsed') ? 'false' : 'true');
        });
        view.__dlgBound = true;
      }

      // Expand / Collapse all
      document.getElementById('dlg-expand-all')?.addEventListener('click', () => {
        document.querySelectorAll('.dlg-node').forEach(n => n.classList.remove('collapsed'));
      });
      document.getElementById('dlg-collapse-all')?.addEventListener('click', () => {
        document.querySelectorAll('.dlg-node').forEach(n => n.classList.add('collapsed'));
      });
      return;
    }

    hideDialogSection();
      if (kind === 'image') {
      if (result.dataUri) {
        view.innerHTML = `<img class="dynImg" src="${esc(result.dataUri)}" alt="preview">`;
      } else if (ext === 'dds') {
        view.innerHTML = `<div class="muted">DDS preview unavailable (no Imagick DDS delegate).</div>`;
      } else {
        view.innerHTML = `<div class="muted">No preview.</div>`;
      }
      return;
    }

    hideDialogSection();
      if (isTextLike && raw) {
      view.innerHTML = `<pre><code>${esc(raw)}</code></pre>`;
      return;
    }

    if (kind === 'xml' || kind === 'lsx') {
      view.innerHTML = renderJson(result || data);
      return;
    }

    if (raw) {
      view.innerHTML = `<pre><code>${esc(raw)}</code></pre>`;
    } else {
      view.innerHTML = renderJson(result || data);
    }
  } catch (err) {
    view.innerHTML = `<div class="flash-red">Failed to load: ${esc(err.message || String(err))}</div>`;
  }
}

  // expose openRel if other code uses it
  window.openRel = openRel;

  // Open on click (files only)
  
  // Persist selection with CSRF; non-blocking
  
  // LocalStorage helpers for persistence fallback
  function lsKey() {
    const baseKey = document.getElementById('modLayout')?.dataset?.basekey || '';
    return baseKey ? ('mods:last:' + baseKey) : '';
  }
  function saveLocal(rel) {
    const k = lsKey();
    if (k && rel) try { localStorage.setItem(k, rel); } catch (_) {}
  }
  function loadLocal() {
    const k = lsKey();
    if (!k) return '';
    try { return localStorage.getItem(k) || ''; } catch (_) { return ''; }
  }
  function clearLocal() {
    const k = lsKey();
    if (!k) return;
    try { localStorage.removeItem(k); } catch (_) {}
  }
  function persistSelection(rel) {
    const baseKey = document.getElementById('modLayout')?.dataset?.basekey || '';
    if (!baseKey || !rel) return;
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    try {
      navigator.sendBeacon('/mods/selection', new Blob([new URLSearchParams({
        '<?= csrf_token() ?>': token,
        base: baseKey,
        path: rel
      })], { type: 'application/x-www-form-urlencoded' }));
    } catch (e) {
      fetch('/mods/selection', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
        body: new URLSearchParams({ '<?= csrf_token() ?>': token, base: baseKey, path: rel })
      }).catch(()=>{});
    }
  }
  tree.addEventListener('click', (e) => {
    const el = e.target.closest('.node');
    if (!el || el.classList.contains('dir')) return;
    document.querySelectorAll('.node.active').forEach(n => n.classList.remove('active'));
    el.classList.add('active');
    try { el.scrollIntoView({block:'nearest', inline:'nearest'}); } catch(_) {}
    const rel = el.getAttribute('data-rel') || '';
    persistSelection(rel); saveLocal(rel);
    openRel(rel);
  });

  // Auto-open remembered selection on load (server value, then ?sel=, then localStorage)
  (function(){
    const layout = document.getElementById('modLayout');
    const serverSelected = layout?.dataset?.selectedfile || '';

    // Build a set of available file rel paths under THIS root
    const available = new Set(
      Array.from(document.querySelectorAll('.node.file[data-rel]'))
        .map(n => n.getAttribute('data-rel') || '')
    );

    let rel = serverSelected;

    if (!rel) {
      // 1) Prefer URL deep-link first
      const urlRel = getUrlSelection();
      if (urlRel && available.has(urlRel)) {
        rel = urlRel;
      } else {
        // If URL has a selection that doesn't exist in this root, clean it up
        if (urlRel && !available.has(urlRel)) setUrlSelection('');

        // 2) Fall back to LocalStorage
        const saved = loadLocal();
        if (saved && available.has(saved)) {
          rel = saved;
        } else if (saved && !available.has(saved)) {
          // Clear stale cross-root value
          clearLocal();
        }
      }
    }

    if (rel) {
      const node = document.querySelector('.node[data-rel="' + rel.replace(/"/g,'\\"') + '"]');
      if (node) {
        node.classList.add('active');
        try { node.scrollIntoView({ block: 'nearest', inline: 'nearest' }); } catch (_) {}
      }
      if (typeof openRel === 'function') openRel(rel);
    }
  })();

  // Highlight helper for Fetch UUID widgets
  function clearHighlights() {
    document.querySelectorAll('.node.highlight').forEach(n=> n.classList.remove('highlight'));
  }
  function highlightAndScroll(query) {
    clearHighlights();
    if (!query) return;
    const nodes = Array.from(document.querySelectorAll('.node.file'));
    const hit = nodes.find(n => (n.textContent || '').toLowerCase().includes(query.toLowerCase()));
    if (hit) {
      hit.classList.add('highlight');
      hit.scrollIntoView({ block: 'center' });
    }
  }
  document.addEventListener('app:fetch-uuid', (e)=> highlightAndScroll(e.detail?.value || ''));
  document.addEventListener('app:fetch-contentuuid', (e)=> highlightAndScroll(e.detail?.value || ''));

  // -------- Auto-size LEFT width to longest item (mods + files) ----------
  function measureLeftWidth() {
    if (!colLeft) return;
    let widest = 0;

    // MyMods anchors
    colLeft.querySelectorAll('.mod-link').forEach(a => {
      const textW = Math.ceil(a.scrollWidth);
      const ROW_PAD = 16;
      const CARD_PAD = 14;
      const GUTTER  = 24;
      const lineW = textW + ROW_PAD + CARD_PAD + GUTTER;
      if (lineW > widest) widest = lineW;
    });

    // File nodes
    colLeft.querySelectorAll('.node-label').forEach(label => {
      const row = label.closest('.node');
      if (!row) return;
      const ml = parseFloat(row.style.marginLeft || '0') || 0;
      const textW = Math.ceil(label.scrollWidth);
      const ICON_W = 20, ROW_PAD = 16, BODY_PAD = 14, GUTTER = 24;
      const lineW = ml + ICON_W + ROW_PAD + BODY_PAD + textW + GUTTER;
      if (lineW > widest) widest = lineW;
    });

    const MIN = 360;
    const MAX = Math.floor(window.innerWidth * 0.48);
    const finalW = Math.max(MIN, Math.min(widest || MIN, MAX));
    document.documentElement.style.setProperty('--left-col', finalW + 'px');
  }
  measureLeftWidth();
  setTimeout(measureLeftWidth, 50);
  if (document.fonts && document.fonts.ready) {
    document.fonts.ready.then(measureLeftWidth).catch(()=>{});
  }
  window.addEventListener('resize', () => requestAnimationFrame(measureLeftWidth));

  // -------- Collapse/Expand left width to header width ----------
  const toggleBtn  = document.getElementById('toggleSidebar');
  const filesTitle = document.getElementById('filesTitle');
  let collapsed = false;

  function collapseToHeader() {
    const btnW   = toggleBtn ? toggleBtn.offsetWidth : 32;
    const titleW = filesTitle ? filesTitle.scrollWidth : 160;
    const PAD    = 32; // head padding + small gutter
    const w = Math.max(240, titleW + btnW + PAD);
    document.documentElement.style.setProperty('--left-col', w + 'px');
    collapsed = true;
  }
  function expandAuto() {
    collapsed = false;
    measureLeftWidth();
  }
  if (toggleBtn) {
    toggleBtn.addEventListener('click', () => {
      if (collapsed) expandAuto(); else collapseToHeader();
    });
  }
})();

function renderDialogTags(dlg) {
  const escHtml = (s) => esc(String(s ?? ''));
  const speakerChip = (s) => `<span class="chip">#${s.index} (${escHtml(s.mappingId)})</span>`;
  const narratorPresent = dlg?.speakers?.narrator?.present === true;

  const addressedRows = (dlg?.speakers?.addressed ?? []).map(m => {
    const to = (m.toIndex === -1) ? 'none' : (m.toIndex === -666 ? 'Narrator' : `#${m.toIndex}`);
    return `<tr><td>#${m.fromIndex}</td><td>${escHtml(to)}</td></tr>`;
  }).join('') || `<tr><td colspan="2" class="muted">none</td></tr>`;

  const chips = (dlg?.speakers?.list ?? []).map(speakerChip).join(' ')
    + (narratorPresent ? ' <span class="chip chip-narrator">Narrator</span>' : '');

  const category   = dlg?.category ?? '—';
  const timelineId = dlg?.timelineId ?? '—';
  const dialogUuid = dlg?.dialogUuid ?? dlg?.uuid ?? '—';

  // exactly the structure requested (no extra wrapper)
  return [
    `<div class="dlg-row"><span class="dlg-label">Category</span><span class="dlg-val mono editable" contenteditable="plaintext-only" data-dlg-edit="category">${escHtml(category)}</span></div>`,
    `<div class="dlg-row"><span class="dlg-label">TimelineID</span><span class="dlg-val mono editable" contenteditable="plaintext-only" data-dlg-edit="timelineId">${escHtml(timelineId)}</span></div>`,  
    `<div class="dlg-row"><span class="dlg-label">Dialog UUID</span><span class="dlg-val mono editable" contenteditable="plaintext-only" data-dlg-edit="dialogUuid" data-copy="${escHtml(dialogUuid)}">${escHtml(dialogUuid)}</span></div>`,
    `<h4 class="dlg-h4">Speakers</h4>`,
    `<div class="dlg-chiprow">${chips}</div>`,
    `<h5 class="dlg-h5">Default Addressed</h5>`,
    `<table class="dlg-table"><thead><tr><th>From</th><th>To</th></tr></thead><tbody>${addressedRows}</tbody></table>`,
    `<div class="dlg-problems"></div>`
  ].join('');
}

function renderDialogNodes(dlg, meta){
  const order = (dlg && dlg.roots && Array.isArray(dlg.roots.ordered)) ? dlg.roots.ordered : [];
  const nodes = Object.assign({}, (dlg && dlg.nodes) ? dlg.nodes : {});
  const seen = new Set(); const seq = [];
  const flagTypes = JSON.parse('<?php echo $flagTypes ?>');
  for (var i=0;i<order.length;i++){ var u=order[i]; if (nodes[u]) { seq.push([u, nodes[u]]); seen.add(u); } }
  for (var u in nodes){ if (!seen.has(u)) seq.push([u, nodes[u]]); }

  const spMap = new Map(((dlg && dlg.speakers && Array.isArray(dlg.speakers.list)) ? dlg.speakers.list : []).map(s => [s.index, s.mappingId || '']));

  // Editable flags renderer
  const flagList = (arr, title, nodeUuid) => {
    const list = Array.isArray(arr) ? arr : [];
    const cnt = list.length;
    if (!cnt) return '';

    const lis = list.map((f, idx) => {
      const hasParam = f.paramval !== null && f.paramval !== undefined;
      return `<li class="flag-li" data-node-uuid="${esc(nodeUuid)}" data-flag-idx="${idx}">
        <code>type
          <select class="flag-type-select mono"
                  data-flag-edit="type"
                  data-flag-which="${esc(title)}"
                  data-flag-idx="${idx}">
            ${(() => {
              const listed = Array.isArray(flagTypes) ? flagTypes : [];
              const cur    = String(f.type ?? '');
              const have   = listed.includes(cur);
              const opts   = listed.map(t =>
                `<option value="${esc(t)}"${t === cur ? ' selected' : ''}>${esc(t)}</option>`
              ).join('');
              // if current type isn’t in the list, include it so it doesn’t get lost
              const extra  = have || !cur ? '' :
                `<option value="${esc(cur)}" selected>${esc(cur)}</option>`;
              return extra + opts;
            })()}
          </select>
        </code>
        <code>
          <span class="editable mono">UUID <input type="text"
                data-flag-edit="UUID"
                data-flag-which="${esc(title)}"
                data-flag-idx="${idx}"
                value="${esc(f.UUID ?? '')}" />
          </span>
        </code>
        <code>
          <span class="editable-toggle">value <input type="checkbox"
                data-flag-edit="value"
                data-flag-which="${esc(title)}"
                data-flag-idx="${idx}"
                {(!!f.value) ? 'checked' : ''} />
          </span>
        </code>
        ${hasParam ? `
        <code>
          <span class="editable mono">paramval <input type="text"
                contenteditable="plaintext-only"
                data-flag-edit="paramval"
                data-flag-which="${esc(title)}"
                data-flag-idx="${idx}"
                value="${String((f.paramval|0))}" />
          </span>
        </code>` : ''}
      </li>`;
    }).join('');

    return `<div class="flag-group">
      <div class="flag-title">${esc(title)} <span class="count">(${cnt})</span></div>
      <ul>${lis}</ul>
    </div>`;
  };


  const cards = seq.map(([uuid, n]) => {
    const ctor = n.constructor || '';
    const isRoot = !!n.isRoot;
    const isEnd  = !!n.isEnd;
    const spk    = n.speakerIndex;

    var speaker = '—';
    if (spk === -666) speaker = 'Narrator';
    else if (spk === -1 || spk == null) speaker = '—';
    else if (spMap.has(spk)) speaker = `#${spk} (${esc(spMap.get(spk))})`;
    else speaker = `<span class="warn">#${spk} (not in speakerlist)</span>`;

    const texts = (Array.isArray(n.texts) ? n.texts : []).map((t, i) => {
    const parts = [];

    // Line ID
    parts.push(
      `<div class="dlg-val mono"> Line Id: <input type="text" class="dlg-val mono"
            data-line-edit="lineId"
            data-line-idx="${i}"
            value="${esc(t.lineId || '')}" />
       </div>`
    );

    // Resolved or fallback text (UI editable; LSX no-op unless you add loc writer)
    const resolved = typeof resolveDialogLineText === 'function' ? resolveDialogLineText(t, meta) : '';
    const shownText = resolved || t.text || '';

    // Handle
    parts.push(
      `<div class="dlg-val mono"> Handle: <input type="text" class="dlg-val mono"
            data-line-edit="handle"
            data-line-idx="${i}"
            value="${esc(t.handle || '')}" />
       </div>`
    );

    parts.push(
      `<div class="dlg-val mono"> Text: <input type="text" class="dlg-val mono"
            data-line-edit="text"
            data-line-idx="${i}"
            value="${esc(shownText)}" />
       </div>`
    );

    // Version (attribute on TranslatedString)
    parts.push(
      `<div class="dlg-val mono"> Version: <input type="text" class="dlg-val mono"
            data-line-edit="version"
            data-line-idx="${i}"
            value="${esc(String(t.version ?? t.Version ?? ''))}" />
       </div>`
    );

    // Tag rule toggle
    const hasTagRule = !!t.hasTagRule || String(t.HasTagRule || '').toLowerCase() === 'true';
    parts.push(
      `<div class="dlg-val mono">Has Tag Rule: <input type="checkbox"
               data-line-edit="hasTagRule"
               data-line-idx="${i}"
               ${hasTagRule ? 'checked' : ''} />
       </div>`
    );

    // Stub toggle
    const isStub = !!t.stub || String(t.stub || '').toLowerCase() === 'true';
    parts.push(
      `<div class="dlg-val mono"> Stub: <input type="checkbox"
               data-line-edit="stub"
               data-line-idx="${i}"
               ${isStub ? 'checked' : ''} />
       </div>`
    );

    return `<div class="line" data-line-idx="${i}">${parts.join('')}</div>`;
  }).join('');


  const childList = Array.isArray(n.children) ? n.children : [];

  const childrenHtml = childList.map(c => {
    const uid = String(c.uuid || '');
    if (c.type === 'local') {
      return `<li>
        <a href="#node-${esc(uid)}" class="link-local chip-uuid" title="${esc(uid)}">
          <span class="short">${esc(uid)}</span>
        </a>
      </li>`;
    }
    if (c.type === 'nested') {
      return `<li>
        <span class="chip-uuid link-nested" data-target-uuid="${esc(uid)}" title="Nested → ${esc(uid)}">
          <span class="short">Nested → ${esc(uid)}</span>
        </span>
      </li>`;
    }
    return `<li>
      <span class="chip-uuid link-orphan" title="Orphan → ${esc(uid)}">
        <span class="short">${esc(uid)}</span>
      </span>
    </li>`;
  }).join('');

    const checks = flagList(n.flags && n.flags.checks, 'Checkflags', uuid);
    const sets   = flagList(n.flags && n.flags.sets,   'Setflags',   uuid);

    const classes = ['dlg-node', 'editable'];
    const dlg_tags = JSON.parse('<?php echo $dlg_tags ?>');
    classes.push('collapsed');

    return `
      <article class="${classes.join(' ')}" id="node-${esc(uuid)}"
               data-node-uuid="${esc(uuid)}"
               aria-expanded="${isRoot ? 'true' : 'false'}">
        <header class="dlg-node-hd">
          <span class="head mono editable copyable" contenteditable="plaintext-only" data-copy="${esc(uuid)}">${esc(String(uuid))}</span>
        </header>
          <section class="dlg-node-body">
            <div class="dlg-val mono">
            Constructor: <select class="dlg-tag-select mono"
                      data-node-edit="constructor">
                ${(() => {
                  const listed = Array.isArray(dlg_tags) ? dlg_tags : [];
                  const have = listed.includes(String(ctor || ''));
                  const opts = listed.map(t =>
                    `<option value="${esc(t)}" data-node-edit="constructor"${t === ctor ? ' selected' : ''}>${esc(t)}</option>`
                  ).join('');
                  // if current ctor isn’t in dlg_tags, include it so it doesn’t get lost
                  const extra = have || !ctor ? '' :
                    `<option value="${esc(String(ctor))}" data-node-edit="constructor" selected>${esc(String(ctor))}</option>`;
                  return extra + opts;
                })()}
              </select>
            </div>
            <div class="dlg-val mono">
            Show Once: <input type="checkbox" data-node-edit="showOnce" ${n.showOnce ? 'checked' : ''}> 
            </div>
            <div class="dlg-val mono">
              <span class="k">Group ID:</span>
              <span class="editable mono" contenteditable="plaintext-only" data-node-edit="groupId">
                ${esc(n.groupId ?? '')}
              </span>
            </div>
            <div class="dlg-val mono">
              <span class="k">Group Index:</span>
              <span class="editable mono" contenteditable="plaintext-only" data-node-edit="groupIndex">
                ${n.groupIndex ?? ''}
              </span>
            </div>
            <div class="dlg-val mono">
            Root Node: <input type="checkbox" data-node-edit="root" ${isRoot ? 'checked': ''} />
            </div>
            <div class="dlg-val mono">
            End Node: <input type="checkbox" data-node-edit="endnode" ${isEnd  ? 'checked': ''} />
            </div>
            <div class="dlg-val mono">
              <span class="k-speaker">Speaker</span>
              <span class="v editable mono"
                      contenteditable="plaintext-only"
                      data-node-edit="speaker">${esc(spk == null ? '' : String(spk))}</span>
            </div>
            <div class="dlg-val mono">
              <span class="k">Approval</span>
              <span class="editable mono" contenteditable="plaintext-only" data-node-edit="approvalRating">
                ${n.approvalRating ?? ''}
              </span>
            </div>
            <div class="dlg-val mono">
            ${String(ctor).toLowerCase()==='nested' ? '<div class="badge nested">Nested</div>' : ''}
            </div>


            <div class="dlg-val mono">
              <span class="k-speaker">Children</span>
              <ul>${childrenHtml || '<li><span class="muted">none</span></li>'}</ul>
            </div>

            ${texts ? `<div class="texts">${texts}</div>` : ''}

            ${checks}${sets}
          </section>
        </article>
      `;
  }).join('');

  return `
    <div class="dlg-controls">
      <button class="btn btn-sm" id="dlg-expand-all" type="button">Expand all</button>
      <button class="btn btn-sm" id="dlg-collapse-all" type="button">Collapse all</button>
      <button class="btn btn-sm btn-primary" id="dlg-save-edits" type="button" title="Save">Save</button>
    </div>
    <div class="dlg-nodes">${cards}</div>`;
}

// --- Dialog editing state & helpers (root-level only for Step 1) ---

// Hold onto the currently loaded normalized LSX payload and route bits
window.__dlgEdit = {
  payload: null,   // normalized LSX tree (object)
  absPath: null,   // absolute or rel path used by your save route
  postUrl: null    // computed POST URL for saving
};

// Call this right after you fetch & render a file response
function setCurrentFileForDialogEditing(json, routeInfo) {
  window.__dlgEdit.payload = (json && (json.payload || json.result)) ? (json.payload || json.result) : null;

  if (routeInfo && routeInfo.relPath) {
    const slug = String(window.__modsSlug || '');
    let rel    = String(routeInfo.relPath).replace(/^\/+/, '');

    // —— Normalize rel FOR SAVE to ALWAYS live under Mods/<slug>/ —— //
    if (rel.startsWith(`Mods/${slug}/`)) {
      // already correct
    } else if (rel.startsWith(`UnpackedMods/${slug}/`)) {
      rel = rel.replace(`UnpackedMods/${slug}/`, `Mods/${slug}/`);
    } else if (rel.startsWith('GameData/')) {
      rel = `Mods/${slug}/` + rel.replace(/^GameData\/+/, '');
    } else {
      // e.g. starts with "Story/..." or anything else -> prefix Mods/<slug>/
      rel = `Mods/${slug}/` + rel;
    }
    // ———————————————————————————————————————————————————————— //

    window.__dlgEdit.postUrl = '/save';   // you route POSTs to /save
    window.__dlgEdit.route = {
      root: window.__modsRoot || '',
      slug: slug,
      rel:  rel                // <— now has Mods/<slug>/... prefix
    };
  } else {
    window.__dlgEdit.postUrl = null;
    window.__dlgEdit.route   = null;
  }
}

// Extract Category / TimelineID edits from the inline DOM
function collectDialogRootEdits() {
  const get = sel => document.querySelector(sel)?.textContent?.trim();
  return {
    category:   get('[data-dlg-edit="category"]'),
    timelineId: get('[data-dlg-edit="timelineId"]'),
    dialogUuid: get('[data-dlg-edit="dialogUuid"]')
  };
}

// helpers
function lsxKids(n){ return Array.isArray(n?.children) ? n.children : (n.children = []); }
function lsxFind(region, pred){ return lsxKids(region).find(pred) || null; }

// find (or create) an <attribute id="X"> as a direct child of dialogNode
function ensureTopLevelAttribute(dialogNode, id, typeIfNew, value){
  const kids = lsxKids(dialogNode);
  let attr = kids.find(c => c?.tag === 'attribute' && c?.attr?.id === id);
  if (!attr) {
    attr = { tag: 'attribute', attr: { id, type: typeIfNew, value: '' }, children: [] };
    kids.unshift(attr); // insert near the top like your existing structure
  }
  if (!attr.attr) attr.attr = {};
  attr.attr.value = String(value ?? '');
  return attr;
}

function applyDialogRootEditsToNormalized(payload, edits){
  if (!payload || typeof payload !== 'object') return payload;

  // <region id="dialog">
  const region = lsxFind(payload, c => c?.tag === 'region' && c?.attr?.id === 'dialog');
  if (!region) return payload;

  // <node id="dialog"> (child of region)
  const dialogNode = lsxFind(region, c => c?.tag === 'node' && c?.attr?.id === 'dialog');
  if (!dialogNode) return payload;

  const has = s => typeof s === 'string' && s.trim() !== '' && s !== '—';

  if (has(edits.category)) {
    ensureTopLevelAttribute(dialogNode, 'category', 'LSString', edits.category.trim());
  }
  if (has(edits.timelineId)) {
    // exact casing: TimelineId
    ensureTopLevelAttribute(dialogNode, 'TimelineId', 'FixedString', edits.timelineId.trim());
  }
  if (has(edits.dialogUuid)) {
    ensureTopLevelAttribute(dialogNode, 'UUID', 'FixedString', edits.dialogUuid.trim());
  }

  return payload;
}
// === INSERTED: Node/Line appliers ===
// utility: get children array (creates if missing)
function nxChildren(n){ return Array.isArray(n?.children) ? n.children : (n.children = []); }

function findDialogRegion(payload){
  return nxChildren(payload).find(c => c?.tag === 'region' && c?.attr?.id === 'dialog') || null;
}

function findNodesBag(region){
  return nxChildren(region).find(c => c?.tag === 'node' && c?.attr?.id === 'nodes') || null;
}

function findNodesChildren(region){
  const bag = findNodesBag(region);
  if (!bag) return [];
  const kids = nxChildren(bag).find(c => c?.tag === 'children');
  return kids ? nxChildren(kids) : [];
}

function ensureAttr(node, id, typeIfNew){
  const kids = nxChildren(node);
  let a = kids.find(c => c?.tag === 'attribute' && c?.attr?.id === id);
  if (!a) { a = { tag:'attribute', attr:{ id, type: typeIfNew, value:'' }, children:[] }; kids.unshift(a); }
  if (!a.attr) a.attr = {};
  return a;
}

function getNodeByUuid(payload, uuid){
  const region = findDialogRegion(payload);
  const nodes = findNodesChildren(region || {});
  for (const n of nodes){
    const a = nxChildren(n).find(c => c?.tag === 'attribute' && c?.attr?.id === 'UUID');
    if (a?.attr?.value === uuid) return n;
  }
  return null;
}

// --- Header fields (constructor/speaker/root/end) ---
function applyNodeHeaderEdit(payload, uuid, kind, rawValue){
  const n = getNodeByUuid(payload, uuid);
  if (!n) return;

  const v = (rawValue ?? '').toString().trim();

  if (kind === 'constructor') {
    ensureAttr(n, 'constructor', 'FixedString').attr.value = v;
    return;
  }
  if (kind === 'speaker') {
    const num = Number.parseInt(v || '0', 10);
    ensureAttr(n, 'speaker', 'int32').attr.value = String(Number.isFinite(num) ? num : 0);
    return;
  }
  if (kind === 'root') {
    const a = ensureAttr(n, 'Root', 'bool');
    const curr = String(a.attr.value || a.value || '').toLowerCase() === 'true';
    a.attr.value = (!curr).toString();
    return;
  }
  if (kind === 'endnode') {
    const a = ensureAttr(n, 'endnode', 'bool');
    const curr = String(a.attr.value || a.value || '').toLowerCase() === 'true';
    a.attr.value = (!curr).toString();
    return;
  }
}

// --- Children list (UUIDs) ---
function applyNodeChildrenEdit(payload, uuid, textBlob){
  const n = getNodeByUuid(payload, uuid);
  if (!n) return;

  // ensure structure: <children><node id="children"><children>...</children></node></children>
  let outerChildren = nxChildren(n).find(c=>c.tag==='children');
  if (!outerChildren) { outerChildren = { tag:'children', attr:[], children:[] }; nxChildren(n).push(outerChildren); }

  let nodeChildren = nxChildren(outerChildren).find(c=>c.tag==='node' && c.attr?.id==='children');
  if (!nodeChildren) { nodeChildren = { tag:'node', attr:{ id:'children' }, children:[] }; outerChildren.children.push(nodeChildren); }

  let innerChildren = nxChildren(nodeChildren).find(c=>c.tag==='children');
  if (!innerChildren) { innerChildren = { tag:'children', attr:[], children:[] }; nodeChildren.children.push(innerChildren); }

  const uuids = String(textBlob||'').split(/[\s,]+/).map(s=>s.trim()).filter(Boolean);

  innerChildren.children = uuids.map(u => ({
    tag:'node',
    attr:{ id:'child' },
    children:[ { tag:'attribute', attr:{ id:'UUID', type:'FixedString', value:u }, children:[] } ]
  }));
}

// --- Line edits (LineId/Handle/Version/HasTagRule/Stub/Text[no-op]) ---
function applyLineEdit(payload, uuid, lineIdx, which, value){
  const n = getNodeByUuid(payload, uuid);
  if (!n) return;

  // path: node -> TaggedTexts -> TaggedText -> TagTexts -> TagText
  const getKid = (parent, tag, id) => nxChildren(parent).find(c => c.tag===tag && (id ? c.attr?.id===id : true));

  const taggedTexts = getKid(n, 'node', 'TaggedTexts'); if (!taggedTexts) return;
  const ttChildren  = getKid(taggedTexts, 'children');   if (!ttChildren) return;
  const taggedText  = getKid(ttChildren, 'node', 'TaggedText'); if (!taggedText) return;
  const tgChildren  = getKid(taggedText, 'children');    if (!tgChildren) return;
  const tagTexts    = getKid(tgChildren, 'node', 'TagTexts');   if (!tagTexts) return;
  const txChildren  = getKid(tagTexts, 'children');      if (!txChildren) return;
  const tagText     = getKid(txChildren, 'node', 'TagText');    if (!tagText) return;

  if (which === 'handle') {
    const a = ensureAttr(tagText, 'TagText', 'TranslatedString');
    a.handle = String(value||'');
    return;
  }
  if (which === 'lineId') {
    ensureAttr(tagText, 'LineId', 'guid').attr.value = String(value||'');
    return;
  }
  if (which === 'version') {
    const a = ensureAttr(tagText, 'TagText', 'TranslatedString');
    a.version = String(value||'');
    return;
  }
  if (which === 'hasTagRule') {
    // <attribute id="HasTagRule" ...> lives under TaggedText
    const a = ensureAttr(taggedText, 'HasTagRule', 'bool');
    const curr = String(a.attr.value || a.value || '').toLowerCase() === 'true';
    a.attr.value = (!curr).toString();
    return;
  }
  if (which === 'stub') {
    // <attribute id="stub" ...> lives under TagText
    const a = ensureAttr(tagText, 'stub', 'bool');
    const curr = String(a.attr.value || a.value || '').toLowerCase() === 'true';
    a.attr.value = (!curr).toString();
    return;
  }
  if (which === 'text') {
    // localization-backed; LSX no-op
    return;
  }
}

// --- Flags (Checkflags / Setflags) ---
function applyFlagEdit(payload, uuid, which /* 'Checkflags'|'Setflags' */, idx, field, rawValue){
  const n = getNodeByUuid(payload, uuid);
  if (!n) return;

  const bagId = which === 'Checkflags' ? 'checkflags' : 'setflags';
  const bag   = nxChildren(n).find(c => c.tag==='node' && c.attr?.id===bagId);
  if (!bag) return;

  const bagKids = nxChildren(bag).find(c => c.tag==='children'); if (!bagKids) return;
  const groups  = nxChildren(bagKids).filter(c => c.tag==='node' && c.attr?.id==='flaggroup');
  const group   = groups[idx|0]; if (!group) return;

  const fgKids  = nxChildren(group).find(c => c.tag==='children'); if (!fgKids) return;
  const flag    = nxChildren(fgKids).find(c => c.tag==='node' && c.attr?.id==='flag'); if (!flag) return;

  const ensure = (id, type) => (ensureAttr(flag, id, type).attr);

  if (field === 'type') {
    ensure('type','FixedString').value = String(rawValue||'');
  } else if (field === 'UUID') {
    ensure('UUID','FixedString').value = String(rawValue||'');
  } else if (field === 'value') {
    const a = ensure('value','bool');
    const curr = String(a.value || '').toLowerCase() === 'true';
    a.value = (!curr).toString();
  } else if (field === 'paramval') {
    ensure('paramval','int32').value = String(parseInt(rawValue||'0',10) || 0);
  }
}

function saveDialogRootEdits() {
  const state = window.__dlgEdit;
  if (!state || !state.payload || !state.postUrl || !state.route) {
    alert('No dialog payload is loaded for editing.');
    return;
  }

  // 1) Root-level fields
  const edits = collectDialogRootEdits();
  applyDialogRootEditsToNormalized(state.payload, edits);

  // 2) Node header fields (constructor/speaker/root/end)
  document.querySelectorAll('[data-node-edit]').forEach(el => {
    const uuid = el.closest('[data-node-uuid]')?.dataset.nodeUuid;
    const kind = el.dataset.nodeEdit;
    if (!uuid || !kind) return;

    if (kind === 'children') return; // handled below

    const val = el.matches('.editable-toggle') ? null : (el.value ?? el.textContent);
    applyNodeHeaderEdit(state.payload, uuid, kind, val);
  });

  // 3) Children editor (textarea)
  document.querySelectorAll('[data-node-edit="children"]').forEach(el => {
    const uuid = el.dataset.nodeUuid || el.closest('[data-node-uuid]')?.dataset.nodeUuid;
    applyNodeChildrenEdit(state.payload, uuid, el.value);
  });

  // 4) Line edits (LineId / Handle / Version / HasTagRule / Stub / Text[no-op])
  document.querySelectorAll('[data-line-edit]').forEach(el => {
    const uuid = el.closest('[data-node-uuid]')?.dataset.nodeUuid;
    if (!uuid) return;
    const idx  = parseInt(el.dataset.lineIdx || '0', 10);
    const kind = el.dataset.lineEdit;
    const val  = el.matches('.chip.toggle') ? null : el.textContent;
    applyLineEdit(state.payload, uuid, idx, kind, val);
  });

  // 5) Flags
  document.querySelectorAll('[data-flag-edit]').forEach(el => {
    const uuid  = el.closest('[data-node-uuid]')?.dataset.nodeUuid;
    if (!uuid) return;
    const which = el.dataset.flagWhich;                 // 'Checkflags'|'Setflags'
    const idx   = parseInt(el.dataset.flagIdx || '0', 10);
    const field = el.dataset.flagEdit;                  // 'type'|'UUID'|'value'|'paramval'
    const val   = el.matches('.editable-toggle') ? null : el.textContent;
    applyFlagEdit(state.payload, uuid, which, idx, field, val);
  });

  // POST payload
  const form = new URLSearchParams();
  form.set('root', state.route.root);
  form.set('slug', state.route.slug);
  form.set('rel',  state.route.rel);
  form.set('relPath', state.route.rel);
  form.set('data_json', JSON.stringify({ payload: state.payload }));

  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const headers = { 'Content-Type': 'application/x-www-form-urlencoded' };
  if (csrf) headers['X-CSRF-TOKEN'] = csrf;

  fetch(state.postUrl, { method: 'POST', headers, body: form })
    .then(async (res) => {
      const txt = await res.text();
      console.log('Save response status:', res.status);
      console.log('Save response body:', txt);
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      alert('Saved dialog changes.');
    })
    .catch((e) => {
      console.error('Network error during save:', e);
      alert('Save failed (network). See console.');
    });
}

// Bind the Save button when dialog controls are mounted
function bindDialogSaveButton() {
  const btn = document.getElementById('dlg-save-edits');
  if (btn) {
    btn.addEventListener('click', saveDialogRootEdits, { once: false });
  }
}

function enhanceDialogMeta() {
  const root = document.getElementById('dialogMeta');
  if (!root) return;

  // Click-to-copy for any .copyable
  root.addEventListener('click', async (e) => {
    const el = e.target.closest('.copyable');
    if (!el) return;
    const txt = (el.dataset.copy ?? el.textContent ?? '').trim();
    if (!txt) return;
    try { await navigator.clipboard.writeText(txt); } catch {}
    el.style.opacity = '0.75';
    setTimeout(() => { el.style.opacity = ''; }, 220);
  });

  // Double-click to edit .editable (Category)
  root.addEventListener('dblclick', (e) => {
    const el = e.target.closest('.editable');
    if (!el) return;
    if (el.getAttribute('contenteditable') !== 'true') {
      el.setAttribute('contenteditable', 'true');
      const sel = window.getSelection(), range = document.createRange();
      range.selectNodeContents(el); sel.removeAllRanges(); sel.addRange(range);
      el.focus();
    }
  });

  // Commit on Enter/blur
  root.addEventListener('keydown', (e) => {
    const el = e.target.closest('.editable[contenteditable="true"]');
    if (!el) return;
    if (e.key === 'Enter') { e.preventDefault(); el.blur(); }
  });
  root.addEventListener('blur', (e) => {
    const el = e.target.closest('.editable[contenteditable="true"]');
    if (!el) return;
    el.removeAttribute('contenteditable');
    const newVal = (el.textContent || '').trim();
    // TODO: save hook here if desired:
    // window.dispatchEvent(new CustomEvent('dialogMetaChanged', { detail: { field: 'category', value: newVal } }));
  }, true);
}

</script>
<?= $this->endSection() ?>
