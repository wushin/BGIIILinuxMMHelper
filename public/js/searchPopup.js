(function () {
  const popupHtml = `
  <div id="mongo-search-popup" class="mongo-popup" style="display:none;">
    <div class="mongo-popup-header">
      <input id="mongo-search-input" placeholder="Search game data…" />
      <button id="mongo-popup-close">×</button>
    </div>
    <div id="mongo-pagination" class="mongo-pagination"></div>
    <div id="mongo-search-results" class="mongo-results"></div>
    <div id="mongo-search-loading" class="mongo-loading" style="display:none;">
      <div class="spinner"></div>
    </div>
  </div>`;

  document.body.insertAdjacentHTML('beforeend', popupHtml);

  const popup   = document.getElementById('mongo-search-popup');
  const input   = document.getElementById('mongo-search-input');
  const results = document.getElementById('mongo-search-results');
  const pager   = document.getElementById('mongo-pagination');

  let currentPage = 1;
  let currentTerm = '';

  const highlight = (text, term) => {
    if (!term) return text;
    const re = new RegExp(`(${term.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'ig');
    return text.replace(re, '<mark>$1</mark>');
  };

  const renderPagination = (totalPages, currentPage) => {
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
      if (start > 2) buttons.push('<span class="dots">...</span>');
    }

    for (let i = start; i <= end; i++) {
      push(i, null, i === currentPage);
    }

    if (end < totalPages) {
      if (end < totalPages - 1) buttons.push('<span class="dots">...</span>');
      push(totalPages);
    }

    if (currentPage < totalPages) {
      push(currentPage + 1, '>');
      push(totalPages, '»');
    }

    pager.innerHTML = buttons.join('');
  };

  const renderResults = json => {
    const escapeHtml = (str) =>
      str.replace(/[&<>"']/g, (c) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;',
      })[c]);

    const linesWithHighlight = (text, term) => {
      const lines = text.split(/\r?\n/);
      const lowerTerm = term.toLowerCase();
      let matchIndex = lines.findIndex(line => line.toLowerCase().includes(lowerTerm));

      if (matchIndex === -1) matchIndex = 0;

      const start = Math.max(0, matchIndex - 2);
      const end = Math.min(lines.length, matchIndex + 3);
      return lines.slice(start, end).join('\n');
    };
    const pages = Math.ceil(json.total / json.perPage) || 1;
    renderPagination(pages, json.page);

    results.innerHTML = json.results.map(doc => {
    const highlightAndEscape = (str, term) => {
      if (!term) return escapeHtml(str);

      try {
        const escapedTerm = term.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        const re = new RegExp(escapedTerm, 'gi');

        const highlighted = str.replace(re, '<mark>$&</mark>');

        return escapeHtml(highlighted)
          .replace(/&lt;mark&gt;/g, '<mark>')
          .replace(/&lt;\/mark&gt;/g, '</mark>');
      } catch (e) {
        console.error('Invalid regex term:', term, e);
        return escapeHtml(str); // fallback to non-highlighted output
      }
    };

    const snippet = highlightAndEscape(linesWithHighlight(doc.raw, currentTerm), currentTerm);
    const full    = highlightAndEscape(doc.raw, currentTerm);

   return `
      <div class="mongo-result" data-file="${doc.filepath}">
        <div class="mongo-path">
          ${doc.filepath}
          <button class="expand-btn" style="margin-left: 10px;">Expand</button>
       </div>
       <pre class="mongo-snippet">${snippet}</pre>
       <pre class="full-content" style="display:none;">${full}</pre>
     </div>
   `;
   }).join('');
  };


  const fetchResults = (term, page = 1) => {
    const loading = document.getElementById('mongo-search-loading');
    loading.style.display = 'block';
    results.innerHTML = '';

    fetch(`/search/mongo?q=${encodeURIComponent(term)}&page=${page}`)
      .then(r => r.json())
      .then(json => {
        renderResults(json);
      })
     .catch(console.error)
     .finally(() => {
       loading.style.display = 'none';
  });
  };

  // Event – input typing (debounced)
  let typingTimer;
  input.addEventListener('input', () => {
    clearTimeout(typingTimer);
    typingTimer = setTimeout(() => {
      currentTerm = input.value.trim();
      currentPage = 1;
      fetchResults(currentTerm, currentPage);
    }, 300);
  });

  // Event – pagination click
  pager.addEventListener('click', e => {
    if (e.target.classList.contains('page-btn')) {
      currentPage = +e.target.dataset.page;
      fetchResults(currentTerm, currentPage);
    }
  });

  document.addEventListener('click', e => {
    if (e.target.classList.contains('expand-btn')) {
      const container = e.target.closest('.mongo-result');
      const full = container.querySelector('.full-content');
      const isHidden = full.style.display === 'none';
      full.style.display = isHidden ? 'block' : 'none';
      e.target.textContent = isHidden ? 'Collapse' : 'Expand';
    }
  });

  // Event – close
  document.getElementById('mongo-popup-close').addEventListener('click', () => {
    popup.style.display = 'none';
  });

  // Event – open from "Game Data" menu button
  window.toggleMongoPopup = () => {
    if (popup.style.display === 'block') {
      popup.style.display = 'none';
    } else {
      popup.style.display = 'block';
      popup.style.left = '10%';
      popup.style.top  = '10%';
      popup.style.width  = '80%';
      popup.style.height = '80%';
      input.focus();
    }
  };

  // Make popup draggable + resizable with plain JS
  (() => {
    const header = popup.querySelector('.mongo-popup-header');
    let isDrag = false, startX, startY, startL, startT;
    header.style.cursor = 'move';
    header.addEventListener('mousedown', e => {
      isDrag = true;
      startX = e.clientX; startY = e.clientY;
      startL = popup.offsetLeft; startT = popup.offsetTop;
    });
    document.addEventListener('mousemove', e => {
      if (!isDrag) return;
      const dx = e.clientX - startX;
      const dy = e.clientY - startY;
      popup.style.left = startL + dx + 'px';
      popup.style.top  = startT + dy + 'px';
    });
    document.addEventListener('mouseup', () => { isDrag = false; });

    // simple CSS resize (bottom‑right)
    popup.style.resize = 'both';
    popup.style.overflow = 'auto';
  })();

  // Delegate click on result to load file in your existing editor function `loadFile(path)`
  results.addEventListener('click', e => {
    const container = e.target.closest('.mongo-result');
    if (container) {
      window.loadFile && window.loadFile(container.dataset.file);
    }
  });
})();
