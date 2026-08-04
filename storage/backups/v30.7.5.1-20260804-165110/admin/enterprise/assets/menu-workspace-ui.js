(function(){
  document.addEventListener('click',function(e){
    var tab=e.target.closest('[data-editor-tab]');
    if(tab){var drawer=tab.closest('.mw-drawer');drawer.querySelectorAll('[data-editor-tab]').forEach(function(x){x.classList.toggle('active',x===tab)});drawer.querySelectorAll('[data-editor-panel]').forEach(function(x){x.classList.toggle('active',x.dataset.editorPanel===tab.dataset.editorTab)});}
  });
  var filter=document.querySelector('[data-product-filter]');
  if(filter){filter.addEventListener('input',function(){var q=this.value.toLocaleLowerCase('tr');document.querySelectorAll('[data-product-checks] label').forEach(function(el){el.hidden=!el.dataset.search.includes(q)})});}
})();
