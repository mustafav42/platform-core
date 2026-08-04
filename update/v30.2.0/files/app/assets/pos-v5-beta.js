(() => {
  'use strict';
  const body = document.body;
  if (!body || !body.classList.contains('ch-pos5')) return;
  document.documentElement.dataset.chPos = '5-beta';

  const money = (value) => new Intl.NumberFormat('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2}).format(Number(value || 0)) + ' ₺';
  const endpoint = body.dataset.posStateEndpoint || '';
  const grid = document.querySelector('[data-table-grid]');

  // Restore scroll positions after product/category/payment operations.
  const scrollKey = 'ch-pos5-scroll:' + location.pathname;
  const restore = sessionStorage.getItem(scrollKey);
  if (restore) {
    try {
      const values = JSON.parse(restore);
      requestAnimationFrame(() => {
        document.querySelectorAll('.v4-product-zone,.v4-ticket-body,.v4-category-rail nav').forEach((el, index) => {
          if (typeof values[index] === 'number') el.scrollTop = values[index];
        });
      });
    } catch (_) {}
  }
  document.addEventListener('submit', () => {
    const values = [...document.querySelectorAll('.v4-product-zone,.v4-ticket-body,.v4-category-rail nav')].map(el => el.scrollTop);
    sessionStorage.setItem(scrollKey, JSON.stringify(values));
  }, true);

  // Live table-state refresh without reloading the screen.
  async function refreshTables() {
    if (!grid || !endpoint || document.hidden) return;
    try {
      const response = await fetch(endpoint, {headers: {'Accept': 'application/json'}, cache: 'no-store', credentials: 'same-origin'});
      if (!response.ok) return;
      const payload = await response.json();
      if (!payload.ok || !Array.isArray(payload.tables)) return;
      payload.tables.forEach((table) => {
        const card = grid.querySelector(`[data-table-id="${table.id}"]`);
        if (!card) return;
        const total = card.querySelector('.v4-table-total');
        if (total) total.textContent = money(table.remaining);
        card.dataset.sessionId = String(table.session_id || 0);
        card.classList.toggle('is-open', Boolean(table.session_id));
        card.classList.toggle('is-empty', !table.session_id && table.status === 'empty');
      });
      grid.dataset.lastSync = new Date().toISOString();
    } catch (_) {}
  }
  if (grid && endpoint) {
    refreshTables();
    window.setInterval(refreshTables, 8000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refreshTables(); });
  }

  // Prevent accidental double submissions during busy service.
  document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || form.dataset.allowRepeat === '1') return;
    const button = form.querySelector('button[type="submit"],button:not([type])');
    if (!button || button.disabled) return;
    requestAnimationFrame(() => {
      button.disabled = true;
      button.dataset.originalText ||= button.textContent || '';
      button.classList.add('is-processing');
    });
  }, true);

  // Auto-dismiss success feedback and highlight remaining balance after partial payment.
  document.querySelectorAll('[data-auto-dismiss]').forEach((el) => setTimeout(() => el.remove(), 3200));
  const remaining = document.querySelector('.ch-pay-ticket-summary strong,.ch-pay-live-summary span:last-child');
  if (remaining && new URLSearchParams(location.search).has('paid')) {
    remaining.classList.add('ch-payment-updated');
    setTimeout(() => remaining.classList.remove('ch-payment-updated'), 1800);
  }

  // Keyboard/touch workflow helpers.
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      const dialog = document.querySelector('dialog[open]');
      if (dialog && typeof dialog.close === 'function') dialog.close();
    }
  });
})();
