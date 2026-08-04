document.addEventListener('DOMContentLoaded',()=>{
  const tableSearch=document.querySelector('[data-table-search]');
  const tableCards=[...document.querySelectorAll('.v4-table-card')];
  const filter=()=>{const q=(tableSearch?.value||'').toLocaleLowerCase('tr-TR').trim();tableCards.forEach(c=>{const area=window.v4Area||'all';c.hidden=(q&&!c.dataset.tableName.includes(q))||(area!=='all'&&c.dataset.area!==area)})};
  tableSearch?.addEventListener('input',filter);
  document.querySelectorAll('[data-area]').forEach(btn=>{if(!btn.classList.contains('v4-table-card'))btn.addEventListener('click',()=>{document.querySelectorAll('.v4-side-link').forEach(x=>x.classList.remove('active'));btn.classList.add('active');window.v4Area=btn.dataset.area;filter()})});
  const productSearch=document.querySelector('[data-product-search]');
  productSearch?.addEventListener('input',()=>{const q=productSearch.value.toLocaleLowerCase('tr-TR').trim();document.querySelectorAll('[data-product-name]').forEach(x=>x.hidden=q&&!x.dataset.productName.includes(q))});

  const updateClock=()=>{const now=new Date();document.querySelectorAll('[data-live-clock]').forEach(x=>x.textContent=now.toLocaleTimeString('tr-TR',{hour:'2-digit',minute:'2-digit'}));document.querySelectorAll('[data-opened]').forEach(card=>{const out=card.querySelector('[data-open-duration]');if(!out)return;const opened=new Date(card.dataset.opened.replace(' ','T'));if(Number.isNaN(opened.getTime()))return;const mins=Math.max(0,Math.floor((now-opened)/60000));out.textContent=mins<60?`${mins} dk`:`${Math.floor(mins/60)} sa ${mins%60} dk`;});};
  updateClock();setInterval(updateClock,30000);

  document.querySelector('[data-fullscreen]')?.addEventListener('click',async()=>{try{if(!document.fullscreenElement)await document.documentElement.requestFullscreen();else await document.exitFullscreen()}catch(e){}});
  document.querySelectorAll('[data-focus-product]').forEach(btn=>btn.addEventListener('click',()=>{const field=document.querySelector('[data-product-search]');if(!field)return;field.focus();field.closest('.v4-search')?.classList.add('is-focused');setTimeout(()=>field.closest('.v4-search')?.classList.remove('is-focused'),900)}));
  document.querySelectorAll('[data-scroll-ticket]').forEach(btn=>btn.addEventListener('click',()=>document.querySelector('.v4-ticket')?.scrollIntoView({behavior:'smooth',block:'start'})));

  const paymentDialog=document.getElementById('paymentDialog');
  if(paymentDialog){
    const inputs=[...paymentDialog.querySelectorAll('.v4-method-grid input')];
    const remaining=Number((paymentDialog.querySelector('.v4-payment-remaining strong')?.textContent||'0').replace(/\./g,'').replace(',','.').replace(/[^0-9.]/g,''))||0;
    const clear=()=>{inputs.forEach(i=>i.value='0');paymentDialog.querySelectorAll('[data-pay-method]').forEach(b=>b.classList.remove('active'))};
    paymentDialog.querySelectorAll('[data-pay-method]').forEach(btn=>btn.addEventListener('click',()=>{clear();const input=paymentDialog.querySelector(`[name="${btn.dataset.payMethod}"]`);if(input)input.value=remaining.toFixed(2);btn.classList.add('active')}));
    paymentDialog.querySelectorAll('[data-pay-part]').forEach(btn=>btn.addEventListener('click',()=>{const active=paymentDialog.querySelector('[data-pay-method].active')||paymentDialog.querySelector('[data-pay-method="cash"]');active?.click();const input=paymentDialog.querySelector(`[name="${active?.dataset.payMethod||'cash'}"]`);if(input)input.value=(remaining*Number(btn.dataset.payPart||1)).toFixed(2)}));
    paymentDialog.querySelector('[data-clear-payment]')?.addEventListener('click',clear);
  }
});
function v4OpenWaiterTable(id,name){document.getElementById('waiterTableId').value=id;document.getElementById('waiterOpenTitle').textContent=name+' Masasını Aç';document.getElementById('waiterOpenTable').showModal()}
function v4Step(id,delta){const e=document.getElementById(id);if(!e)return;e.value=Math.max(Number(e.min||1),Math.min(Number(e.max||99),Number(e.value||1)+delta))}
window.v4OpenWaiterTable=v4OpenWaiterTable;window.v4Step=v4Step;

