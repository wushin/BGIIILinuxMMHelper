/* /public/js/filesSectionAutoHeight.js
 *
 * Auto-sizes the "Files & Folders (all depths)" section so it expands
 * to match the container — both HEIGHT (scrollable body) and now WIDTH.
 *
 * WHAT THIS DOES (width fixes):
 * - Forces the section body to stretch: width:100%; max-width:100%; min-width:0
 * - Walks up ancestor chain: for any flex/grid parent, sets min-width:0 (and overflow:hidden)
 *   so the child can actually expand instead of being constrained by min-content sizing.
 * - Applies word-wrapping on long path segments so they don't force overflow.
 *
 * Also preserves the earlier height handling (scrollable body, sticky header).
 */

(() => {
  const HEADING_TEXT = 'Files & Folders (all depths)';

  function findSection() {
    // Find a heading element with the expected text (case-insensitive, trimmed)
    const headings = Array.from(
      document.querySelectorAll('h1,h2,h3,h4,h5,h6,.section-title,.panel-title')
    );
    const header = headings.find(h =>
      (h.textContent || '').trim().toLowerCase().startsWith(HEADING_TEXT.toLowerCase())
    );
    if (!header) return null;

    // Prefer a nearby container section/panel; otherwise the header's parent
    const container =
      header.closest('section, .section, .panel, .card, .box, .sidebar-section') ||
      header.parentElement;
    if (!container) return null;

    // Try to find an explicit body; fallback to the next block sibling
    let body =
      container.querySelector('.section-body, .panel-body, .card-body, .content, .list, .tree') ||
      (header.nextElementSibling && isBlock(header.nextElementSibling) ? header.nextElementSibling : null);

    // As a last resort, create a body wrapper after the header so we can manage sizing
    if (!body) {
      body = document.createElement('div');
      body.className = 'section-body';
      header.insertAdjacentElement('afterend', body);
      // Move all following siblings into body
      let sib = body.nextSibling;
      while (sib) {
        const next = sib.nextSibling;
        if (sib.nodeType === 1 /* element */) body.appendChild(sib);
        sib = next;
      }
    }

    return { header, container, body };
  }

  function isBlock(el) {
    const disp = getComputedStyle(el).display;
    return disp !== 'inline' && disp !== 'inline-block' && disp !== 'contents';
  }

  // Allow a child to occupy full width inside flex/grid ancestors.
  function unlockWidthThroughAncestors(el) {
    let p = el && el.parentElement;
    while (p && p !== document.body) {
      const cs = getComputedStyle(p);
      const isFlex = cs.display.includes('flex');
      const isGrid = cs.display.includes('grid');
      if (isFlex || isGrid) {
        // These two are the usual culprits that prevent width from stretching
        if (!p.style.minWidth) p.style.minWidth = '0';
        if (!p.style.overflow) p.style.overflow = 'hidden';
      }
      p = p.parentElement;
    }
  }

  function applyWidth(container, body) {
    // Ensure container participates in layout and can pass width down
    const cStyle = container.style;
    if (!cStyle.display) cStyle.display = 'flex';
    if (!getComputedStyle(container).display.includes('flex')) cStyle.display = 'flex';
    cStyle.flexDirection = 'column';
    cStyle.minWidth = '0';           // critical for width in flex/grid chains
    cStyle.width = '100%';
    cStyle.boxSizing = 'border-box';

    // Make the body stretch across the container and wrap long paths
    const bStyle = body.style;
    bStyle.flex = '1 1 auto';
    bStyle.minWidth = '0';          // critical
    bStyle.width = '100%';
    bStyle.maxWidth = '100%';
    bStyle.boxSizing = 'border-box';
    bStyle.overflow = bStyle.overflow || 'auto';
    bStyle.wordBreak = 'break-word';
    bStyle.overflowWrap = 'anywhere'; // handle very long filenames/paths

    // If the body has an inner tree/list/table, make those full-width too
    const inner = body.querySelector('.tree, .list, table, ul, ol, .files-tree, .directory-tree');
    if (inner) {
      const i = inner.style;
      i.minWidth = '0';
      i.width = '100%';
      i.maxWidth = '100%';
      i.boxSizing = 'border-box';
      // For tables that like to min-content size:
      if (inner.tagName === 'TABLE') {
        i.tableLayout = 'fixed';
        // Let cells wrap
        inner.querySelectorAll('td, th').forEach(cell => {
          cell.style.wordBreak = 'break-word';
          cell.style.overflowWrap = 'anywhere';
        });
      }
    }

    // Make sure flex/grid ancestors don't constrain width
    unlockWidthThroughAncestors(container);
    unlockWidthThroughAncestors(body);
  }

  function applyHeight(container, body) {
    // Height logic (kept from earlier): scrollable section body
    const cStyle = container.style;
    cStyle.minHeight = '0';
    const bStyle = body.style;
    bStyle.flex = bStyle.flex || '1 1 auto';
    bStyle.minHeight = '0';
    // Cap to viewport to avoid overflow beyond bottom
    const rect = container.getBoundingClientRect();
    const top = rect.top + window.scrollY;
    const viewportBottom = window.scrollY + window.innerHeight;
    const padding = 12;
    const available = Math.max(120, viewportBottom - (top + padding));
    const isFlexy =
      getComputedStyle(container).display.includes('flex') ||
      getComputedStyle(container.parentElement || document.body).display.includes('flex');

    if (!isFlexy) {
      bStyle.height = available + 'px';
    } else {
      bStyle.maxHeight = available + 'px';
    }
  }

  function sizeSection() {
    const s = findSection();
    if (!s) return;
    const { container, body } = s;
    applyWidth(container, body);
    applyHeight(container, body);
  }

  function schedule() {
    clearTimeout(schedule._t);
    schedule._t = setTimeout(sizeSection, 16);
  }

  function init() {
    sizeSection();

    // Recalculate when the window resizes or orientation changes
    window.addEventListener('resize', schedule);
    window.addEventListener('orientationchange', schedule);

    // When the sidebar collapses/expands (from /js/sidebarToggle.js), recalc
    window.addEventListener('sidebar:toggled', schedule);

    // Observe DOM changes (re-renders, route changes, etc.)
    const mo = new MutationObserver(schedule);
    mo.observe(document.body, { childList: true, subtree: true });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();

