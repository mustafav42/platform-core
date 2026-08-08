(() => {
  'use strict';
  const root = document.querySelector('[data-pay-screen]');
  if (!root) return;

  const open = root.querySelector('[data-open-complimentary]');
  const dialog = document.getElementById('complimentaryDialog');
  const paymentSelection = document.getElementById('uProductSelection');
  const giftSelection = dialog?.querySelector('[data-gift-selection]');
  const label = dialog?.querySelector('[data-gift-selection-label]');
  const submit = dialog?.querySelector('[data-gift-submit]');

  const selection = () => {
    try { return JSON.parse(paymentSelection?.value || '{}') || {}; }
    catch { return {}; }
  };

  open?.addEventListener('click', () => {
    const selected = selection();
    const count = Object.values(selected).reduce((sum, qty) => sum + Number(qty || 0), 0);
    if (giftSelection) giftSelection.value = JSON.stringify(selected);
    if (label) label.textContent = count > 0
      ? `${count} adet ürün ikram olarak işaretlenecek.`
      : 'Önce soldaki adisyondan ürün seçin.';
    if (submit) submit.disabled = count <= 0;
    dialog?.showModal();
  });

  dialog?.querySelector('form')?.addEventListener('submit', (event) => {
    const selected = selection();
    const count = Object.values(selected).reduce((sum, qty) => sum + Number(qty || 0), 0);
    if (count <= 0) {
      event.preventDefault();
      alert('İkram için önce soldaki adisyondan ürün seçin.');
      return;
    }
    if (giftSelection) giftSelection.value = JSON.stringify(selected);
  });
})();