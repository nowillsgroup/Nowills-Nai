(function(){
  const $ = (s, r=document) => r.querySelector(s);
  const sidebar = $('#sidebar'), overlay = $('#sidebarOverlay'), menu = $('#menuToggle');
  if(menu){ menu.addEventListener('click', ()=>{ sidebar.classList.toggle('open'); overlay.classList.toggle('show'); }); }
  if(overlay){ overlay.addEventListener('click', ()=>{ sidebar.classList.remove('open'); overlay.classList.remove('show'); }); }
  const userBtn = $('#userMenuBtn'), userMenu = $('#userMenu');
  if(userBtn){ userBtn.addEventListener('click', e=>{ e.stopPropagation(); userMenu.classList.toggle('open'); }); }
  document.addEventListener('click', ()=>{ if(userMenu) userMenu.classList.remove('open'); });

  const modal = $('#searchModal'), input = $('#globalSearchInput'), results = $('#searchResults');
  function openSearch(){ if(!modal) return; modal.hidden=false; setTimeout(()=>input&&input.focus(),30); }
  function closeSearch(){ if(!modal) return; modal.hidden=true; }
  const trigger = $('#searchTrigger'); if(trigger) trigger.addEventListener('click', openSearch);
  const close = $('#searchClose'); if(close) close.addEventListener('click', closeSearch);
  if(modal) modal.addEventListener('click', e=>{ if(e.target===modal) closeSearch(); });
  document.addEventListener('keydown', e=>{
    if((e.metaKey||e.ctrlKey)&&e.key.toLowerCase()==='k'){ e.preventDefault(); openSearch(); }
    if(e.key==='Escape'){ closeSearch(); if(userMenu) userMenu.classList.remove('open'); }
  });
  let timer;
  if(input){ input.addEventListener('input', ()=>{ clearTimeout(timer); const q=input.value.trim(); if(q.length<2){ results.innerHTML='<div class="search-empty">Type at least two characters to search the workspace.</div>'; return; } timer=setTimeout(async()=>{ try{ const res=await fetch('/search?q='+encodeURIComponent(q),{headers:{'Accept':'application/json'}}); const data=await res.json(); if(!data.results.length){ results.innerHTML='<div class="search-empty">No results found.</div>'; return;} results.innerHTML=data.results.map(r=>`<a class="search-result" href="${r.url}"><div><span class="type">${r.type}</span><strong>${escapeHtml(r.title)}</strong><span>${escapeHtml(r.subtitle||'')}</span></div></a>`).join(''); }catch(e){ results.innerHTML='<div class="search-empty">Search unavailable.</div>'; } },220); }); }
  function escapeHtml(s){ return String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

  document.querySelectorAll('[data-confirm]').forEach(form=>{ form.addEventListener('submit', e=>{ if(!confirm(form.dataset.confirm)) e.preventDefault(); }); });
  document.querySelectorAll('[data-prompt]').forEach(btn=>{ btn.addEventListener('click', ()=>{ const ta=$('#arenaPrompt')||$('#arenaFirstPrompt'); if(ta){ ta.value=btn.dataset.prompt; ta.focus(); } }); });
  document.querySelectorAll('form[data-autosubmit] select').forEach(sel=>{ sel.addEventListener('change', ()=>sel.closest('form').submit()); });
})();
