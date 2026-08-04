(() => {
  'use strict';
  const forms = [...document.querySelectorAll('.v4-product-form')];
  if (!forms.length) return;

  const money = value => new Intl.NumberFormat('tr-TR', {minimumFractionDigits:2, maximumFractionDigits:2}).format(Number(value || 0)) + ' ₺';
  const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[ch]));
  const totalEl = document.querySelector('[data-ticket-total]');
  const newTotalEl = document.querySelector('[data-new-total]');
  const ticketCountEl = document.querySelector('[data-ticket-count]');
  const itemCountEl = document.querySelector('[data-ticket-item-count]');
  const pendingHeadingCount = document.querySelector('[data-pending-heading-count]');
  const pendingList = document.querySelector('[data-pending-list]');
  const pendingEmpty = document.querySelector('[data-pending-empty]');
  const clearForm = document.querySelector('[data-clear-order-form]');
  const sendButton = document.querySelector('[data-send-order]');
  const committedTotal = Number(totalEl?.dataset.committedTotal || 0);
  let latestSequence = 0;
  let visiblePendingTotal = Number((newTotalEl?.textContent || '0').replace(/\./g,'').replace(',','.').replace(/[^0-9.-]/g,'')) || 0;
  const localQuantities = new Map();

  forms.forEach(form => {
    form.dataset.submitting = '';
    form.removeAttribute('aria-busy');
    const trigger = form.querySelector('[data-quick-trigger]');
    if (trigger) { trigger.disabled = false; trigger.removeAttribute('disabled'); trigger.style.opacity = ''; }
    const card = form.querySelector('.v4-product-card');
    card?.classList.remove('is-submitting','is-processing','is-saving');
    const badge = form.querySelector('[data-quick-badge]');
    const initial = Number(badge && !badge.hidden ? badge.textContent : 0) || 0;
    localQuantities.set(form.dataset.productId, initial);
  });

  const toast = (message, type='') => {
    let el = document.querySelector('.ch-pos-toast');
    if (!el) { el = document.createElement('div'); el.className = 'ch-pos-toast'; document.body.appendChild(el); }
    el.textContent = message; el.className = 'ch-pos-toast ' + type;
    requestAnimationFrame(() => el.classList.add('show'));
    clearTimeout(el._timer); el._timer = setTimeout(() => el.classList.remove('show'), 1500);
  };

  const updateSummary = (pendingTotal, pendingRows) => {
    visiblePendingTotal = Number(pendingTotal || 0);
    const rowCount = pendingRows.length;
    const quantityCount = pendingRows.reduce((sum, row) => sum + Number(row.quantity || 0), 0);
    if (newTotalEl) newTotalEl.textContent = money(visiblePendingTotal);
    if (totalEl) totalEl.textContent = money(committedTotal + visiblePendingTotal);
    if (ticketCountEl) ticketCountEl.textContent = String(rowCount);
    if (itemCountEl) itemCountEl.textContent = String(rowCount);
    if (pendingHeadingCount) pendingHeadingCount.textContent = String(rowCount);
    if (pendingEmpty) pendingEmpty.hidden = rowCount > 0;
    if (clearForm) clearForm.hidden = rowCount === 0;
    if (sendButton) sendButton.disabled = rowCount === 0;
    document.querySelectorAll('[data-mobile-view="ticket"] span').forEach(el => el.textContent = String(rowCount));
    return quantityCount;
  };

  const renderPending = rows => {
    if (!pendingList) return;
    pendingList.innerHTML = rows.map(row => `
      <div class="v4-line pending">
        <div><b>${escapeHtml(row.quantity)}×</b><span><strong>${escapeHtml(row.product_name)}</strong><small>${money(row.unit_price)}${row.item_note ? ' · 📝 ' + escapeHtml(row.item_note) : ''}</small></span></div>
        <em>${money(Number(row.unit_price) * Number(row.quantity))}</em>
        <div class="v4-line-actions">
          <form method="post"><input type="hidden" name="csrf" value="${escapeHtml(forms[0].querySelector('[name=csrf]').value)}"><input type="hidden" name="action" value="pending_qty"><input type="hidden" name="session_id" value="${escapeHtml(forms[0].querySelector('[name=session_id]').value)}"><input type="hidden" name="key" value="${escapeHtml(row.key)}"><input type="hidden" name="delta" value="-1"><button>−</button></form>
          <form method="post"><input type="hidden" name="csrf" value="${escapeHtml(forms[0].querySelector('[name=csrf]').value)}"><input type="hidden" name="action" value="pending_qty"><input type="hidden" name="session_id" value="${escapeHtml(forms[0].querySelector('[name=session_id]').value)}"><input type="hidden" name="key" value="${escapeHtml(row.key)}"><input type="hidden" name="delta" value="1"><button>+</button></form>
          <form method="post"><input type="hidden" name="csrf" value="${escapeHtml(forms[0].querySelector('[name=csrf]').value)}"><input type="hidden" name="action" value="remove_pending"><input type="hidden" name="session_id" value="${escapeHtml(forms[0].querySelector('[name=session_id]').value)}"><input type="hidden" name="key" value="${escapeHtml(row.key)}"><button class="danger">×</button></form>
        </div>
      </div>`).join('');
  };

  const applyServerState = payload => {
    const rows = Array.isArray(payload.pending) ? payload.pending : [];
    renderPending(rows);
    updateSummary(payload.pending_total, rows);
    const byProduct = new Map();
    rows.forEach(row => byProduct.set(String(row.product_id), (byProduct.get(String(row.product_id)) || 0) + Number(row.quantity || 0)));
    forms.forEach(form => {
      const quantity = byProduct.get(form.dataset.productId) || 0;
      localQuantities.set(form.dataset.productId, quantity);
      const badge = form.querySelector('[data-quick-badge]');
      const card = form.querySelector('.v4-product-card');
      if (badge) { badge.textContent = String(quantity); badge.hidden = quantity <= 0; }
      card?.classList.toggle('has-selection', quantity > 0);
      card?.classList.remove('is-saving','is-processing','is-submitting');
      form.dataset.submitting = '';
      form.removeAttribute('aria-busy');
      const trigger = form.querySelector('[data-quick-trigger]');
      if (trigger) { trigger.disabled = false; trigger.removeAttribute('disabled'); trigger.style.opacity = ''; }
    });
  };

  forms.forEach(form => {
    const card = form.querySelector('.v4-product-card');
    const badge = form.querySelector('[data-quick-badge]');
    form.addEventListener('submit', async event => {
      event.preventDefault();
      const sequence = ++latestSequence;
      const id = form.dataset.productId;
      const price = Number(form.dataset.productPrice || 0);
      const previous = localQuantities.get(id) || 0;
      const next = previous + 1;
      localQuantities.set(id, next);
      visiblePendingTotal += price;
      if (badge) { badge.textContent = String(next); badge.hidden = false; }
      card?.classList.add('has-selection','is-tapped','is-saving');
      setTimeout(() => card?.classList.remove('is-tapped'), 100);
      if (totalEl) totalEl.textContent = money(committedTotal + visiblePendingTotal);
      if (newTotalEl) newTotalEl.textContent = money(visiblePendingTotal);
      if (sendButton) sendButton.disabled = false;
      if (clearForm) clearForm.hidden = false;
      if (pendingEmpty) pendingEmpty.hidden = true;

      try {
        const response = await fetch(location.href, {
          method:'POST', body:new FormData(form), credentials:'same-origin',
          headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}
        });
        const payload = await response.json();
        if (!response.ok || !payload.ok) throw new Error(payload.message || 'Ürün eklenemedi.');
        if (sequence >= latestSequence) applyServerState(payload);
        else applyServerState(payload);
      } catch (error) {
        localQuantities.set(id, previous);
        visiblePendingTotal = Math.max(0, visiblePendingTotal - price);
        if (badge) { badge.textContent = String(previous); badge.hidden = previous <= 0; }
        card?.classList.remove('is-saving','is-processing','is-submitting');
        form.dataset.submitting = '';
        form.removeAttribute('aria-busy');
        const trigger = form.querySelector('[data-quick-trigger]');
        if (trigger) { trigger.disabled = false; trigger.removeAttribute('disabled'); trigger.style.opacity = ''; }
        card?.classList.toggle('has-selection', previous > 0);
        card?.classList.add('is-error'); setTimeout(() => card?.classList.remove('is-error'), 320);
        if (totalEl) totalEl.textContent = money(committedTotal + visiblePendingTotal);
        if (newTotalEl) newTotalEl.textContent = money(visiblePendingTotal);
        toast(error.message || 'Ürün eklenemedi.', 'error');
      }
    });
  });
})();
