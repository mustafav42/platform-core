(() => {
  'use strict';
  const init = () => {
    const root = document.querySelector('[data-pay-screen]');
    if (!root) return;
    const tabs = root.querySelectorAll('[data-pay-tab]');
    const mode = document.getElementById('uPaymentMode');
    const free = root.querySelector('[data-free-amount]');
    const clear = root.querySelector('[data-clear-selection]');
    tabs.forEach(btn => btn.addEventListener('click', () => {
      tabs.forEach(x => x.classList.toggle('active', x === btn));
      const tab = btn.dataset.payTab;
      if (tab === 'products') {
        if (mode) mode.value = 'products';
      } else {
        if (mode) mode.value = 'amount';
        if (tab === 'amount') free?.click();
        if (tab === 'person') {
          clear?.click();
          root.querySelector('[data-selection-label]')?.replaceChildren(document.createTextNode('Kişi başına tahsil edilecek tutarı girin'));
        }
      }
    }));
  };
  document.addEventListener('DOMContentLoaded', init);
})();
