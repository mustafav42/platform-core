(() => {
  'use strict';

  const grids = [...document.querySelectorAll('.ch-table-grid[data-table-grid]')];
  if (!grids.length) return;

  document.body.classList.add('ch-pos-tables-only');

  const state = new WeakMap();

  function visibleCards(grid) {
    return [...grid.querySelectorAll('.v4-table-card')].filter(card => !card.hidden && card.style.display !== 'none');
  }

  function chooseLayout(count, width, height) {
    if (count <= 0) return {cols:1, rows:1};

    // Table cards are intentionally slightly taller than wide.
    const desiredRatio = 0.82;
    let best = null;

    for (let cols = 1; cols <= count; cols++) {
      const rows = Math.ceil(count / cols);
      const gap = count > 36 ? 7 : count > 24 ? 9 : 12;
      const cellW = (width - gap * (cols - 1)) / cols;
      const cellH = (height - gap * (rows - 1)) / rows;
      if (cellW <= 0 || cellH <= 0) continue;

      const ratio = cellW / cellH;
      const ratioPenalty = Math.abs(Math.log(Math.max(.01, ratio / desiredRatio)));
      const touchPenalty = cellW < 86 || cellH < 72 ? 5 : 0;
      const areaScore = Math.min(cellW / 165, cellH / 205);
      const score = areaScore - ratioPenalty * .72 - touchPenalty;

      if (!best || score > best.score) {
        best = {cols, rows, gap, cellW, cellH, score};
      }
    }

    return best || {cols:count, rows:1, gap:7, cellW:width/count, cellH:height};
  }

  function densityFor(cellW, cellH) {
    if (cellH >= 205 && cellW >= 155) return 'full';
    if (cellH >= 165 && cellW >= 130) return 'medium';
    if (cellH >= 118 && cellW >= 100) return 'compact';
    return 'micro';
  }

  function fit(grid) {
    const cards = visibleCards(grid);
    const count = cards.length;
    const rect = grid.getBoundingClientRect();
    if (!rect.width || !rect.height) return;

    const layout = chooseLayout(count, rect.width, rect.height);
    grid.style.setProperty('--ch-grid-gap', layout.gap + 'px');
    grid.style.gridTemplateColumns = `repeat(${layout.cols}, minmax(0,1fr))`;
    grid.style.gridTemplateRows = `repeat(${layout.rows}, minmax(0,1fr))`;
    grid.dataset.density = densityFor(layout.cellW, layout.cellH);
    grid.dataset.columns = String(layout.cols);
    grid.dataset.rows = String(layout.rows);
  }

  function applyFilters(grid) {
    const current = state.get(grid) || {area:'all', search:''};
    const search = current.search.trim().toLocaleLowerCase('tr-TR');

    grid.querySelectorAll('.v4-table-card').forEach(card => {
      const areaMatch = current.area === 'all' || card.dataset.area === current.area;
      const name = (card.dataset.tableName || '').toLocaleLowerCase('tr-TR');
      const searchMatch = !search || name.includes(search);
      card.hidden = !(areaMatch && searchMatch);
    });

    requestAnimationFrame(() => fit(grid));
  }

  grids.forEach(grid => {
    state.set(grid, {area:'all', search:''});

    const shell = grid.closest('.v4-table-layout, .v4-cash-layout') || document;
    const main = grid.closest('.v4-table-main');
    const searchInput = main?.querySelector('[data-table-search]');

    searchInput?.addEventListener('input', () => {
      const current = state.get(grid);
      current.search = searchInput.value || '';
      applyFilters(grid);
    });

    shell.querySelectorAll('.v4-side-link[data-area]').forEach(button => {
      button.addEventListener('click', () => {
        shell.querySelectorAll('.v4-side-link[data-area]').forEach(x => x.classList.remove('active'));
        button.classList.add('active');
        const current = state.get(grid);
        current.area = button.dataset.area || 'all';
        applyFilters(grid);
      });
    });

    const resize = new ResizeObserver(() => fit(grid));
    resize.observe(grid);
    window.addEventListener('orientationchange', () => setTimeout(() => fit(grid), 100));
    window.addEventListener('resize', () => fit(grid), {passive:true});

    applyFilters(grid);
  });
})();