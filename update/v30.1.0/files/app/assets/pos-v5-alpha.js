(() => {
  'use strict';
  document.documentElement.dataset.chPos = '5-alpha';
  const body = document.body;
  if (!body || !body.classList.contains('ch-pos5')) return;
  const search = document.querySelector('[data-product-search]');
  if (search && window.matchMedia('(min-width: 821px)').matches) {
    document.addEventListener('keydown', (event) => {
      if (event.key === '/' && !/INPUT|TEXTAREA|SELECT/.test(document.activeElement?.tagName || '')) {
        event.preventDefault(); search.focus();
      }
    });
  }
  document.querySelectorAll('.v4-product-card,.v4-table-card a,.ch-direct-table-button').forEach((el) => {
    el.addEventListener('pointerdown', () => el.classList.add('ch-pressed'));
    ['pointerup','pointercancel','pointerleave'].forEach((name) => el.addEventListener(name, () => el.classList.remove('ch-pressed')));
  });
})();
