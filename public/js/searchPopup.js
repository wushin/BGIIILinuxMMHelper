(function () {
   window.toggleMongoPopup = () => {
    if (window.mongoPopupWin && !window.mongoPopupWin.closed) {
      window.mongoPopupWin.focus();
      return;
    }

    const win = window.open('', '_blank', 'width=1000,height=800,resizable=yes,scrollbars=yes');
    window.mongoPopupWin = win;

    const popupHtml = `
      <div id="mongo-search-popup" class="mongo-popup">
        <div class="mongo-popup-header">
          <div class="search-input-wrapper">
            <input id="mongo-search-input" placeholder="Search game data…" autocomplete="off" />
            <div id="search-history-dropdown" class="dropdown"></div>
          </div>
          <button id="mongo-search-button" class="header-btn">Search</button>
          <button id="toggle-filters" class="header-btn">Toggle Filters</button>
          <button id="mongo-popup-close" class="header-btn">&times;</button>
        </div>

        <div id="mongo-filters" class="mongo-filters">
          <h4>Filters</h4>
          <div>
            <strong>Directories</strong>
            <div id="dir-filters"></div>
            <button type="button" onclick="selectAll('dir')">Select All</button>
            <button type="button" onclick="deselectAll('dir')">Deselect All</button>
          </div>
          <div>
            <strong>Extensions</strong>
            <div id="ext-filters"></div>
            <button type="button" onclick="selectAll('ext')">Select All</button>
            <button type="button" onclick="deselectAll('ext')">Deselect All</button>
          </div>
        </div>

        <div class="mongo-results-wrapper">
          <div id="mongo-pagination" class="mongo-pagination"></div>
          <div id="mongo-search-results" class="mongo-results"></div>
          <div id="mongo-search-loading" class="mongo-loading" style="visibility: hidden;">
            <div class="spinner"></div>
          </div>
        </div>
      </div>
    `;

    win.document.write(`
      <!DOCTYPE html>
      <html>
        <head>
          <title>Game Data Search</title>
          <meta charset="UTF-8" />
          <link rel="stylesheet" href="/css/searchPopup.css">
        </head>
        <body style="margin:0;padding:0;overflow:hidden;">
          <div id="popup-root">${popupHtml}</div>
          <script>(${popupLogic.toString()})();<\/script>
        </body>
      </html>
    `);
    win.document.close();
  };

  function popupLogic() {
    const input = document.getElementById('mongo-search-input');
    const dropdown = document.getElementById('search-history-dropdown');
    const results = document.getElementById('mongo-search-results');
    const pager = document.getElementById('mongo-pagination');
    const loading = document.getElementById('mongo-search-loading');
    const dirFilters = document.getElementById('dir-filters');
    const extFilters = document.getElementById('ext-filters');
    const searchBtn = document.getElementById('mongo-search-button');
    const toggleBtn = document.getElementById('toggle-filters');

    let currentPage = 1;
    let currentTerm = '';
    let typingTimer;

    let selectedDirs = JSON.parse(localStorage.getItem('mongoDirs') || '[]');
    let selectedExts = JSON.parse(localStorage.getItem('mongoExts') || '[]');
    let searchHistory = JSON.parse(localStorage.getItem('mongoSearchHistory') || '[]');

    const saveSearchTerm = (term) => {
      if (!term) return;
      searchHistory = [term, ...searchHistory.filter(t => t !== term)];
      localStorage.setItem('mongoSearchHistory', JSON.stringify(searchHistory.slice(0, 20)));
    };

    const showSearchHistory = (filter = '') => {
      if (!searchHistory.length) return dropdown.style.display = 'none';
      const filtered = searchHistory.filter(t => t.toLowerCase().includes(filter.toLowerCase()));
      if (!filtered.length) return dropdown.style.display = 'none';

      dropdown.innerHTML = filtered.map(term => `<div class="history-item">${term}</div>`).join('');
      dropdown.innerHTML += `<div class="clear-history">Clear History</div>`;
      dropdown.style.display = 'block';
    };

    dropdown.addEventListener('click', e => {
      if (e.target.classList.contains('history-item')) {
        input.value = e.target.textContent;
        currentTerm = input.value.trim();
        fetchResults(currentTerm, 1);
        dropdown.style.display = 'none';
      } else if (e.target.classList.contains('clear-history')) {
        searchHistory = [];
        localStorage.removeItem('mongoSearchHistory');
        dropdown.style.display = 'none';
      }
    });

    input.addEventListener('input', () => {
      showSearchHistory(input.value);
      clearTimeout(typingTimer);
      typingTimer = setTimeout(() => {
        currentTerm = input.value.trim();
        currentPage = 1;
        fetchResults(currentTerm, currentPage);
      }, 300);
    });

    input.addEventListener('focus', () => showSearchHistory(input.value));
    input.addEventListener('blur', () => setTimeout(() => dropdown.style.display = 'none', 150));

    searchBtn.addEventListener('click', () => {
      currentTerm = input.value.trim();
      currentPage = 1;
      saveSearchTerm(currentTerm);
      fetchResults(currentTerm, currentPage);
      document.getElementById('mongo-filters').style.display = 'none';
    });

    toggleBtn.addEventListener('click', () => {
      const box = document.getElementById('mongo-filters');
      box.style.display = box.style.display === 'none' ? 'block' : 'none';
    });

    window.selectAll = (type) => {
      document.querySelectorAll(`#${type}-filters input[type=checkbox]`).forEach(cb => cb.checked = true);
    };

    window.deselectAll = (type) => {
      document.querySelectorAll(`#${type}-filters input[type=checkbox]`).forEach(cb => cb.checked = false);
    };

    fetch('/search/mongo-filters')
      .then(res => res.json())
      .then(data => {
        renderFilterOptions(data.dirs || [], 'dir-filters', selectedDirs);
        renderFilterOptions(data.exts || [], 'ext-filters', selectedExts);
      });

    function renderFilterOptions(list, targetId, selected) {
      const container = document.getElementById(targetId);
      container.innerHTML = list.map(opt => {
        const checked = selected.includes(opt) ? 'checked' : '';
        return `<label><input type="checkbox" value="${opt}" ${checked}> ${opt}</label><br>`;
      }).join('');
    }

    const fetchResults = (term, page = 1) => {
      loading.style.visibility = 'visible';
      results.innerHTML = '';

      fetch(`/search/mongo?q=${encodeURIComponent(term)}&page=${page}`)
        .then(r => r.json())
        .then(json => renderResults(json))
        .catch(console.error)
        .finally(() => loading.style.visibility = 'hidden');
    };

    function renderPagination(totalPages, currentPage) {
      const buttons = [];

      const push = (page, label = null, active = false) => {
        buttons.push(`<button data-page="${page}" class="page-btn${active ? ' active' : ''}">${label ?? page}</button>`);
      };

      if (currentPage > 1) {
        push(1, '«');
        push(currentPage - 1, '<');
      }

      const start = Math.max(1, currentPage - 2);
      const end = Math.min(totalPages, currentPage + 2);

      if (start > 1) {
        push(1);
        if (start > 2) buttons.push('<span class="dots">…</span>');
      }

      for (let i = start; i <= end; i++) {
        push(i, null, i === currentPage);
      }

      if (end < totalPages) {
        if (end < totalPages - 1) buttons.push('<span class="dots">…</span>');
        push(totalPages);
      }

      if (currentPage < totalPages) {
        push(currentPage + 1, '>');
        push(totalPages, '»');
      }

      document.getElementById('mongo-pagination').innerHTML = buttons.join('');
    }

    const renderResults = json => {
      const escapeHtml = str => str.replace(/[&<>\"]/g, c => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;'
      })[c]);

      const highlight = (text, term) => {
        if (!term) return escapeHtml(text);
        const escaped = term.replace(/[.*+?^${}()|[\\]\\]/g, '\\$&');
        const re = new RegExp(escaped, 'gi');
        return escapeHtml(text).replace(re, '<mark>$&</mark>')
          .replace(/&lt;mark&gt;/g, '<mark>')
          .replace(/&lt;\/mark&gt;/g, '</mark>');
      };

      const linesWithHighlight = (text, term) => {
        const lines = text.split(/\r?\n/);
        const match = lines.findIndex(l => l.toLowerCase().includes(term.toLowerCase()));
        const start = Math.max(0, match - 2);
        const end = Math.min(lines.length, match + 3);
        return lines.slice(start, end).join('\n');
      };

      const pages = Math.ceil(json.total / json.perPage) || 1;
      renderPagination(pages, json.page);
      results.innerHTML = json.results.map(doc => {
        const snippet = highlight(linesWithHighlight(doc.raw, currentTerm), currentTerm);
        const full = highlight(doc.raw, currentTerm);
        return `
          <div class="mongo-result" data-file="${doc.filepath}">
            <div class="mongo-path">
              ${doc.filepath}
              <button class="expand-btn">Expand</button>
            </div>
            <pre class="mongo-snippet">${snippet}</pre>
            <pre class="full-content" style="display:none;">${full}</pre>
          </div>
        `;
      }).join('');
    };

    pager.addEventListener('click', e => {
      if (e.target.classList.contains('page-btn')) {
        currentPage = +e.target.dataset.page;
        fetchResults(currentTerm, currentPage);
      }
    });

    results.addEventListener('click', e => {
      if (e.target.classList.contains('expand-btn')) {
        const container = e.target.closest('.mongo-result');
        const full = container.querySelector('.full-content');
        const isHidden = full.style.display === 'none';
        full.style.display = isHidden ? 'block' : 'none';
        e.target.textContent = isHidden ? 'Collapse' : 'Expand';
      }
    });

    document.getElementById('mongo-popup-close').addEventListener('click', () => {
      window.close();
    });
  }
})();

