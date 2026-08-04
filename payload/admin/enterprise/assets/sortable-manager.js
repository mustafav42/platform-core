(() => {
  'use strict';

  const endpoint = document.documentElement.dataset.reorderEndpoint || 'reorder.php';
  const token = document.querySelector('meta[name="ch-csrf"]')?.content || '';
  let active = null;

  const toast = (message, ok = true) => {
    let node = document.getElementById('ch-sort-toast');
    if (!node) {
      node = document.createElement('div');
      node.id = 'ch-sort-toast';
      document.body.appendChild(node);
    }
    node.className = `ch-sort-toast ${ok ? 'is-success' : 'is-error'} is-visible`;
    node.textContent = message;
    clearTimeout(node._timer);
    node._timer = setTimeout(() => node.classList.remove('is-visible'), 2200);
  };

  const listItems = container => [...container.querySelectorAll(':scope > [data-sort-id]')];
  const snapshot = container => listItems(container).map(item => item.dataset.sortId);
  const restore = (container, ids) => ids.forEach(id => {
    const item = container.querySelector(`:scope > [data-sort-id="${CSS.escape(id)}"]`);
    if (item) container.appendChild(item);
  });

  async function save(container, before) {
    const items = listItems(container);
    const payload = {
      csrf_token: token,
      type: container.dataset.sortableList,
      category_id: Number(container.dataset.categoryId || 0),
      ids: items.map(item => Number(item.dataset.sortId))
    };
    container.classList.add('is-saving');
    try {
      const response = await fetch(endpoint, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
        body: JSON.stringify(payload)
      });
      const data = await response.json().catch(() => ({ok: false, message: 'Sunucudan geçersiz yanıt alındı.'}));
      if (!response.ok || !data.ok) throw new Error(data.message || 'Sıralama kaydedilemedi.');
      items.forEach((item, index) => {
        const order = item.querySelector('[data-order-label]');
        if (order) order.textContent = String((index + 1) * 10).padStart(2, '0');
      });
      toast(data.message || 'Sıralama kaydedildi.');
      if (navigator.vibrate) navigator.vibrate(35);
    } catch (error) {
      restore(container, before);
      toast(error.message || 'Sıralama kaydedilemedi.', false);
    } finally {
      container.classList.remove('is-saving');
    }
  }

  function moveAtPoint(event) {
    if (!active) return;
    const {container, item} = active;
    const target = document.elementFromPoint(event.clientX, event.clientY)?.closest('[data-sort-id]');
    if (!target || target === item || target.parentElement !== container) return;
    const rect = target.getBoundingClientRect();
    const verticalMid = rect.top + rect.height / 2;
    container.insertBefore(item, event.clientY > verticalMid ? target.nextSibling : target);
  }

  function finish() {
    if (!active) return;
    const state = active;
    active = null;
    state.item.classList.remove('is-dragging');
    document.body.classList.remove('ch-sorting');
    try { state.handle.releasePointerCapture(state.pointerId); } catch (_) {}
    const after = snapshot(state.container);
    if (after.join(',') !== state.before.join(',')) save(state.container, state.before);
  }

  document.querySelectorAll('[data-sortable-list]').forEach(container => {
    container.querySelectorAll(':scope > [data-sort-id] [data-drag-handle]').forEach(handle => {
      handle.addEventListener('pointerdown', event => {
        if (event.button !== undefined && event.button !== 0) return;
        const item = handle.closest('[data-sort-id]');
        if (!item || container.classList.contains('is-saving')) return;
        event.preventDefault();
        active = {container, item, handle, pointerId: event.pointerId, before: snapshot(container)};
        handle.setPointerCapture?.(event.pointerId);
        item.classList.add('is-dragging');
        document.body.classList.add('ch-sorting');
      });
      handle.addEventListener('pointermove', event => {
        if (!active || active.pointerId !== event.pointerId) return;
        event.preventDefault();
        moveAtPoint(event);
      });
      handle.addEventListener('pointerup', finish);
      handle.addEventListener('pointercancel', finish);
    });
  });
})();
