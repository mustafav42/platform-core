(()=>{
 const root=document.querySelector('[data-pay-screen]');
 if(!root)return;
 const form=document.getElementById('unifiedPaymentForm');
 const display=document.getElementById('uAmountDisplay');
 const mode=document.getElementById('uPaymentMode');
 const selectionInput=document.getElementById('uProductSelection');
 const submit=form.querySelector('[data-u-submit]');
 const modeLabel=root.querySelector('[data-mode-label]');
 const selectionLabel=root.querySelector('[data-selection-label]');
 const remaining=Number(window.CH_CASHIER?.remaining||0);
 let selected={};
 let target=remaining;
 let amountBuffer='';
 let amountEntryActive=false;
 const distributed={cash:0,card:0,meal_card:0,transfer:0};
 const fmt=v=>new Intl.NumberFormat('tr-TR',{minimumFractionDigits:2,maximumFractionDigits:2}).format(v)+' ₺';
 const parseBuffer=()=>{
   if(!amountBuffer)return 0;
   const normalized=amountBuffer.replace(',','.');
   const value=Number(normalized);
   return Number.isFinite(value)?value:0;
 };
 const productTotal=()=>Object.entries(selected).reduce((total,[id,quantity])=>{
   const item=root.querySelector(`[data-u-item][data-id="${id}"]`);
   return total+(item?Number(item.dataset.price)*quantity:0);
 },0);
 function syncMethods(){
   let sum=0;
   Object.entries(distributed).forEach(([key,value])=>{
     sum+=value;
     const input=form.querySelector(`[name="${key}"]`);
     if(input)input.value=value.toFixed(2);
     const badge=root.querySelector(`[data-method-amount="${key}"]`);
     if(badge)badge.textContent=fmt(value);
   });
   const total=root.querySelector('[data-distribution-total]');
   if(total)total.textContent=fmt(sum);
   submit.disabled=target<=0||Math.abs(sum-target)>.009;
 }
 function resetMethods(){
   Object.keys(distributed).forEach(key=>distributed[key]=0);
   root.querySelectorAll('[data-u-method]').forEach(button=>button.classList.remove('active'));
   syncMethods();
 }
 function renderTarget(){
   display.value=fmt(target);
   display.textContent=fmt(target);
 }
 function renderMode(){
   const count=Object.values(selected).reduce((sum,value)=>sum+Number(value||0),0);
   if(mode.value==='products'){
     if(modeLabel)modeLabel.textContent='Ürün bazlı';
     if(selectionLabel)selectionLabel.textContent=count+' adet ürün seçildi';
   }else if(mode.value==='amount'){
     if(modeLabel)modeLabel.textContent='Serbest tutar';
     if(selectionLabel)selectionLabel.textContent='Girilen tutar tahsil edilecek';
   }else{
     if(modeLabel)modeLabel.textContent='Tüm hesap';
     if(selectionLabel)selectionLabel.textContent='Kalan bakiyenin tamamı';
   }
 }
 function clearProductSelection(){
   selected={};
   selectionInput.value='{}';
   root.querySelectorAll('[data-u-item]').forEach(item=>{
     item.classList.remove('selected');
     const picked=item.querySelector('[data-picked]');
     if(picked)picked.textContent='0';
   });
 }
 function setAmountTarget(value,{keepBuffer=true}={}){
   mode.value='amount';
   clearProductSelection();
   target=Math.max(0,Math.min(remaining,Number(value)||0));
   if(!keepBuffer)amountBuffer='';
   renderTarget();
   renderMode();
   resetMethods();
 }
 function syncSelection(){
   selectionInput.value=JSON.stringify(selected);
   const hasSelection=Object.keys(selected).length>0;
   if(hasSelection){
     mode.value='products';
     target=productTotal();
   }else{
     mode.value='all';
     target=remaining;
   }
   amountBuffer='';
   amountEntryActive=false;
   renderTarget();
   renderMode();
   root.querySelectorAll('[data-u-item]').forEach(item=>{
     const quantity=selected[item.dataset.id]||0;
     item.classList.toggle('selected',quantity>0);
     const picked=item.querySelector('[data-picked]');
     if(picked)picked.textContent=String(quantity);
   });
   resetMethods();
 }
 function applyKey(key){
   if(!amountEntryActive){
     amountEntryActive=true;
     amountBuffer='';
   }
   if(key==='⌫'){
     amountBuffer=amountBuffer.slice(0,-1);
   }else if(key===','){
     if(!amountBuffer.includes(','))amountBuffer=(amountBuffer||'0')+',';
   }else if(/^\d$/.test(key)){
     const commaIndex=amountBuffer.indexOf(',');
     if(commaIndex!==-1&&amountBuffer.length-commaIndex-1>=2)return;
     const integerPart=(commaIndex===-1?amountBuffer:amountBuffer.slice(0,commaIndex)).replace(/^0+(?=\d)/,'');
     if(commaIndex===-1&&integerPart.length>=9)return;
     amountBuffer+=key;
   }
   setAmountTarget(parseBuffer());
 }
 root.querySelectorAll('[data-u-item]').forEach(item=>item.addEventListener('click',()=>{
   const id=item.dataset.id;
   const max=Number(item.dataset.max);
   const current=selected[id]||0;
   if(current<max)selected[id]=current+1;
   else delete selected[id];
   syncSelection();
 }));
 root.querySelector('[data-select-all]')?.addEventListener('click',()=>{
   selected={};
   root.querySelectorAll('[data-u-item]').forEach(item=>selected[item.dataset.id]=Number(item.dataset.max));
   syncSelection();
 });
 root.querySelector('[data-clear-selection]')?.addEventListener('click',()=>{
   selected={};
   syncSelection();
 });
 root.querySelectorAll('[data-u-key]').forEach(button=>button.addEventListener('click',()=>applyKey(button.dataset.uKey)));
 root.querySelectorAll('[data-add-amount]').forEach(button=>button.addEventListener('click',()=>{
   if(!amountEntryActive){amountEntryActive=true;amountBuffer='';}
   const next=Math.min(remaining,parseBuffer()+Number(button.dataset.addAmount||0));
   amountBuffer=next.toFixed(2).replace('.',',').replace(/,00$/,'');
   setAmountTarget(next);
 }));
 root.querySelector('[data-free-amount]')?.addEventListener('click',()=>{
   amountEntryActive=true;
   amountBuffer='';
   setAmountTarget(0);
 });
 root.querySelector('[data-round]')?.addEventListener('click',()=>{
   amountEntryActive=true;
   const rounded=Math.min(remaining,Math.round(target));
   amountBuffer=String(rounded).replace('.',',');
   setAmountTarget(rounded);
 });
 root.querySelector('[data-full-remaining]')?.addEventListener('click',()=>{
   amountEntryActive=false;
   amountBuffer='';
   selected={};
   mode.value='all';
   target=remaining;
   clearProductSelection();
   renderTarget();
   renderMode();
   resetMethods();
 });
 root.querySelector('[data-reset-methods]')?.addEventListener('click',resetMethods);
 root.querySelectorAll('[data-u-method]').forEach(button=>button.addEventListener('click',()=>{
   if(target<=0)return;
   const used=Object.values(distributed).reduce((sum,value)=>sum+value,0);
   const left=Math.max(0,target-used);
   const key=button.dataset.uMethod;
   if(left>.009)distributed[key]+=left;
   else{
     Object.keys(distributed).forEach(method=>distributed[method]=0);
     distributed[key]=target;
   }
   root.querySelectorAll('[data-u-method]').forEach(item=>item.classList.toggle('active',item===button));
   syncMethods();
 }));
 const drawer=root.querySelector('[data-product-drawer]');
 const mask=root.querySelector('[data-drawer-mask]');
 const openDrawer=()=>{drawer?.classList.add('open');mask?.classList.add('open')};
 const closeDrawer=()=>{drawer?.classList.remove('open');mask?.classList.remove('open')};
 root.querySelector('[data-add-products]')?.addEventListener('click',openDrawer);
 root.querySelector('[data-close-products]')?.addEventListener('click',closeDrawer);
 mask?.addEventListener('click',closeDrawer);
 root.querySelectorAll('[data-drawer-category]').forEach(button=>button.addEventListener('click',event=>{
   event.preventDefault();
   event.stopPropagation();
   const id=button.dataset.drawerCategory;
   root.querySelectorAll('[data-drawer-category]').forEach(item=>item.classList.toggle('active',item===button));
   root.querySelectorAll('[data-drawer-product]').forEach(product=>{product.hidden=product.dataset.categoryId!==id});
 }));
 if(drawer?.dataset.openOnLoad==='1')openDrawer();
 syncSelection();
})();
