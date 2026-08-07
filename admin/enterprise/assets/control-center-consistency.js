(()=>{'use strict';
const primary=/^(kaydet|değişiklikleri kaydet|oluştur|ekle|yeni|uygula|başlat|etkinleştir|güncelle|devam|tamamla)/i;
const danger=/^(sil|kaldır|iptal et|pasife al|temizle|çıkış)/i;
const secondary=/^(vazgeç|geri|kapat|önizle|düzenle|aç|seç|filtrele|dışa aktar)/i;
function normalize(root=document){root.querySelectorAll('.ch-content a').forEach(a=>{if(a.closest('.ch-nav,.ch-breadcrumb,.ch-quick-panel,.command-results,.ch-profile,.mw-product-main,.mw-group-list'))return;if(a.classList.contains('ch-btn')||a.classList.contains('ch-icon-action'))return;const t=(a.textContent||'').trim();if(!t||t.length>42)return;if(primary.test(t))a.dataset.autoButton='primary';else if(danger.test(t))a.dataset.autoButton='danger';else if(secondary.test(t))a.dataset.autoButton='secondary';});}
normalize();new MutationObserver(m=>{for(const x of m)for(const n of x.addedNodes)if(n.nodeType===1)normalize(n)}).observe(document.body,{childList:true,subtree:true});
const frame=document.querySelector('[data-compat-frame]');if(frame){frame.addEventListener('load',()=>{document.querySelector('[data-compat-loading]')?.classList.add('is-hidden');try{const d=frame.contentDocument;if(!d)return;const style=d.createElement('style');style.textContent=`html,body{background:#f6f4f2!important;color:#211d1a!important}
body{margin:0!important;padding:0!important;font-family:Inter,system-ui,-apple-system,"Segoe UI",sans-serif!important}
.layout,.shell,.enterprise-shell,.app-shell{display:block!important;grid-template-columns:1fr!important;min-height:auto!important;background:transparent!important}
.layout>aside,.shell>aside,aside.sidebar,.sidebar,.admin-sidebar,.enterprise-sidebar,.main-sidebar,.top-sidebar,.legacy-sidebar{display:none!important}
.main-shell,.enterprise-main,.shell>main,main{display:block!important;width:100%!important;max-width:none!important;min-width:0!important;margin:0!important;padding:20px 22px 36px!important;background:transparent!important}
.main-shell>.topbar,.enterprise-topbar,.topbar{display:none!important}
a.back,.bd-back{display:none!important}
.card,.panel,.module,.architecture,.summary article{border-color:#e7e1dc!important;box-shadow:0 4px 16px rgba(49,34,27,.05)!important}
button,input[type=submit],input[type=button],a.button,.btn{border-radius:12px!important;min-height:42px!important;padding:10px 15px!important;font-weight:800!important;font-family:inherit!important}
button:not(.toggle):not([disabled]),input[type=submit]{background:#92263a!important;color:#fff!important;border:0!important}
button:not(.toggle):not([disabled]):hover,input[type=submit]:hover{background:#7d1f31!important}
button.danger,.danger{background:#fff0f2!important;color:#b53e4d!important;border:1px solid #ffd9de!important}
input,select,textarea{border-radius:11px!important;border-color:#e7e1dc!important;background:#fff!important;color:#211d1a!important}
input:focus,select:focus,textarea:focus{outline:0!important;border-color:#bb7180!important;box-shadow:0 0 0 3px rgba(146,38,58,.12)!important}
.eyebrow{color:#92263a!important}
.toggle{position:relative!important;width:48px!important;height:28px!important;padding:3px!important;border:0!important;border-radius:999px!important;background:#d7d1cd!important}
.toggle span{display:block!important;width:22px!important;height:22px!important;border-radius:50%!important;background:#fff!important;box-shadow:0 2px 7px rgba(33,29,26,.18)!important;transform:translateX(0)!important;transition:transform .18s ease!important}
.is-on .toggle{background:#218a61!important}
.is-on .toggle span{transform:translateX(20px)!important}
@media(max-width:760px){.main-shell,.enterprise-main,.shell>main,main{padding:14px 12px 28px!important}}`;d.head.appendChild(style);d.querySelectorAll('a').forEach(a=>{const t=(a.textContent||'').trim();if(primary.test(t)||secondary.test(t)||danger.test(t)){a.style.display='inline-flex';a.style.alignItems='center';a.style.justifyContent='center';a.style.gap='8px';a.style.minHeight='42px';a.style.padding='10px 15px';a.style.borderRadius='12px';a.style.textDecoration='none';a.style.fontWeight='800';a.style.border='1px solid #e4e7ec';if(primary.test(t)){a.style.background='#92263a';a.style.color='#fff';a.style.borderColor='transparent'}else if(danger.test(t)){a.style.background='#fff3f2';a.style.color='#d92d20';a.style.borderColor='#ffd5d2'}else{a.style.background='#fff';a.style.color='#344054'}}});}catch(e){console.warn('Control Center compatibility styling skipped',e);}});}
})();
