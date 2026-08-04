(()=>{
 const root=document.documentElement;
 const saved=localStorage.getItem('restaurant-admin-theme');
 if(saved==='dark') root.dataset.theme='dark';
 const sync=()=>{const b=document.querySelector('[data-theme-toggle]');if(b)b.textContent=root.dataset.theme==='dark'?'☀':'◐'};
 document.addEventListener('DOMContentLoaded',()=>{
   sync();
   document.querySelector('[data-theme-toggle]')?.addEventListener('click',()=>{root.dataset.theme=root.dataset.theme==='dark'?'light':'dark';localStorage.setItem('restaurant-admin-theme',root.dataset.theme);sync();});
   document.querySelector('[data-nav-toggle]')?.addEventListener('click',()=>document.querySelector('.sidebar')?.classList.toggle('open'));
   document.addEventListener('click',e=>{const s=document.querySelector('.sidebar');if(window.innerWidth<=800&&s?.classList.contains('open')&&!s.contains(e.target)&&!e.target.closest('[data-nav-toggle]'))s.classList.remove('open')});
 });
})();

// v3.1.1 Smart table drag-and-drop planner
(()=>{
 document.addEventListener('DOMContentLoaded',()=>{
  const plans=[...document.querySelectorAll('[data-floor-plan]')];
  if(!plans.length)return;
  const snap=n=>Math.max(0,Math.round(n/10)*10);
  plans.forEach(plan=>{
   plan.querySelectorAll('.smart-table').forEach(table=>{
    let drag=null;
    table.addEventListener('pointerdown',e=>{
     if(e.button!==0)return;
     const tr=table.getBoundingClientRect(),pr=plan.getBoundingClientRect();
     drag={dx:e.clientX-tr.left,dy:e.clientY-tr.top,pr};
     table.classList.add('dragging');table.setPointerCapture(e.pointerId);e.preventDefault();
    });
    table.addEventListener('pointermove',e=>{
     if(!drag)return;
     const maxX=Math.max(0,plan.scrollWidth-table.offsetWidth),maxY=Math.max(0,plan.scrollHeight-table.offsetHeight);
     const x=Math.min(maxX,snap(e.clientX-drag.pr.left+plan.scrollLeft-drag.dx));
     const y=Math.min(maxY,snap(e.clientY-drag.pr.top+plan.scrollTop-drag.dy));
     table.style.left=x+'px';table.style.top=y+'px';table.dataset.x=x;table.dataset.y=y;
    });
    const stop=()=>{drag=null;table.classList.remove('dragging')};
    table.addEventListener('pointerup',stop);table.addEventListener('pointercancel',stop);
   });
  });
  document.querySelectorAll('[data-save-layout]').forEach(btn=>btn.addEventListener('click',()=>{
   const rows=[...document.querySelectorAll('.smart-table')].map(t=>({id:Number(t.dataset.tableId),x:Number(t.dataset.x||0),y:Number(t.dataset.y||0)}));
   const input=document.getElementById('layout-json'),form=document.getElementById('layout-save-form');
   if(input&&form){input.value=JSON.stringify(rows);form.submit();}
  }));
 });
})();
