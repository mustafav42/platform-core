(() => {
  const sidebar = document.querySelector('.enterprise-sidebar');
  document.querySelector('[data-sidebar-toggle]')?.addEventListener('click', () => sidebar?.classList.toggle('open'));
  document.querySelectorAll('[data-copy]').forEach((button) => {
    button.addEventListener('click', async () => {
      const value = button.getAttribute('data-copy') || '';
      try {
        await navigator.clipboard.writeText(value);
        const old = button.textContent;
        button.textContent = 'Kopyalandı';
        setTimeout(() => { button.textContent = old; }, 1400);
      } catch {
        window.prompt('Dosya yolunu kopyalayın:', value);
      }
    });
  });
  const input = document.querySelector('.drop-zone input[type=file]');
  input?.addEventListener('change', () => {
    const label = document.querySelector('.drop-zone b');
    if (label && input.files?.length) label.textContent = `${input.files.length} görsel seçildi`;
  });
})();
