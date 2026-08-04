(() => {
  const overlay = document.getElementById('commandOverlay');
  const input = document.getElementById('commandInput');
  const results = document.getElementById('commandResults');
  const trigger = document.getElementById('commandButton');
  const menuButton = document.getElementById('menuButton');
  const sidebar = document.getElementById('sidebar');
  const commands = [
    ['⌂','Dashboard','İşletme özetine dön','./'],
    ['▣','POS / Kasa','Kasiyer ekranını aç','../cashier/'],
    ['◇','Ürün Yönetimi','Ürün ve kategorileri düzenle','../admin/?page=products'],
    ['▦','Masalar ve Salonlar','Masa düzenini yönet','../admin/?page=tables'],
    ['♙','Personel','PIN ve yetkileri yönet','../admin/?page=staff'],
    ['↗','Satış Raporları','Satış performansını incele','../admin/?page=reports'],
    ['⌁','QR Menü','Dijital menüyü yönet','../admin/qr-menu.php'],
    ['▤','Yazıcı Ayarları','Mutfak yazıcılarını yönet','../admin/print-settings.php'],
    ['✦','Marka Merkezi','Logo, renk ve giriş ekranı','../admin/brand-center.php'],
    ['⚙','Sistem Merkezi','Sistem sağlığını kontrol et','../admin/system-center.php'],
    ['◫','Yedekleme','Veritabanı yedeği al','../admin/?page=maintenance']
  ];
  let filtered = commands;
  let selected = 0;
  function render(query='') {
    const q = query.trim().toLocaleLowerCase('tr-TR');
    filtered = commands.filter(c => (c[1]+' '+c[2]).toLocaleLowerCase('tr-TR').includes(q));
    selected = Math.min(selected, Math.max(0, filtered.length-1));
    results.innerHTML = filtered.map((c,i)=>`<a class="command-result ${i===selected?'selected':''}" href="${c[3]}"><i>${c[0]}</i><div><b>${c[1]}</b><span>${c[2]}</span></div></a>`).join('') || '<div class="empty-state">Sonuç bulunamadı.</div>';
  }
  function open() { overlay.hidden=false; render(); setTimeout(()=>input.focus(),20); }
  function close() { overlay.hidden=true; input.value=''; selected=0; }
  trigger?.addEventListener('click', open);
  overlay?.addEventListener('click', e => { if(e.target===overlay) close(); });
  input?.addEventListener('input', e => render(e.target.value));
  input?.addEventListener('keydown', e => {
    if(e.key==='ArrowDown'){e.preventDefault();selected=Math.min(selected+1,filtered.length-1);render(input.value)}
    if(e.key==='ArrowUp'){e.preventDefault();selected=Math.max(selected-1,0);render(input.value)}
    if(e.key==='Enter'&&filtered[selected]){location.href=filtered[selected][3]}
  });
  document.addEventListener('keydown', e => {
    if((e.metaKey||e.ctrlKey)&&e.key.toLowerCase()==='k'){e.preventDefault();overlay.hidden?open():close()}
    if(e.key==='Escape'&&!overlay.hidden)close();
  });
  menuButton?.addEventListener('click',()=>sidebar.classList.toggle('open'));
  document.addEventListener('click',e=>{if(innerWidth<=900&&sidebar.classList.contains('open')&&!sidebar.contains(e.target)&&e.target!==menuButton)sidebar.classList.remove('open')});
})();
