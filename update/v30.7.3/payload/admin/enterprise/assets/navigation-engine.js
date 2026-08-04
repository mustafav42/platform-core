(()=>{
 const q=(s,r=document)=>r.querySelector(s),qa=(s,r=document)=>[...r.querySelectorAll(s)];
 const sidebar=q('[data-admin-sidebar]'),backdrop=q('[data-sidebar-backdrop]'),shell=q('[data-admin-shell]');
 const closeMobile=()=>{sidebar?.classList.remove('is-open');backdrop?.classList.remove('is-open')};
 q('[data-sidebar-toggle]')?.addEventListener('click',()=>{sidebar?.classList.add('is-open');backdrop?.classList.add('is-open')});backdrop?.addEventListener('click',closeMobile);
 const collapsed=localStorage.getItem('ch.sidebar.collapsed')==='1';if(collapsed)shell?.classList.add('is-sidebar-collapsed');
 q('[data-sidebar-collapse]')?.addEventListener('click',()=>{shell?.classList.toggle('is-sidebar-collapsed');localStorage.setItem('ch.sidebar.collapsed',shell?.classList.contains('is-sidebar-collapsed')?'1':'0')});
 qa('[data-nav-group]').forEach(group=>{const id=group.dataset.groupId,toggle=q('[data-nav-group-toggle]',group),current=group.classList.contains('is-current'),stored=localStorage.getItem('ch.nav.'+id);if(!current&&stored!=='open')group.classList.add('is-collapsed');toggle?.setAttribute('aria-expanded',String(!group.classList.contains('is-collapsed')));toggle?.addEventListener('click',()=>{group.classList.toggle('is-collapsed');const open=!group.classList.contains('is-collapsed');toggle.setAttribute('aria-expanded',String(open));localStorage.setItem('ch.nav.'+id,open?'open':'closed')})});
 const palette=q('[data-command-palette]'),input=q('[data-command-input]'),items=()=>qa('[data-command-item]',palette).filter(x=>!x.hidden);let active=0;
 const sync=()=>{items().forEach((el,i)=>el.classList.toggle('is-selected',i===active));items()[active]?.scrollIntoView({block:'nearest'})};
 const open=()=>{if(!palette)return;palette.hidden=false;document.body.classList.add('has-command');setTimeout(()=>input?.focus(),20);active=0;sync()};
 const close=()=>{if(!palette)return;palette.hidden=true;document.body.classList.remove('has-command');if(input)input.value='';qa('[data-command-item]',palette).forEach(x=>x.hidden=false);q('[data-command-empty]',palette)?.setAttribute('hidden','');q('[data-command-count]',palette).textContent=qa('[data-command-item]',palette).length+' sonuç'};
 qa('[data-command-open]').forEach(b=>b.addEventListener('click',open));qa('[data-command-close]').forEach(b=>b.addEventListener('click',close));
 input?.addEventListener('input',()=>{const term=input.value.trim().toLocaleLowerCase('tr-TR');let count=0;qa('[data-command-item]',palette).forEach(el=>{el.hidden=term!==''&&!el.dataset.search.includes(term);if(!el.hidden)count++});const empty=q('[data-command-empty]',palette);if(empty)empty.hidden=count!==0;q('[data-command-count]',palette).textContent=count+' sonuç';active=0;sync()});
 document.addEventListener('keydown',e=>{if((e.metaKey||e.ctrlKey)&&e.key.toLowerCase()==='k'){e.preventDefault();palette?.hidden?open():close();return}if(palette?.hidden)return;if(e.key==='Escape'){e.preventDefault();close()}else if(e.key==='ArrowDown'){e.preventDefault();active=Math.min(active+1,items().length-1);sync()}else if(e.key==='ArrowUp'){e.preventDefault();active=Math.max(active-1,0);sync()}else if(e.key==='Enter'&&items()[active]){e.preventDefault();items()[active].click()}});
 document.addEventListener('click',e=>{qa('.ch-quick-menu[open]').forEach(d=>{if(!d.contains(e.target))d.removeAttribute('open')})});
})();
