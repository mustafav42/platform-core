(()=>{
 const cfg=window.CH_IMAGE_STUDIO||{},canvas=document.getElementById('canvas'),ctx=canvas.getContext('2d'),preview=document.getElementById('preview');
 const zoom=document.getElementById('zoom'),zoomOut=document.getElementById('zoomOut'),checks=document.getElementById('checks'),form=document.getElementById('saveForm');
 const img=new Image(); img.crossOrigin='same-origin';
 let scale=1,base=1,x=0,y=0,rotation=0,drag=false,last={x:0,y:0};
 function rotatedSize(){return rotation%180===0?{w:img.naturalWidth,h:img.naturalHeight}:{w:img.naturalHeight,h:img.naturalWidth}}
 function reset(){const s=rotatedSize();base=Math.min(canvas.width/s.w,canvas.height/s.h);scale=base;x=canvas.width/2;y=canvas.height/2;zoom.value='1';draw()}
 function draw(){ctx.clearRect(0,0,canvas.width,canvas.height);ctx.save();ctx.translate(x,y);ctx.rotate(rotation*Math.PI/180);ctx.scale(scale,scale);ctx.drawImage(img,-img.naturalWidth/2,-img.naturalHeight/2);ctx.restore();preview.src=canvas.toDataURL('image/jpeg',.86);zoomOut.value=Math.round((scale/base)*100)+'%';updateChecks()}
 function updateChecks(){const ratio=img.naturalWidth/img.naturalHeight,items=[];items.push([img.naturalWidth>=1200&&img.naturalHeight>=900,'Çözünürlük '+img.naturalWidth+'×'+img.naturalHeight]);items.push([ratio>1.15&&ratio<1.8,'Yatay oran QR için uygun']);items.push([scale/base<1.65,'Aşırı yakınlaştırma yok']);checks.innerHTML=items.map(([ok,t])=>'<div class="cis-check '+(ok?'good':'warn')+'">'+t+'</div>').join('')}
 function point(e){const r=canvas.getBoundingClientRect(),p=e.touches?e.touches[0]:e;return{x:(p.clientX-r.left)*canvas.width/r.width,y:(p.clientY-r.top)*canvas.height/r.height}}
 function start(e){drag=true;last=point(e);e.preventDefault()} function move(e){if(!drag)return;const p=point(e);x+=p.x-last.x;y+=p.y-last.y;last=p;draw();e.preventDefault()} function end(){drag=false}
 canvas.addEventListener('mousedown',start);canvas.addEventListener('mousemove',move);window.addEventListener('mouseup',end);canvas.addEventListener('touchstart',start,{passive:false});canvas.addEventListener('touchmove',move,{passive:false});window.addEventListener('touchend',end);
 zoom.oninput=()=>{scale=base*Number(zoom.value);draw()};document.getElementById('rotateLeft').onclick=()=>{rotation=(rotation-90)%360;reset()};document.getElementById('rotateRight').onclick=()=>{rotation=(rotation+90)%360;reset()};document.getElementById('reset').onclick=()=>{rotation=0;reset()};
 form.addEventListener('submit',e=>{e.preventDefault();canvas.toBlob(blob=>{if(!blob){alert('Görsel hazırlanamadı.');return}const f=new File([blob],'cherryhouse-4x3.jpg',{type:'image/jpeg'}),dt=new DataTransfer();dt.items.add(f);document.getElementById('croppedFile').files=dt.files;form.submit()},'image/jpeg',.88)});
 document.querySelector('.cis-use')?.addEventListener('click',e=>{const b=e.currentTarget,payload={type:'cherryhouse-media-selected',path:b.dataset.path,url:b.dataset.url,alt:''};if(window.opener){window.opener.postMessage(payload,location.origin);window.close()}else history.back()});
 img.onload=reset; img.onerror=()=>alert('Kaynak görsel açılamadı.'); img.src=cfg.source;
})();
