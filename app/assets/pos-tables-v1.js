(()=>{
  const norm=(v)=>(v||'').toLocaleLowerCase('tr-TR').trim();
  const apply=()=>{
    const query=norm(document.querySelector('[data-table-search]')?.value);
    const area=document.querySelector('[data-area-filter].active')?.dataset.areaFilter||'all';
    document.querySelectorAll('[data-table-card]').forEach(card=>{
      const name=norm(card.dataset.tableName);
      const cardArea=String(card.dataset.areaId||'');
      card.hidden=(query!==''&&!name.includes(query))||(area!=='all'&&area!==cardArea);
    });
  };
  document.addEventListener('input',e=>{if(e.target.matches('[data-table-search]'))apply()});
  document.addEventListener('click',e=>{
    const btn=e.target.closest('[data-area-filter]');
    if(!btn)return;
    document.querySelectorAll('[data-area-filter]').forEach(x=>x.classList.remove('active'));
    btn.classList.add('active'); apply();
  });
  const tick=()=>document.querySelectorAll('[data-opened-at]').forEach(el=>{
    const started=Date.parse(el.dataset.openedAt.replace(' ','T'));
    if(!Number.isFinite(started))return;
    const mins=Math.max(0,Math.floor((Date.now()-started)/60000));
    const h=Math.floor(mins/60),m=mins%60;
    el.textContent=h?`${h} sa ${m} dk`:`${m} dk`;
  });
  tick(); setInterval(tick,30000);
})();
