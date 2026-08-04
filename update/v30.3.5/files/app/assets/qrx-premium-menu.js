(()=>{'use strict';
const groups=[...document.querySelectorAll('[data-category-group]')];
const rows=[...document.querySelectorAll('.product-row')];
const search=document.getElementById('menu-search');
const clear=document.querySelector('.search-clear');
const counter=document.getElementById('search-count');
const empty=document.getElementById('qrx-empty-state');
const modal=document.getElementById('qrx-product-detail');
let lastFocused=null;
const norm=value=>(value||'').toLocaleLowerCase('tr-TR').trim();
const escapeHtml=value=>String(value||'').replace(/[&<>'"]/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[char]));

function setGroup(group,open,{scroll=false}={}){
  const trigger=group.querySelector('.category-trigger');
  const panel=group.querySelector('.category-panel');
  if(!trigger||!panel)return;
  trigger.setAttribute('aria-expanded',String(open));
  group.classList.toggle('is-open',open);
  if(open){
    panel.hidden=false;
    requestAnimationFrame(()=>panel.classList.add('is-visible'));
    if(scroll)setTimeout(()=>group.scrollIntoView({behavior:'smooth',block:'start'}),220);
  }else{
    panel.classList.remove('is-visible');
    const finish=()=>{if(!group.classList.contains('is-open'))panel.hidden=true;};
    panel.addEventListener('transitionend',finish,{once:true});
    setTimeout(finish,360);
  }
}

if(document.body.dataset.firstCategoryOpen==='1'&&groups[0])setGroup(groups[0],true);

groups.forEach(group=>{
  const trigger=group.querySelector('.category-trigger');
  trigger?.addEventListener('click',()=>{
    const willOpen=trigger.getAttribute('aria-expanded')!=='true';
    groups.forEach(other=>{if(other!==group)setGroup(other,false);});
    setGroup(group,willOpen,{scroll:willOpen});
  });
});

function applySearch(){
  const query=norm(search?.value);let visible=0;
  groups.forEach(group=>{
    let groupMatches=0;
    group.querySelectorAll('.product-row').forEach(row=>{
      const match=!query||norm(row.dataset.search).includes(query);
      row.hidden=!match;
      if(match){visible++;groupMatches++;}
    });
    group.hidden=query?groupMatches===0:false;
    if(query&&groupMatches)setGroup(group,true);
    if(!query&&group.classList.contains('is-search-open'))setGroup(group,false);
    group.classList.toggle('is-search-open',Boolean(query&&groupMatches));
  });
  if(clear)clear.hidden=!query;
  if(counter)counter.textContent=query?`${visible} ürün bulundu`:'';
  if(empty){empty.hidden=visible!==0||!query;if(!empty.hidden)document.querySelector('.category-directory')?.appendChild(empty);}
}
search?.addEventListener('input',applySearch);
clear?.addEventListener('click',()=>{search.value='';search.focus();applySearch();});

rows.forEach((row,index)=>{
  row.style.setProperty('--row-delay',`${Math.min(index%10,8)*28}ms`);
  row.addEventListener('click',()=>openDetail(row));
  row.addEventListener('keydown',event=>{if(event.key==='Enter'||event.key===' '){event.preventDefault();openDetail(row);}});
  const img=row.querySelector('img');
  if(img){const done=()=>row.querySelector('.product-thumb')?.classList.add('is-loaded');img.complete?done():img.addEventListener('load',done,{once:true});}
});

function openDetail(row){
  if(!modal)return;
  lastFocused=document.activeElement;
  const name=row.dataset.name||'';
  const image=row.dataset.image||'';
  modal.querySelector('#qrx-detail-title').textContent=name;
  modal.querySelector('.qrx-detail-price').textContent=row.dataset.price||'';
  modal.querySelector('.qrx-detail-category').textContent=row.dataset.category||'';
  modal.querySelector('.qrx-detail-description').textContent=row.dataset.description||'Bu ürün için henüz açıklama eklenmemiş.';
  modal.querySelector('.qrx-detail-badges').innerHTML=[...row.querySelectorAll('.qrx-badge')].map(item=>item.outerHTML).join('');
  const meta=modal.querySelector('.qrx-product-meta');
  const allergenBox=modal.querySelector('.qrx-allergen-meta');
  const allergenList=modal.querySelector('.qrx-allergen-list');
  const nutritionLine=modal.querySelector('.qrx-nutrition-line');
  const allergens=(row.dataset.allergens||'').split('|').map(item=>item.trim()).filter(Boolean);
  const calories=(row.dataset.calories||'').trim();
  const prep=(row.dataset.prepTime||'').trim();
  if(allergenList)allergenList.innerHTML=allergens.map(label=>`<span>${escapeHtml(label)}</span>`).join('');
  if(allergenBox)allergenBox.hidden=allergens.length===0;
  const facts=[];
  if(calories)facts.push(`<span class="qrx-fact"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M13.5 2.8c.4 3.1-1.1 4.4-2.5 5.9-1.2 1.3-2.4 2.7-2.4 5 0 2.2 1.5 4 3.4 4s3.4-1.8 3.4-4c0-1.4-.5-2.6-1.3-3.8 2.8 1.5 4.6 4.1 4.6 7.1 0 4-3 7-6.7 7s-6.7-3-6.7-7c0-4.2 2.4-6.7 4.8-9.2 1.4-1.5 2.9-3 3.4-5z"/></svg><b>${escapeHtml(calories)} kcal</b></span>`);
  if(prep)facts.push(`<span class="qrx-fact"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="13" r="8"/><path d="M12 9v4l2.8 1.8M9 2h6M12 5V2"/></svg><b>${escapeHtml(prep==='60'?'60 dk+':prep+' dk')}</b></span>`);
  if(nutritionLine){nutritionLine.innerHTML=facts.join('');nutritionLine.hidden=facts.length===0;}
  if(meta)meta.hidden=allergens.length===0&&facts.length===0;
  modal.querySelector('.qrx-detail-media').innerHTML=image?`<img src="${image}" alt="${name.replace(/"/g,'&quot;')}">`:`<div class="qrx-detail-placeholder">${name.slice(0,1)}</div>`;
  modal.hidden=false;
  document.documentElement.classList.add('qrx-modal-open');
  document.body.classList.add('qrx-modal-open');
  requestAnimationFrame(()=>modal.classList.add('is-open'));
  modal.querySelector('.qrx-detail-close')?.focus();
}
function closeDetail(){
  if(!modal||modal.hidden)return;
  modal.classList.remove('is-open');
  document.documentElement.classList.remove('qrx-modal-open');
  document.body.classList.remove('qrx-modal-open');
  setTimeout(()=>{modal.hidden=true;lastFocused?.focus?.();},260);
}
modal?.addEventListener('click',event=>{if(event.target.closest('[data-qrx-close]'))closeDetail();});
document.addEventListener('keydown',event=>{if(event.key==='Escape')closeDetail();});
window.addEventListener('pageshow',()=>{document.documentElement.classList.remove('qrx-modal-open');document.body.classList.remove('qrx-modal-open');if(modal){modal.hidden=true;modal.classList.remove('is-open');}});

const progress=document.querySelector('.qrx-progress span');
const mini=document.querySelector('.qrx-mini-header');
const hero=document.querySelector('.hero');
let ticking=false;
function updateChrome(){const max=Math.max(1,document.documentElement.scrollHeight-innerHeight);if(progress)progress.style.transform=`scaleX(${Math.min(1,Math.max(0,scrollY/max))})`;if(mini&&hero)mini.classList.toggle('is-visible',scrollY>Math.max(180,hero.offsetHeight*.55));ticking=false;}
addEventListener('scroll',()=>{if(!ticking){requestAnimationFrame(updateChrome);ticking=true;}},{passive:true});
addEventListener('resize',updateChrome,{passive:true});updateChrome();
})();
