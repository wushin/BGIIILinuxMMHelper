
/* /public/js/mongoPopup.js — keep existing popup behavior & CSS
   CHANGE: consume dirsByRoot/dirByRoot from /search/mongo-filters
           (no more roots/dirs at the API level). We flatten all root arrays
           into a single "Directories" list to preserve the current UI.
*/
(function () {
  // --- PUBLIC: called by your "Game Data" button
  window.toggleMongoPopup = () => {
    if (window.mongoPopupWin && !window.mongoPopupWin.closed) {
      window.mongoPopupWin.focus();
      return;
    }

    const win = window.open('', '_blank', 'width=1200,height=850,resizable=yes,scrollbars=yes');
    window.mongoPopupWin = win;

    // 1) Collect theme attrs + CSS from the OPENER (same-origin)
    const themeAttrs = {};
    ['data-theme', 'data-color-mode', 'data-ui', 'data-mode'].forEach((attr) => {
      const vHtml = document.documentElement.getAttribute(attr);
      const vBody = document.body.getAttribute(attr);
      if (vHtml) themeAttrs[`html:${attr}`] = vHtml;
      if (vBody) themeAttrs[`body:${attr}`] = vBody;
    });

    const openerStylesheets = Array.from(document.querySelectorAll('link[rel="stylesheet"]'))
      .map(l => l.href).filter(Boolean);
    const openerInlineStyles = Array.from(document.querySelectorAll('style'))
      .map(s => s.textContent || '');

    // 2) Popup markup: header + main (sidebar + content)
    const popupHtml = `
      <div id="mongo-search-popup" class="mongo-popup">
        <div class="mongo-popup-header">
          <div class="search-input-wrapper">
            <input id="mongo-search-input" placeholder="Search game data…" autocomplete="off" />
            <div id="search-history-dropdown" class="dropdown"></div>
          </div>
          <button id="mongo-search-button" class="header-btn">Search</button>
          <button id="toggle-filters" class="header-btn" title="Show/Hide Filters">Sidebar</button>
          <button id="mongo-popup-close" class="header-btn" aria-label="Close popup">&times;</button>
        </div>

        <div class="mongo-main">
          <aside id="mongo-sidebar" class="mongo-sidebar" style="display:block;">
            <div class="filters-head">
              <h4>Filters</h4>
              <div class="filters-summary">
                <span id="root-count" class="count-badge">0 Roots</span>
                <span id="dir-count" class="count-badge">0 Dirs</span>
                <span id="ext-count" class="count-badge">0 Exts</span>
                <span id="region-count" class="count-badge">0 Regions</span>
                <span id="group-count" class="count-badge">0 Groups</span>
              </div>
            </div>

            <div class="accordion">

              <section class="filter-section" data-kind="dir">
                <button type="button" class="accordion-head" aria-expanded="true">
                  <strong>Directories</strong><span class="chev">▾</span>
                </button>
                <div class="accordion-body" style="display:block;">
                  <div class="filter-head-actions">
                    <input id="dir-search" class="filter-search" placeholder="Filter directories…" />
                    <button type="button" data-action="selectAll" data-scope="dir">Select All</button>
                    <button type="button" data-action="deselectAll" data-scope="dir">Deselect All</button>
                  </div>
                  <div id="dir-filters" class="filter-list"></div>
                </div>
              </section>

              <section class="filter-section" data-kind="ext">
                <button type="button" class="accordion-head" aria-expanded="true">
                  <strong>Extensions</strong><span class="chev">▾</span>
                </button>
                <div class="accordion-body" style="display:block;">
                  <div class="filter-head-actions">
                    <input id="ext-search" class="filter-search" placeholder="Filter extensions…" />
                    <button type="button" data-action="selectAll" data-scope="ext">Select All</button>
                    <button type="button" data-action="deselectAll" data-scope="ext">Deselect All</button>
                  </div>
                  <div id="ext-filters" class="filter-list"></div>
                </div>
              </section>

              <section class="filter-section" data-kind="region">
                <button type="button" class="accordion-head" aria-expanded="true">
                  <strong>Regions</strong><span class="chev">▾</span>
                </button>
                <div class="accordion-body" style="display:block;">
                  <div class="filter-head-actions">
                    <input id="region-search" class="filter-search" placeholder="Filter regions…" />
                    <button type="button" data-action="selectAll" data-scope="region">Select All</button>
                    <button type="button" data-action="deselectAll" data-scope="region">Deselect All</button>
                  </div>
                  <div id="region-filters" class="filter-list"></div>
                </div>
              </section>

              <section class="filter-section" data-kind="group">
                <button type="button" class="accordion-head" aria-expanded="true">
                  <strong>Groups</strong><span class="chev">▾</span>
                </button>
                <div class="accordion-body" style="display:block;">
                  <div class="filter-head-actions">
                    <button type="button" data-action="selectAll" data-scope="group">Select All</button>
                    <button type="button" data-action="deselectAll" data-scope="group">Deselect All</button>
                  </div>
                  <div id="group-filters" class="filter-list"></div>
                </div>
              </section>

            </div>
          </aside>

          <section class="mongo-content">
            <div id="mongo-pagination" class="mongo-pagination"></div>
            <div id="mongo-search-results" class="mongo-results"></div>
            <div id="mongo-search-loading" class="mongo-loading" style="visibility: hidden;">
              <div class="spinner"></div>
            </div>
          </section>
        </div>
      </div>
    `;

    // 3) Write popup document, then inject opener theme/CSS safely
    win.document.write(`
      <!DOCTYPE html>
      <html>
        <head>
          <title>Game Data Search</title>
          <meta charset="UTF-8" />
          <meta name="viewport" content="width=device-width, initial-scale=1" />
          <link rel="stylesheet" href="/css/searchPopup.css">
        </head>
        <body style="margin:0;padding:0;overflow:hidden;">
          <div id="popup-root">${popupHtml}</div>
          <script>
            (function(){
              try {
                // Apply theme attributes from opener
                var theme = ${JSON.stringify(themeAttrs)};
                Object.keys(theme).forEach(function(k){
                  var parts = k.split(':');
                  var node = parts[0] === 'html' ? document.documentElement : document.body;
                  if (node) node.setAttribute(parts[1], theme[k]);
                });

                // Inject opener <link rel="stylesheet"> first
                var hrefs = ${JSON.stringify(openerStylesheets)};
                hrefs.forEach(function(href){
                  try {
                    var l = document.createElement('link');
                    l.rel = 'stylesheet';
                    l.href = href;
                    document.head.insertBefore(l, document.head.firstChild);
                  } catch(e){}
                });

                // Inject opener inline <style>
                var inline = ${JSON.stringify(openerInlineStyles)};
                inline.forEach(function(css){
                  try {
                    if (!css) return;
                    var s = document.createElement('style');
                    s.textContent = css;
                    document.head.appendChild(s);
                  } catch(e){}
                });
              } catch(e) {
                console.error('Theme/CSS injection failed:', e);
              }
            })();
          <\/script>
          <script>(${popupLogic.toString()})();<\/script>
        </body>
      </html>
    `);
    win.document.close();
  };

  // --- PRIVATE: logic that runs inside the popup window
  function popupLogic() {
    const input     = document.getElementById('mongo-search-input');
    const dropdown  = document.getElementById('search-history-dropdown');
    const results   = document.getElementById('mongo-search-results');
    const pager     = document.getElementById('mongo-pagination');
    const loading   = document.getElementById('mongo-search-loading');
    const searchBtn = document.getElementById('mongo-search-button');
    const toggleBtn = document.getElementById('toggle-filters');
    const sidebar   = document.getElementById('mongo-sidebar');

    const rootCountEl  = document.getElementById('root-count');
    const dirCountEl    = document.getElementById('dir-count');
    const extCountEl    = document.getElementById('ext-count');
    const regionCountEl = document.getElementById('region-count');
    const groupCountEl  = document.getElementById('group-count');

    let currentPage   = 1;
    let currentTerm   = '';
    let searchHistory = JSON.parse(localStorage.getItem('mongoSearchHistory') || '[]');

    // restore persisted filters + last query + accordion states
    let selectedDirs    = JSON.parse(localStorage.getItem('mongoDirs') || '[]');
    let selectedExts    = JSON.parse(localStorage.getItem('mongoExts') || '[]');
    let selectedRegions = JSON.parse(localStorage.getItem('mongoRegions') || '[]');
    let selectedGroups  = JSON.parse(localStorage.getItem('mongoGroups') || '[]');
    let accordionsState = JSON.parse(localStorage.getItem('mongoAccordions') || '{}');
    const lastQuery = localStorage.getItem('mongoLastQuery') || '';
    if (lastQuery) input.value = lastQuery; // do not auto-search

    function getSelectedFilters(){
      // root = first-level under dirsByRoot, rendered as checkboxes with data-root-toggle="1"
      const roots = Array.from(
        document.querySelectorAll('#dir-filters input[data-root-toggle="1"]:checked')
      ).map(cb => cb.value);

      const dirs    = Array.from(document.querySelectorAll('#dir-filters .root-children input[type=checkbox]:checked')).map(cb => cb.value);
      const exts    = Array.from(document.querySelectorAll('#ext-filters input:checked')).map(cb => cb.value);
      const regions = Array.from(document.querySelectorAll('#region-filters input:checked')).map(cb => cb.value);
      const groups  = Array.from(document.querySelectorAll('#group-filters input:checked')).map(cb => cb.value);

      return { roots, dirs, exts, regions, groups };
    }

    function updateCounts() {
      const roots = Array.from(
        document.querySelectorAll('#dir-filters input[data-root-toggle="1"]:checked')
      );
      const { dirs, exts, regions, groups } = getSelectedFilters();
      if (rootCountEl)  rootCountEl.textContent  = `${roots.length} Roots`;
      dirCountEl.textContent    = `${dirs.length} Dirs`;
      extCountEl.textContent    = `${exts.length} Exts`;
      regionCountEl.textContent = `${regions.length} Regions`;
      groupCountEl.textContent  = `${groups.length} Groups`;
    }

    // --- lock sidebar width to the widest label (even when sections collapsed)
    function lockSidebarWidth() {
      const sections = Array.from(document.querySelectorAll('.filter-section'));
      const snapshots = sections.map(sec => {
        const head = sec.querySelector('.accordion-head');
        const body = sec.querySelector('.accordion-body');
        return {
          body,
          wasDisplay: body.style.display,
          wasExpanded: head.getAttribute('aria-expanded') === 'true',
          chev: head.querySelector('.chev')
        };
      });

      // Temporarily show all bodies to measure real widths
      snapshots.forEach(s => { s.body.style.display = 'block'; });

      // Measure widest label text
      let maxLabel = 0;
      document.querySelectorAll('.filter-chip .chip-text').forEach(el => {
        const w = el.getBoundingClientRect().width;
        if (w > maxLabel) maxLabel = w;
      });

      const total = Math.ceil(maxLabel + 22 + 8 + 24 + 4 + 12);

      sidebar.style.width = total + 'px';
      sidebar.style.minWidth = total + 'px';

      // Restore bodies to original state
      snapshots.forEach(s => {
        if (s.wasExpanded) {
          s.body.style.display = 'block';
          s.chev.textContent = '▾';
        } else {
          s.body.style.display = (s.wasDisplay || 'none');
          s.chev.textContent = '▸';
        }
      });
    }

    // --- Search history
    const saveSearchTerm = (term) => {
      if (!term) return;
      searchHistory = [term, ...searchHistory.filter(t => t !== term)];
      localStorage.setItem('mongoSearchHistory', JSON.stringify(searchHistory.slice(0, 20)));
      localStorage.setItem('mongoLastQuery', term);
    };

    const showSearchHistory = (filter = '') => {
      const items = (searchHistory || []).filter(t => t.toLowerCase().includes((filter || '').toLowerCase()));
      if (!items.length) { dropdown.style.display = 'none'; return; }
      dropdown.innerHTML = items.map(term => `<div class="history-item" title="${term}">${term}</div>`).join('') +
                           `<div class="clear-history">Clear History</div>`;
      dropdown.style.display = 'block';
    };

    dropdown.addEventListener('click', e => {
      if (e.target.classList.contains('history-item')) {
        input.value = e.target.textContent;
        currentTerm = input.value.trim();
        currentPage = 1;
        saveSearchTerm(currentTerm);
        fetchResults(currentTerm, currentPage);
        dropdown.style.display = 'none';
      } else if (e.target.classList.contains('clear-history')) {
        searchHistory = [];
        localStorage.removeItem('mongoSearchHistory');
        dropdown.style.display = 'none';
      }
    });

    input.addEventListener('focus', () => showSearchHistory(input.value));
    input.addEventListener('blur', () => setTimeout(() => dropdown.style.display = 'none', 150));
    input.addEventListener('input', () => showSearchHistory(input.value));

    // Run searches on Enter or Search button
    input.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        currentTerm = input.value.trim();
        currentPage = 1;
        saveSearchTerm(currentTerm);
        fetchResults(currentTerm, currentPage);
      }
    });

    // Buttons
    document.getElementById('mongo-search-button').addEventListener('click', () => {
      currentTerm = input.value.trim();
      currentPage = 1;
      saveSearchTerm(currentTerm);
      fetchResults(currentTerm, currentPage);
    });

    // Sidebar toggle
    document.getElementById('toggle-filters').addEventListener('click', () => {
      sidebar.style.display = (sidebar.style.display === 'none') ? 'block' : 'none';
    });

    // Accordion behavior
    function saveAccordionState() {
      const state = {};
      document.querySelectorAll('.filter-section').forEach(sec => {
        const kind = sec.getAttribute('data-kind');
        const head = sec.querySelector('.accordion-head');
        state[kind] = head.getAttribute('aria-expanded') === 'true';
      });
      localStorage.setItem('mongoAccordions', JSON.stringify(state));
    }

    document.addEventListener('click', (ev) => {
      const head = ev.target.closest('.accordion-head');
      if (!head) return;
      const body = head.nextElementSibling;
      const expanded = head.getAttribute('aria-expanded') === 'true';
      head.setAttribute('aria-expanded', expanded ? 'false' : 'true');
      head.querySelector('.chev').textContent = expanded ? '▸' : '▾';
      body.style.display = expanded ? 'none' : 'block';
      saveAccordionState();
    });

    // Restore accordion states if present
    (function restoreAccordion() {
      let acc = {};
      try { acc = JSON.parse(localStorage.getItem('mongoAccordions') || '{}'); } catch {}
      document.querySelectorAll('.filter-section').forEach(sec => {
        const kind = sec.getAttribute('data-kind');
        if (acc && Object.prototype.hasOwnProperty.call(acc, kind)) {
          const show = !!acc[kind];
          const head = sec.querySelector('.accordion-head');
          const body = sec.querySelector('.accordion-body');
          head.setAttribute('aria-expanded', show ? 'true' : 'false');
          head.querySelector('.chev').textContent = show ? '▾' : '▸';
          body.style.display = show ? 'block' : 'none';
        }
      });
    })();

    // Section action buttons (Select/Deselect All)
    document.addEventListener('click', (ev) => {
      const btn = ev.target.closest('.filter-head-actions [data-action]');
      if (!btn) return;
      const scope  = btn.getAttribute('data-scope');
      const action = btn.getAttribute('data-action');
      const map = { dir: 'dir-filters', ext: 'ext-filters', region: 'region-filters', group: 'group-filters' };
      const container = document.getElementById(map[scope]);
      if (!container) return;
      container.querySelectorAll('input[type=checkbox]').forEach(cb => cb.checked = (action === 'selectAll'));
      persistFilters();
      updateCounts();
    });

    // Filter search (per section)
    function wireFilterSearch(inputId, listId) {
      const field = document.getElementById(inputId);
      const list  = document.getElementById(listId);
      if (!field || !list) return;
      field.addEventListener('input', () => {
        const q = (field.value || '').toLowerCase();
        list.querySelectorAll('.filter-chip').forEach(chip => {
          const label = chip.getAttribute('data-label') || '';
          chip.style.display = label.includes(q) ? '' : 'none';
        });
      });
    }

    // === FETCH FILTERS (render roots first; expand children only when checked) ===
    fetch('/search/mongo-filters')
      .then(res => res.json())
      .then(data => {
        const dirsByRoot = (data && (data.dirsByRoot || data.dirByRoot)) || {};

        // Restore any previously selected dirs (we already read selectedDirs above)
        // Optional: restore selected roots based on whether any child was selected.
        const selectedRoots = {};
        Object.keys(dirsByRoot).forEach(root => {
          selectedRoots[root] = (selectedDirs || []).some(d => (dirsByRoot[root] || []).includes(d));
        });

        renderDirByRoot(dirsByRoot, 'dir-filters', selectedDirs, selectedRoots);

        const exts    = Array.isArray(data.exts)    ? data.exts.map(x => String(x||'').toLowerCase()) : [];
        const regions = Array.isArray(data.regions) ? data.regions : [];
        const groups  = Array.isArray(data.groups)  ? data.groups.map(x => String(x||'').toLowerCase()) : [];

        renderFilterOptions(exts,    'ext-filters',    selectedExts);
        renderFilterOptions(regions, 'region-filters', selectedRegions);
        renderFilterOptions(groups,  'group-filters',  selectedGroups);

        // Persist on change + counts per section
        ['ext-filters','region-filters','group-filters'].forEach(id => {
          const el = document.getElementById(id);
          el.addEventListener('change', () => { persistFilters(); updateCounts(); });
        });

        // Directories need special wiring (roots/children)
        document.getElementById('dir-filters').addEventListener('change', (e) => {
          const rootToggle = e.target.closest('input[data-root-toggle="1"]');
          if (rootToggle) {
            const root = rootToggle.value;
            const kids = document.querySelector(`#root-${cssId(root)} .root-children`);
            const open = !!rootToggle.checked;
            kids.style.display = open ? 'block' : 'none';
            if (!open) {
              // uncheck all children when collapsing a root
              kids.querySelectorAll('input[type="checkbox"]').forEach(cb => { cb.checked = false; });
            }
            persistFilters();
            updateCounts();
            return;
          }
          // children checkbox changed
          persistFilters();
          updateCounts();
        });

        // Wire searches (dir search should only affect visible children)
        wireFilterSearch('dir-search', 'dir-filters');
        wireFilterSearch('ext-search', 'ext-filters');
        wireFilterSearch('region-search', 'region-filters');

        // Make Select All / Deselect All only touch VISIBLE directory children
        document.addEventListener('click', (ev) => {
          const btn = ev.target.closest('.filter-head-actions [data-action]');
          if (!btn) return;
          const scope  = btn.getAttribute('data-scope');
          const action = btn.getAttribute('data-action');
          if (scope === 'dir') {
            const container = document.getElementById('dir-filters');
            container.querySelectorAll('.root-children input[type=checkbox]').forEach(cb => {
              if (cb.offsetParent !== null) cb.checked = (action === 'selectAll');
            });
            persistFilters();
            updateCounts();
          }
        });

        updateCounts();

        // Lock the sidebar width AFTER rendering (keeps your CSS intact)
        lockSidebarWidth();
        window.addEventListener('load', () => setTimeout(lockSidebarWidth, 0));
      })
      .catch(console.error);

    function renderFilterOptions(list, targetId, selected) {
      const container = document.getElementById(targetId);
      container.innerHTML = (list || []).map(opt => {
        const val = `${opt}`;
        const checked = selected.includes(val) ? 'checked' : '';
        const labelText = val;
        return `
          <label class="filter-chip" data-label="${(labelText || '').toLowerCase()}">
            <input type="checkbox" value="${val}" ${checked}>
            <span class="chip-text" title="${labelText}">${labelText}</span>
          </label>
        `;
      }).join('');
    }

    function cssId(s) { return String(s || '').toLowerCase().replace(/[^a-z0-9_-]+/g, '-'); }

    function renderDirByRoot(dirsByRoot, targetId, selectedDirs, selectedRoots) {
      const container = document.getElementById(targetId);
      const roots = Object.keys(dirsByRoot || {}).sort((a,b)=>a.localeCompare(b,undefined,{numeric:true}));
      if (!roots.length) { container.innerHTML = '<div class="muted">No directories.</div>'; return; }

      container.innerHTML = roots.map(root => {
        const id = `root-${cssId(root)}`;
        const open = !!selectedRoots[root];
        const children = (dirsByRoot[root] || []).slice().sort((a,b)=>a.localeCompare(b,undefined,{numeric:true}));

        const childHtml = children.map(dir => {
          const checked = (selectedDirs || []).includes(dir) ? 'checked' : '';
          return `
            <label class="filter-chip" data-label="${(dir || '').toLowerCase()}">
              <input type="checkbox" value="${dir}" ${checked}>
              <span class="chip-text" title="${dir}">${dir}</span>
            </label>
          `;
        }).join('');

        return `
          <div id="${id}" class="dir-root">
            <label class="filter-chip root-row" data-label="${root.toLowerCase()}">
              <input type="checkbox" value="${root}" data-root-toggle="1" ${open ? 'checked' : ''}>
              <span class="chip-text" title="${root}">${root}</span>
            </label>
            <div class="root-children" style="display:${open ? 'block' : 'none'};">
              ${childHtml}
            </div>
          </div>
        `;
      }).join('');
    }

    const getSelectedRoots = () =>
        Array.from(document.querySelectorAll('#dir-filters input[data-root-toggle="1"]:checked'))
            .map(cb => cb.value);

    function persistFilters() {
      const { dirs, exts, regions, groups } = getSelectedFilters();
      localStorage.setItem('mongoDirs', JSON.stringify(dirs));
      localStorage.setItem('mongoExts', JSON.stringify(exts));
      localStorage.setItem('mongoRegions', JSON.stringify(regions));
      localStorage.setItem('mongoGroups', JSON.stringify(groups));
    }

    // Core: fetch & render results (server AND-combines all filters)
    const fetchResults = (term, page = 1) => {
      loading.style.visibility = 'visible';
      results.innerHTML = '';
      pager.innerHTML = '';

      const { dirs, exts, regions, groups } = getSelectedFilters();
      const roots = getSelectedRoots();                     // <-- ADDED

      const params = new URLSearchParams({
        q: term || '',
        page: page || 1
      });

      roots.forEach(r => params.append('roots[]', r));      // <-- ADDED
      dirs.forEach(d => params.append('dirs[]', d));
      exts.forEach(e => params.append('exts[]', e));
      regions.forEach(r => params.append('regions[]', r));
      groups.forEach(g => params.append('groups[]', g));

      fetch(`/search/mongo?${params.toString()}`)
        .then(r => r.json())
        .then(json => renderResults(json))
        .catch(err => {
          console.error(err);
          results.innerHTML = `<div class="error">An error occurred. Check logs.</div>`;
        })
        .finally(() => loading.style.visibility = 'hidden');
    };

    function renderPagination(totalPages, currentPage) {
      const buttons = [];
      const push = (page, label = null, active = false) => {
        buttons.push(`<button data-page="${page}" class="page-btn${active ? ' active' : ''}">${label ?? page}</button>`);
      };

      if (currentPage > 1) { push(1, '«'); push(currentPage - 1, '<'); }
      const start = Math.max(1, currentPage - 2);
      const end = Math.min(totalPages, currentPage + 2);

      if (start > 1) {
        push(1);
        if (start > 2) buttons.push('<span class="dots">…</span>');
      }
      for (let i = start; i <= end; i++) push(i, null, i === currentPage);
      if (end < totalPages) {
        if (end < totalPages - 1) buttons.push('<span class="dots">…</span>');
        push(totalPages);
      }
      if (currentPage < totalPages) { push(currentPage + 1, '>'); push(totalPages, '»'); }

      document.getElementById('mongo-pagination').innerHTML = buttons.join('');
    }

    const renderResults = json => {
      const escapeHtml = str => (str || '').replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
      const highlight = (text, term) => {
        if (!text) return '';
        if (!term) return escapeHtml(text);
        const escaped = term.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        const re = new RegExp(escaped, 'gi');
        return escapeHtml(text)
          .replace(re, '<mark>$&</mark>')
          .replace(/&lt;mark&gt;/g, '<mark>')
          .replace(/&lt;\/mark&gt;/g, '</mark>');
      };
      const linesWithHighlight = (text, term) => {
        if (!text) return '';
        const lines = text.split(/\r?\n/);
        const idx = term ? lines.findIndex(l => l.toLowerCase().includes(term.toLowerCase())) : -1;
        if (idx === -1) return lines.slice(0, 5).join('\n');
        const start = Math.max(0, idx - 2);
        const end = Math.min(lines.length, idx + 3);
        return lines.slice(start, end).join('\n');
      };

      if (!json || !Array.isArray(json.results) || json.results.length === 0) {
        document.getElementById('mongo-pagination').innerHTML = '';
        results.innerHTML = `<div class="no-results">No results found.</div>`;
        return;
      }

      const pages = Math.ceil((json.total || json.results.length) / (json.perPage || json.pageSize || json.results.length)) || 1;
      renderPagination(pages, json.page || 1);

      const q = (document.getElementById('mongo-search-input')?.value || '').trim();

      results.innerHTML = json.results.map(doc => {
        const raw = doc.raw || doc.full || doc.snippet || '';
        const snippet = highlight(linesWithHighlight(raw, q), q);
        const full = highlight(raw || '', q);
        const filepath = escapeHtml(doc.filepath || doc.path || '(unknown file)');
        const hasFull = !!raw;
        return `
          <div class="mongo-result" data-file="${filepath}">
            <div class="mongo-path">
              <span class="path-text">${filepath}</span>
              ${hasFull ? '<button class="expand-btn">Expand</button>' : ''}
            </div>
            <pre class="mongo-snippet">${snippet}</pre>
            ${hasFull ? `<pre class="full-content" style="display:none;">${full}</pre>` : ''}
          </div>
        `;
      }).join('');
    };

    // Pagination clicks
    pager.addEventListener('click', e => {
      if (e.target.classList.contains('page-btn')) {
        currentPage = +e.target.dataset.page;
        fetchResults(currentTerm, currentPage);
      }
    });

    // Expand/Collapse per result
    results.addEventListener('click', e => {
      if (e.target.classList.contains('expand-btn')) {
        const container = e.target.closest('.mongo-result');
        const full = container.querySelector('.full-content');
        const isHidden = full.style.display === 'none';
        full.style.display = isHidden ? 'block' : 'none';
        e.target.textContent = isHidden ? 'Collapse' : 'Expand';
      }
    });

    // Close button
    document.getElementById('mongo-popup-close').addEventListener('click', () => window.close());
  }

  // --- Convenience: wire the header button if present in the opener
  document.addEventListener('DOMContentLoaded', () => {
    const btn = document.getElementById('btn-game-data');
    if (btn) btn.addEventListener('click', () => window.toggleMongoPopup());
  });
})();
