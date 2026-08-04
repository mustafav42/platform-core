(() => {
  'use strict';
  const init = () => {
    const screen = document.querySelector('.ch-pay-screen');
    const form = screen?.querySelector('.ch-pay-grid');
    const center = screen?.querySelector('.ch-pay-center');
    if (!screen || !form || !center || screen.dataset.v611Ready === '1') return;
    screen.dataset.v611Ready = '1';

    // Remove the v61 elements from their old location and rebuild as a real top row.
    center.querySelectorAll(':scope > .chpay61-summary-cards, :scope > .chpay61-tabs').forEach(el => el.remove());
    form.querySelectorAll(':scope > .chpay61-summary-wrap').forEach(el => el.remove());

    const live = screen.querySelector('.ch-pay-live-summary');
    const values = live ? [...live.querySelectorAll('b')].map(x => x.textContent.trim()) : [];
    const wrap = document.createElement('section');
    wrap.className = 'chpay61-summary-wrap';
    wrap.innerHTML = `
      <div class="chpay61-summary-cards">
        <article><span>Genel Toplam</span><b>${values[0] || '0,00 ₺'}</b></article>
        <article><span>Ödenen Toplam</span><b>${values[1] || '0,00 ₺'}</b></article>
        <article><span>Kalan Tutar</span><b>${values[2] || '0,00 ₺'}</b></article>
      </div>
      <div class="chpay61-tabs" role="tablist">
        <button type="button" class="active" data-v611-mode="amount">⌨ Tutar Girerek Ödeme</button>
        <button type="button" data-v611-mode="products">▦ Ürün Bazlı Ödeme</button>
        <button type="button" data-v611-mode="person">♙ Kişi Bazlı Ödeme</button>
      </div>`;
    form.insertBefore(wrap, center);

    wrap.addEventListener('click', (event) => {
      const button = event.target.closest('[data-v611-mode]');
      if (!button) return;
      wrap.querySelectorAll('[data-v611-mode]').forEach(x => x.classList.toggle('active', x === button));
      const hidden = document.getElementById('uPaymentMode');
      if (hidden) hidden.value = button.dataset.v611Mode === 'products' ? 'products' : 'amount';
    });
  };
  document.addEventListener('DOMContentLoaded', init);
})();
