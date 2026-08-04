(()=>{
  'use strict';
  const STORAGE_PREFIX='ch.nav.';
  const groups=()=>Array.from(document.querySelectorAll('[data-nav-group]'));

  function setState(group, open, persist=true){
    const toggle=group.querySelector('[data-nav-group-toggle]');
    const items=group.querySelector('.ch-nav-items');
    group.classList.toggle('is-collapsed', !open);
    group.classList.toggle('is-expanded', open);
    if(toggle) toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    if(items){
      items.hidden=!open;
      items.setAttribute('aria-hidden', open ? 'false' : 'true');
    }
    if(persist){
      const id=group.getAttribute('data-group-id') || '';
      if(id){
        try{ localStorage.setItem(STORAGE_PREFIX+id, open ? 'open' : 'closed'); }catch(_e){}
      }
    }
  }

  function initialize(){
    groups().forEach(group=>{
      const id=group.getAttribute('data-group-id') || '';
      const current=group.classList.contains('is-current') || !!group.querySelector('.ch-nav-items a.active');
      let stored='';
      try{ stored=id ? (localStorage.getItem(STORAGE_PREFIX+id) || '') : ''; }catch(_e){}
      const shouldOpen=current || stored==='open';
      setState(group, shouldOpen, false);
      const toggle=group.querySelector('[data-nav-group-toggle]');
      if(toggle){
        toggle.setAttribute('role','button');
        toggle.setAttribute('tabindex','0');
      }
    });
  }

  // Capture phase prevents older sidebar listeners from toggling the same group twice.
  document.addEventListener('click', event=>{
    const toggle=event.target.closest('[data-nav-group-toggle]');
    if(!toggle) return;
    const group=toggle.closest('[data-nav-group]');
    if(!group) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    setState(group, group.classList.contains('is-collapsed'));
  }, true);

  document.addEventListener('keydown', event=>{
    if(event.key!=='Enter' && event.key!==' ') return;
    const toggle=event.target.closest('[data-nav-group-toggle]');
    if(!toggle) return;
    const group=toggle.closest('[data-nav-group]');
    if(!group) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    setState(group, group.classList.contains('is-collapsed'));
  }, true);

  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded', initialize, {once:true});
  else initialize();
})();
