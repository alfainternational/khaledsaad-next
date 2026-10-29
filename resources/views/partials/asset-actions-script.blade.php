{{-- أفعال بطاقة الأصل خارج الدرس (نسخ، تنزيل Markdown، حدث PDF، نسخ رابط المشاركة، تأكيد الحذف). يُضمَّن مرة واحدة في الصفحة. --}}
<script nonce="{{ $cspNonce }}">
(function(){
  function ev(name,meta){window.atharEvent&&window.atharEvent(name,meta||{})}
  document.querySelectorAll('[data-asset]').forEach(function(card){
    var md=card.querySelector('[data-asset-md]');if(!md)return;
    var copy=card.querySelector('[data-action="copy"]');copy&&copy.addEventListener('click',function(){var text=md.value;(navigator.clipboard?navigator.clipboard.writeText(text):Promise.reject()).catch(function(){md.classList.remove('sr-only');md.select();document.execCommand('copy');md.classList.add('sr-only')}).finally(function(){copy.textContent='✓ نُسخ';setTimeout(function(){copy.textContent='نسخ'},1500);ev('asset.copied',{part:'tool'})})});
    var dl=card.querySelector('[data-action="download-md"]');dl&&dl.addEventListener('click',function(){var blob=new Blob([md.value],{type:'text/markdown;charset=utf-8'}),a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download=(dl.dataset.filename||'asset')+'.md';document.body.appendChild(a);a.click();a.remove();ev('asset.downloaded',{part:'tool',format:'md'})});
    var pdf=card.querySelector('[data-action="download-pdf"]');pdf&&pdf.addEventListener('click',function(){ev('asset.downloaded',{part:'tool',format:'pdf'})});
  });
  document.querySelectorAll('[data-copy-target]').forEach(function(btn){btn.addEventListener('click',function(){var el=document.querySelector(btn.dataset.copyTarget);if(!el)return;var text=el.value!==undefined?el.value:el.textContent;(navigator.clipboard?navigator.clipboard.writeText(text):Promise.reject()).catch(function(){if(el.select){el.select();document.execCommand('copy')}}).finally(function(){var o=btn.textContent;btn.textContent='✓ نُسخ';setTimeout(function(){btn.textContent=o},1500)})})});
  document.querySelectorAll('form[data-confirm]').forEach(function(f){f.addEventListener('submit',function(e){if(!window.confirm(f.dataset.confirm))e.preventDefault()})});
  document.querySelectorAll('[data-fix]').forEach(function(a){a.addEventListener('click',function(){var el=document.getElementById('f-'+a.dataset.fix);if(el){setTimeout(function(){el.focus()},300)}})});
})();
</script>
