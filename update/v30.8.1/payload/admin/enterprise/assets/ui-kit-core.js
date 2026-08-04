(()=>{'use strict';
 const q=(s,r=document)=>r.querySelector(s),qa=(s,r=document)=>[...r.querySelectorAll(s)];
 const overlay=()=>{let el=q('[data-ch-overlay]');if(!el){el=document.createElement('div');el.className='ch-overlay';el.dataset.chOverlay='';el.hidden=true;document.body.append(el)}return el};
 const closeAll=()=>{qa('.ch-modal,.ch-drawer').forEach(x=>x.hidden=true);overlay().hidden=true;document.body.style.overflow=''};
 document.addEventListener('click',e=>{const open=e.target.closest('[data-ch-open]');if(open){const el=q(open.dataset.chOpen);if(el){overlay().hidden=false;el.hidden=false;document.body.style.overflow='hidden'}return}if(e.target.closest('[data-ch-close]')||e.target.matches('[data-ch-overlay]'))closeAll();const toast=e.target.closest('[data-ch-toast]');if(toast)window.CHUI.toast(toast.dataset.chToast||'İşlem tamamlandı','success')});
 document.addEventListener('keydown',e=>{if(e.key==='Escape')closeAll()});
 window.CHUI={closeAll,toast(message,type='success'){let stack=q('.ch-toast-stack');if(!stack){stack=document.createElement('div');stack.className='ch-toast-stack';document.body.append(stack)}const t=document.createElement('div');t.className='ch-toast ch-toast--'+type;t.innerHTML='<strong>'+ (type==='success'?'✓':'!') +'</strong><div><b>'+message+'</b><div style="font-size:12px;color:var(--ch-muted);margin-top:3px">CherryHouse Control Center</div></div>';stack.append(t);setTimeout(()=>t.remove(),3200)}};
})();