/* CherryHouse POS v4.2 — hızlı adet, uzun basma notu ve telefon görünümü */
(()=>{
  const forms=[...document.querySelectorAll('[data-quick-product]:not([data-optimistic-product="1"])')];
  const noteDialog=document.getElementById('quickNoteDialog');
  let noteForm=null;

  const submitQuick=(form)=>{
    if(!form || form.dataset.submitting==='1') return;
    form.dataset.submitting='1';
    const trigger=form.querySelector('[data-quick-trigger]');
    trigger?.classList.add('is-submitting');
    if(typeof form.requestSubmit==='function') form.requestSubmit(); else form.submit();
  };

  forms.forEach(form=>{
    const trigger=form.querySelector('[data-quick-trigger]');
    const qty=form.querySelector('[data-quick-quantity]');
    const badge=form.querySelector('[data-quick-badge]');
    let count=0, sendTimer=0, holdTimer=0, held=false, pointerDown=false;

    const render=()=>{
      if(!badge)return;
      badge.textContent=String(count);
      badge.hidden=count<1;
      badge.classList.remove('pop');
      void badge.offsetWidth;
      badge.classList.add('pop');
      trigger?.classList.remove('is-tapped');
      void trigger?.offsetWidth;
      trigger?.classList.add('is-tapped');
    };
    const queue=()=>{
      count=Math.min(99,count+1);
      if(qty)qty.value=String(count);
      render();
      window.clearTimeout(sendTimer);
      sendTimer=window.setTimeout(()=>submitQuick(form),520);
    };
    const openNote=()=>{
      held=true;
      window.clearTimeout(sendTimer);
      count=Math.max(1,count||1);
      if(qty)qty.value=String(count);
      noteForm=form;
      if(noteDialog){
        const title=noteDialog.querySelector('[data-quick-note-title]');
        const text=noteDialog.querySelector('[data-quick-note-text]');
        noteDialog.querySelectorAll('[data-note-presets] button').forEach(b=>b.classList.remove('active'));
        if(title)title.textContent=(form.dataset.productLabel||'Ürün')+' için not';
        if(text)text.value='';
        noteDialog.showModal();
      }
    };

    trigger?.addEventListener('pointerdown',e=>{
      if(e.pointerType==='mouse' && e.button!==0)return;
      pointerDown=true;held=false;
      holdTimer=window.setTimeout(openNote,620);
    });
    ['pointerup','pointercancel','pointerleave'].forEach(type=>trigger?.addEventListener(type,()=>{
      pointerDown=false;window.clearTimeout(holdTimer);
    }));
    trigger?.addEventListener('contextmenu',e=>e.preventDefault());
    trigger?.addEventListener('click',e=>{
      e.preventDefault();
      if(held){held=false;return;}
      if(pointerDown)return;
      queue();
    });
  });

  if(noteDialog){
    const text=noteDialog.querySelector('[data-quick-note-text]');
    const close=()=>{noteDialog.close();noteForm=null;};
    noteDialog.querySelectorAll('[data-note-presets] button').forEach(btn=>btn.addEventListener('click',()=>btn.classList.toggle('active')));
    noteDialog.querySelector('[data-quick-note-apply]')?.addEventListener('click',()=>{
      if(!noteForm)return;
      const presets=[...noteDialog.querySelectorAll('[data-note-presets] button.active')].map(x=>x.textContent.trim());
      const custom=(text?.value||'').trim();
      const value=[...presets,custom].filter(Boolean).join(', ').slice(0,500);
      const input=noteForm.querySelector('[data-quick-note]');
      if(input)input.value=value;
      noteDialog.close();
      submitQuick(noteForm);
    });
    noteDialog.querySelector('[data-quick-note-cancel]')?.addEventListener('click',close);
    noteDialog.querySelector('[data-quick-note-close]')?.addEventListener('click',close);
    noteDialog.addEventListener('cancel',e=>{e.preventDefault();close();});
  }

  const switcher=document.querySelector('[data-mobile-switch]');
  if(switcher){
    const setView=view=>{
      document.body.classList.toggle('v4-mobile-ticket',view==='ticket');
      switcher.querySelectorAll('[data-mobile-view]').forEach(b=>b.classList.toggle('active',b.dataset.mobileView===view));
      if(view==='ticket')document.querySelector('.v4-ticket')?.scrollTo({top:0});
    };
    switcher.querySelectorAll('[data-mobile-view]').forEach(btn=>btn.addEventListener('click',()=>setView(btn.dataset.mobileView||'products')));
    document.querySelectorAll('[data-scroll-ticket]').forEach(btn=>btn.addEventListener('click',()=>{
      if(window.matchMedia('(max-width: 900px)').matches)setView('ticket');
    }));
  }
})();

/* CherryHouse POS v4.3 — floor map view */
(()=>{const map=document.querySelector('[data-floor-map]'),grid=document.querySelector('[data-table-grid]');if(!map||!grid)return;const buttons=[...document.querySelectorAll('[data-table-view]')];function setView(view){const isMap=view==='map';map.hidden=!isMap;grid.hidden=isMap;buttons.forEach(b=>b.classList.toggle('active',b.dataset.tableView===view));localStorage.setItem('ch-pos-table-view',view)}buttons.forEach(b=>b.addEventListener('click',()=>setView(b.dataset.tableView)));setView(localStorage.getItem('ch-pos-table-view')||'map');const areaButtons=[...document.querySelectorAll('[data-area]')].filter(x=>x.classList.contains('v4-side-link'));areaButtons.forEach(b=>b.addEventListener('click',()=>{const area=b.dataset.area;document.querySelectorAll('.v4-floor-table').forEach(t=>t.hidden=area!=='all'&&t.dataset.area!==area)}));const search=document.querySelector('[data-table-search]');search?.addEventListener('input',()=>{const q=search.value.trim().toLocaleLowerCase('tr-TR');document.querySelectorAll('.v4-floor-table').forEach(t=>t.hidden=q!==''&&!t.dataset.tableName.includes(q))});})();
