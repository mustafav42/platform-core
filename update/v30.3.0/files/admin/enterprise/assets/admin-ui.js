(()=>{
'use strict';
const root=document.documentElement,body=document.body;
const q=(s,c=document)=>c.querySelector(s),qa=(s,c=document)=>[...c.querySelectorAll(s)];
const store={get:(k,d='')=>{try{return localStorage.getItem(k)??d}catch{return d}},set:(k,v)=>{try{localStorage.setItem(k,v)}catch{}}};
const toast=(message)=>{const stack=q('[data-toast-stack]');if(!stack)return;const el=document.createElement('div');el.className='ent-toast';el.textContent=message;stack.append(el);setTimeout(()=>el.remove(),2600)};
const applyTheme=(theme)=>{root.dataset.entTheme=theme;store.set('cherryhouse-admin-theme',theme)};
applyTheme(store.get('cherryhouse-admin-theme',matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light'));
qa('[data-theme-toggle]').forEach(btn=>btn.addEventListener('click',()=>{const next=root.dataset.entTheme==='dark'?'light':'dark';applyTheme(next);toast(next==='dark'?'Koyu tema açıldı':'Açık tema açıldı')}));
if(store.get('cherryhouse-sidebar-collapsed')==='1')body.classList.add('sidebar-collapsed');
q('[data-sidebar-collapse]')?.addEventListener('click',()=>{body.classList.toggle('sidebar-collapsed');store.set('cherryhouse-sidebar-collapsed',body.classList.contains('sidebar-collapsed')?'1':'0')});
const closeSidebar=()=>body.classList.remove('sidebar-open');q('[data-sidebar-toggle]')?.addEventListener('click',()=>body.classList.toggle('sidebar-open'));q('[data-sidebar-backdrop]')?.addEventListener('click',closeSidebar);qa('[data-admin-sidebar] a').forEach(a=>a.addEventListener('click',closeSidebar));
const favorites=new Set(JSON.parse(store.get('cherryhouse-admin-favorites','[]')));qa('[data-favorite]').forEach(btn=>{const id=btn.dataset.favorite;const paint=()=>{btn.classList.toggle('is-favorite',favorites.has(id));btn.textContent=favorites.has(id)?'★':'☆'};paint();btn.addEventListener('click',e=>{e.preventDefault();e.stopPropagation();favorites.has(id)?favorites.delete(id):favorites.add(id);store.set('cherryhouse-admin-favorites',JSON.stringify([...favorites]));paint();toast(favorites.has(id)?'Favorilere eklendi':'Favorilerden kaldırıldı')})});
const palette=q('[data-command-palette]'),input=q('[data-command-input]'),items=qa('[data-command-item]');let active=0;
const paintActive=()=>items.forEach((el,i)=>el.classList.toggle('is-active',i===active&&!el.hidden));
const filter=()=>{const term=(input?.value||'').toLocaleLowerCase('tr-TR').trim();let first=-1;items.forEach((el,i)=>{el.hidden=term!==''&&!el.dataset.search.includes(term);if(!el.hidden&&first<0)first=i});active=Math.max(0,first);paintActive()};
const openPalette=()=>{if(!palette)return;palette.hidden=false;setTimeout(()=>input?.focus(),10);filter()};const closePalette=()=>{if(!palette)return;palette.hidden=true;if(input)input.value=''};
qa('[data-command-open]').forEach(btn=>btn.addEventListener('click',openPalette));q('[data-command-close]')?.addEventListener('click',closePalette);input?.addEventListener('input',filter);
document.addEventListener('keydown',e=>{if((e.metaKey||e.ctrlKey)&&e.key.toLowerCase()==='k'){e.preventDefault();palette?.hidden?openPalette():closePalette();return}if(!palette||palette.hidden)return;if(e.key==='Escape'){closePalette();return}const visible=items.filter(x=>!x.hidden);if(e.key==='ArrowDown'||e.key==='ArrowUp'){e.preventDefault();const current=visible.indexOf(items[active]);const next=e.key==='ArrowDown'?(current+1)%visible.length:(current-1+visible.length)%visible.length;active=items.indexOf(visible[next]);paintActive();visible[next]?.scrollIntoView({block:'nearest'})}if(e.key==='Enter'){const el=items[active];if(el&&!el.hidden)el.click()}});
qa('[data-copy]').forEach(btn=>btn.addEventListener('click',async()=>{try{await navigator.clipboard.writeText(btn.dataset.copy||'');toast('Görsel yolu kopyalandı')}catch{toast('Kopyalama başarısız')}}));
})();

// CherryHouse v30.3 Aurora — live search, recent pages and dashboard sync.
(()=>{
'use strict';
const q=(s,c=document)=>c.querySelector(s), qa=(s,c=document)=>[...c.querySelectorAll(s)];
const input=q('[data-command-input]'), live=q('[data-command-live]'), liveLabel=q('.command-live-label');
let timer=null, controller=null;
const esc=s=>String(s??'').replace(/[&<>'"]/g,ch=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[ch]));
input?.addEventListener('input',()=>{
 clearTimeout(timer); const term=input.value.trim();
 if(term.length<2){if(live)live.innerHTML='';if(liveLabel)liveLabel.hidden=true;return;}
 timer=setTimeout(async()=>{
   controller?.abort(); controller=new AbortController();
   try{const r=await fetch('api/search.php?q='+encodeURIComponent(term),{signal:controller.signal,headers:{'X-Requested-With':'XMLHttpRequest'}});const j=await r.json();
     if(!live)return; const items=j.results||[]; liveLabel.hidden=items.length===0;
     live.innerHTML=items.map(x=>`<a href="${esc(x.url)}" class="command-live-item"><span>${esc(x.icon)}</span><div><strong>${esc(x.title)}</strong><small>${esc(x.type)} · ${esc(x.subtitle)}</small></div><b>↵</b></a>`).join('');
   }catch(e){if(e.name!=='AbortError'&&live)live.innerHTML='';}
 },220);
});
const key='cherryhouse-recent-pages-v303';
try{
 const title=q('.page-heading h1')?.textContent?.trim(); const url=location.pathname+location.search;
 if(title&&!/giriş/i.test(title)){let rows=JSON.parse(localStorage.getItem(key)||'[]').filter(x=>x.url!==url);rows.unshift({title,url,time:Date.now()});rows=rows.slice(0,8);localStorage.setItem(key,JSON.stringify(rows));}
 const box=q('[data-recent-pages]'); if(box){const rows=JSON.parse(localStorage.getItem(key)||'[]');box.innerHTML=rows.length?rows.map(x=>`<a href="${esc(x.url)}"><span>↗</span><div><strong>${esc(x.title)}</strong><small>${new Date(x.time).toLocaleString('tr-TR',{day:'2-digit',month:'2-digit',hour:'2-digit',minute:'2-digit'})}</small></div></a>`).join(''):'<div class="empty-panel">Ziyaret ettiğiniz sayfalar burada görünecek.</div>';}
}catch{}
const sync=async()=>{const root=q('[data-live-dashboard]');if(!root)return;try{const r=await fetch('api/dashboard.php',{headers:{'X-Requested-With':'XMLHttpRequest'}});const j=await r.json();if(!j.ok)return;Object.entries(j.data||{}).forEach(([k,v])=>{const el=q(`[data-live="${k}"]`,root);if(!el)return;el.textContent=k==='today_sales'?Number(v).toLocaleString('tr-TR',{minimumFractionDigits:2,maximumFractionDigits:2})+' ₺':v;});}catch{}};
sync();setInterval(sync,12000);
qa('[data-notification-id]').forEach(a=>a.addEventListener('click',()=>{const fd=new FormData();fd.append('id',a.dataset.notificationId||'0');navigator.sendBeacon?.('api/notifications.php',fd)}));
})();
