(() => {
  'use strict';
  const init = () => {
    const screen = document.querySelector('.ch-pay-screen');
    if (!screen || screen.dataset.v61Ready === '1') return;
    screen.dataset.v61Ready = '1';
    const center = screen.querySelector('.ch-pay-center');
    const amount = screen.querySelector('.ch-pay-amount-card');
    if (center && amount) {
      const live = screen.querySelector('.ch-pay-live-summary');
      const values = live ? [...live.querySelectorAll('b')].map(x => x.textContent.trim()) : [];
      const cards = document.createElement('div');
      cards.className = 'chpay61-summary-cards';
      cards.innerHTML = `
        <article><span>Genel Toplam</span><b>${values[0] || '0,00 ₺'}</b></article>
        <article><span>Ödenen Toplam</span><b>${values[1] || '0,00 ₺'}</b></article>
        <article><span>Kalan Tutar</span><b>${values[2] || '0,00 ₺'}</b></article>`;
      center.insertBefore(cards, amount);
      amount.style.display = 'none';
      const tabs = document.createElement('div');
      tabs.className = 'chpay61-tabs';
      tabs.innerHTML = '<button type="button" class="active" data-v61-mode="amount">⌨ Tutar Girerek Ödeme</button><button type="button" data-v61-mode="products">▦ Ürün Bazlı Ödeme</button><button type="button" data-v61-mode="person">♙ Kişi Bazlı Ödeme</button>';
      cards.after(tabs);
      tabs.addEventListener('click', e => {
        const btn = e.target.closest('button'); if (!btn) return;
        tabs.querySelectorAll('button').forEach(b => b.classList.toggle('active', b === btn));
        const mode = btn.dataset.v61Mode;
        const hidden = document.getElementById('uPaymentMode');
        if (hidden) hidden.value = mode === 'products' ? 'products' : 'amount';
      });
    }
  };
  document.addEventListener('DOMContentLoaded', init);
})();
