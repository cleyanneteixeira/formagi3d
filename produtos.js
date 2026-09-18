//produtos.js — catálogo completo: busca, categoria (chips) e ordenação, tudo refletido na URL
const params=new URLSearchParams(location.search);
const state={categoria:params.get('categoria')||'Todos',q:params.get('q')||'',ordenar:params.get('ordenar')||'relevancia'};
const chipsEl=document.querySelector('#catalog-chips');
chipsEl.innerHTML=`<button class="chip" data-chip="Todos">Todos</button>${categories.map(c=>`<button class="chip" data-chip="${escapeHtml(c.id)}">${escapeHtml(c.name)}</button>`).join('')}`;
function syncUI(){
  const searchEl=document.querySelector('#search');if(searchEl)searchEl.value=state.q;
  const sortEl=document.querySelector('#sort');if(sortEl)sortEl.value=state.ordenar;
  chipsEl.querySelectorAll('.chip').forEach(c=>c.classList.toggle('active',c.dataset.chip===state.categoria));
  document.querySelectorAll('.nav [data-category]').forEach(b=>b.classList.toggle('active',b.dataset.category===state.categoria));
}
function updateURL(){
  const qs=new URLSearchParams();
  if(state.categoria!=='Todos')qs.set('categoria',state.categoria);
  if(state.q)qs.set('q',state.q);
  if(state.ordenar!=='relevancia')qs.set('ordenar',state.ordenar);
  const str=qs.toString();
  history.replaceState(null,'',location.pathname+(str?'?'+str:''));
}
function render(){
  let list=products.filter(p=>matchesCategory(p,state.categoria)&&normalize(p.name+' '+p.category).includes(normalize(state.q)));
  if(state.ordenar==='menor-preco')list=[...list].sort((a,b)=>a.price-b.price);
  if(state.ordenar==='maior-preco')list=[...list].sort((a,b)=>b.price-a.price);
  if(state.ordenar==='avaliacoes')list=[...list].sort((a,b)=>b.reviews-a.reviews);
  document.querySelector('#product-grid').innerHTML=list.map(productCard).join('');
  document.querySelector('#empty').hidden=list.length>0;
  document.querySelector('#catalog-count').textContent=`${categoryLabels[state.categoria]||state.categoria}${state.q?' · “'+state.q+'”':''} — ${list.length} produto${list.length===1?'':'s'}`;
  syncUI();
  updateURL();
}
chipsEl.addEventListener('click',e=>{const chip=e.target.closest('.chip');if(chip){state.categoria=chip.dataset.chip;state.q='';render()}});
document.querySelector('#sort').addEventListener('change',e=>{state.ordenar=e.target.value;render()});
document.querySelector('#clear-filters').addEventListener('click',()=>{state.categoria='Todos';state.q='';render()});
document.querySelector('#search').addEventListener('input',e=>{state.q=e.target.value.trim();state.categoria='Todos';render()});
document.addEventListener('catalog:nav',e=>{
  if('categoria' in e.detail)state.categoria=e.detail.categoria;
  if('q' in e.detail){state.q=e.detail.q||'';state.categoria='Todos'}
  render();
  document.querySelector('#produtos-header').scrollIntoView({behavior:'smooth'});
});
render();
