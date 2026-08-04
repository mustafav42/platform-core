(function(){
  document.addEventListener('click',function(e){
    var tab=e.target.closest('[data-editor-tab]');
    if(tab){var drawer=tab.closest('.mw-drawer');drawer.querySelectorAll('[data-editor-tab]').forEach(function(x){x.classList.toggle('active',x===tab)});drawer.querySelectorAll('[data-editor-panel]').forEach(function(x){x.classList.toggle('active',x.dataset.editorPanel===tab.dataset.editorTab)});}
  });
  var filter=document.querySelector('[data-product-filter]');
  if(filter){filter.addEventListener('input',function(){var q=this.value.toLocaleLowerCase('tr');document.querySelectorAll('[data-product-checks] label').forEach(function(el){el.hidden=!el.dataset.search.includes(q)})});}
})();

(function(){
  const csrf=()=>document.querySelector('.mw-editor input[name="csrf_token"]')?.value||'';
  const urlFor=p=>!p?'':(/^https?:\/\//i.test(p)||p.startsWith('/')?p:'/'+p.replace(/^\/+/,''));
  function initMedia(root){
    if(!root||root.dataset.ready)return;root.dataset.ready='1';
    const input=root.querySelector('[data-product-media-input]');
    const preview=root.querySelector('[data-product-media-preview]');
    const image=root.querySelector('[data-product-media-image]');
    const path=root.querySelector('[data-product-media-path]');
    const upload=root.querySelector('[data-product-media-upload]');
    const drop=root.querySelector('[data-product-media-drop]');
    const progress=root.querySelector('[data-product-media-progress]');
    const sync=()=>{const val=(input?.value||'').trim();preview?.classList.toggle('has-image',!!val);if(image){if(val)image.src=urlFor(val);else image.removeAttribute('src')}if(path)path.textContent=val?('Seçili dosya: '+val):''};
    root.querySelectorAll('[data-product-media-library]').forEach(btn=>btn.addEventListener('click',()=>{
      input?.closest('.media-picker-field')?.querySelector('.media-picker-open')?.click();
    }));
    root.querySelector('[data-product-media-remove]')?.addEventListener('click',()=>{if(!input)return;input.value='';input.dispatchEvent(new Event('input',{bubbles:true}));input.dispatchEvent(new Event('change',{bubbles:true}));sync()});
    async function send(file){
      if(!file)return;if(!['image/jpeg','image/png','image/webp'].includes(file.type)){alert('Yalnızca JPG, PNG veya WebP yükleyebilirsiniz.');return}
      if(file.size>32*1024*1024){alert('Dosya boyutu 32 MB sınırını aşıyor.');return}
      progress.hidden=false; const fd=new FormData();fd.append('action','upload');fd.append('csrf_token',csrf());fd.append('folder','Ürünler');fd.append('alt_text','');fd.append('files[]',file);
      try{const res=await fetch('api/media-center.php',{method:'POST',body:fd,credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest'}});const data=await res.json();if(!res.ok||!data.ok||!data.items?.length)throw new Error(data.message||data.errors?.[0]?.message||'Görsel yüklenemedi.');const item=data.items[0];input.value=item.path||item.relative_path||'';input.dispatchEvent(new Event('input',{bubbles:true}));input.dispatchEvent(new Event('change',{bubbles:true}));sync();}
      catch(err){alert(err.message||'Görsel yüklenemedi.')}finally{progress.hidden=true;if(upload)upload.value=''}
    }
    upload?.addEventListener('change',()=>send(upload.files?.[0]));
    ['dragenter','dragover'].forEach(ev=>drop?.addEventListener(ev,e=>{e.preventDefault();drop.classList.add('is-dragging')}));
    ['dragleave','drop'].forEach(ev=>drop?.addEventListener(ev,e=>{e.preventDefault();drop.classList.remove('is-dragging')}));
    drop?.addEventListener('drop',e=>send(e.dataTransfer?.files?.[0]));
    input?.addEventListener('input',sync);input?.addEventListener('change',sync);sync();
  }
  function boot(){document.querySelectorAll('[data-product-media]').forEach(initMedia)}
  document.readyState==='loading'?document.addEventListener('DOMContentLoaded',()=>setTimeout(boot,0)):setTimeout(boot,0);
  new MutationObserver(boot).observe(document.documentElement,{childList:true,subtree:true});
})();
