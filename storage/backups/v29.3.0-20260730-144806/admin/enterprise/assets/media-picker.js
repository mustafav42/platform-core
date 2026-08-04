(()=>{
  const SELECTOR='input[data-media-picker],input[name="image_path"],input[name="media_url"],input[name="qr_hero_image"],input[name="qr_logo_image"],input[name="og_image"]';
  let active=null;
  const rootPath=()=>'/admin/enterprise/media-picker.php';
  function previewFor(input){
    let wrap=input.closest('.media-picker-field');
    if(!wrap){wrap=document.createElement('div');wrap.className='media-picker-field';input.parentNode.insertBefore(wrap,input);wrap.appendChild(input);}
    let tools=wrap.querySelector('.media-picker-tools');
    if(!tools){tools=document.createElement('div');tools.className='media-picker-tools';tools.innerHTML='<button type="button" class="media-picker-open">Medya Kütüphanesinden Seç</button><button type="button" class="media-picker-clear">Temizle</button><div class="media-picker-preview" hidden><img alt=""><span></span></div>';wrap.appendChild(tools);tools.querySelector('.media-picker-open').onclick=()=>{active=input;window.open(rootPath(),'cherryhouseMediaPicker','width=1120,height=760,resizable=yes,scrollbars=yes')};tools.querySelector('.media-picker-clear').onclick=()=>{input.value='';input.dispatchEvent(new Event('input',{bubbles:true}));update(input)};}
    update(input);
  }
  function urlFor(path){if(!path)return'';if(/^https?:\/\//i.test(path)||path.startsWith('/'))return path;return '/'+path.replace(/^\/+/, '');}
  function update(input){const wrap=input.closest('.media-picker-field');if(!wrap)return;const p=wrap.querySelector('.media-picker-preview');const img=p?.querySelector('img');const span=p?.querySelector('span');if(input.value.trim()){p.hidden=false;img.src=urlFor(input.value.trim());span.textContent=input.value.trim();}else if(p){p.hidden=true;img.removeAttribute('src');span.textContent='';}}
  function boot(){document.querySelectorAll(SELECTOR).forEach(i=>{if(i.dataset.mediaReady)return;i.dataset.mediaReady='1';previewFor(i);i.addEventListener('input',()=>update(i));});}
  addEventListener('message',e=>{if(e.origin!==location.origin||e.data?.type!=='cherryhouse-media-selected'||!active)return;active.value=e.data.path||'';active.dispatchEvent(new Event('input',{bubbles:true}));active.dispatchEvent(new Event('change',{bubbles:true}));update(active);active.focus();active=null;});
  document.readyState==='loading'?document.addEventListener('DOMContentLoaded',boot):boot();
  new MutationObserver(boot).observe(document.documentElement,{childList:true,subtree:true});
})();
