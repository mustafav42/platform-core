(() => {
  'use strict';
  const moneyText = (value) => Number(value || 0).toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ₺';
  const init = () => {
    const dialog = document.getElementById('paymentDialog');
    if (!dialog || dialog.dataset.v60Ready === '1') return;
    const card = dialog.querySelector('.v4-dialog-card');
    const form = dialog.querySelector('#cashierPaymentForm');
    const summary = dialog.querySelector('.cashier-summary');
    if (!card || !form || !summary) return;
    dialog.dataset.v60Ready = '1';
    dialog.classList.add('ch-payment-v60');

    const tableTitle = card.querySelector('h2')?.textContent?.trim() || 'Adisyon';
    const sourceItems = document.getElementById('cashierItems');
    const sourceFinancial = document.querySelector('.v4-ticket footer .v4-financial');

    const top = document.createElement('header');
    top.className = 'chpay-topbar';
    top.innerHTML = `<div><span class="chpay-brand">🍒 CherryHouse <b>POS 5.0</b></span><span class="chpay-context">${tableTitle}</span></div><div class="chpay-top-actions"><button type="button" data-chpay-move>⇄ Masa Değiştir</button><button type="button" data-chpay-note>▣ Adisyon Notu</button><button type="button" data-payment-close>× Kapat</button></div>`;

    const shell = document.createElement('div');
    shell.className = 'chpay-shell';
    const left = document.createElement('section');
    left.className = 'chpay-ticket';
    left.innerHTML = `<div class="chpay-panel-title"><div><h2>Adisyon</h2><small>${tableTitle}</small></div><span>${sourceItems ? sourceItems.querySelectorAll('.cashier-pay-item').length : 0} Ürün</span></div>`;
    const list = document.createElement('div');
    list.className = 'chpay-ticket-list';
    if (sourceItems) {
      sourceItems.querySelectorAll('.cashier-pay-item').forEach((item) => {
        const clone = item.cloneNode(true);
        clone.classList.add('chpay-line');
        list.appendChild(clone);
      });
    }
    if (!list.children.length) list.innerHTML = '<div class="chpay-empty">Henüz ürün eklenmedi.</div>';
    const actions = document.createElement('div');
    actions.className = 'chpay-ticket-actions';
    actions.innerHTML = `<button type="button" data-chpay-add>＋ Ürün Ekle</button><button type="button" data-chpay-discount>% İndirim</button><button type="button" data-chpay-cancel>⌫ Ürünü İptal Et</button>`;
    const totals = document.createElement('div');
    totals.className = 'chpay-totals';
    totals.innerHTML = sourceFinancial ? sourceFinancial.innerHTML : '';
    left.append(list, actions, totals);

    const right = document.createElement('section');
    right.className = 'chpay-payment';
    const heading = document.createElement('div');
    heading.className = 'chpay-heading';
    heading.innerHTML = '<h1>Ödeme</h1><p>Tutar, ürün veya kişi bazlı tahsilat yapın.</p>';
    summary.classList.add('chpay-summary');
    right.append(heading, summary, form);
    shell.append(left, right);

    [...card.children].forEach(el => el.remove());
    card.append(top, shell);

    top.querySelector('[data-payment-close]')?.addEventListener('click', () => dialog.close());
    top.querySelector('[data-chpay-move]')?.addEventListener('click', () => { dialog.close(); document.getElementById('moveDialog')?.showModal(); });
    actions.querySelector('[data-chpay-discount]')?.addEventListener('click', () => { dialog.close(); document.getElementById('discountDialog')?.showModal(); });
    actions.querySelector('[data-chpay-add]')?.addEventListener('click', () => {
      const url = new URL(location.href); url.searchParams.delete('pay'); url.searchParams.set('drawer','1'); location.href = url.toString();
    });

    const modeButtons = form.querySelectorAll('[data-payment-mode]');
    modeButtons.forEach(btn => {
      const label = btn.textContent.trim();
      if (label === 'Tüm Hesap') btn.innerHTML = '▤ Tutar Girerek Ödeme';
      if (label === 'Ürün Seç') btn.innerHTML = '▦ Ürün Bazlı Ödeme';
      if (label === 'Tutar Gir') btn.innerHTML = '♙ Kişi Bazlı Ödeme';
    });

    const submit = form.querySelector('[data-payment-submit]');
    if (submit) submit.textContent = '✓ Tahsilatı Tamamla';
    const methods = form.querySelectorAll('[data-method]');
    const icons = {cash:'💵', card:'💳', meal_card:'🍽', transfer:'↔'};
    methods.forEach(btn => { const k=btn.dataset.method; btn.innerHTML = `<span>${icons[k]||'•••'}</span><b>${btn.textContent.trim()}</b>`; });
  };
  document.addEventListener('DOMContentLoaded', init);
})();
