//produto.js — página de detalhe de um produto (?id=), com relacionados
const id=Number(new URLSearchParams(location.search).get('id'));
const p=findProduct(id);
const pdp=document.querySelector('#pdp');
const breadcrumb=document.querySelector('#breadcrumb');
const relatedSection=document.querySelector('#related');
if(!p){
  breadcrumb.innerHTML='<a href="index.html">Início</a><span aria-hidden="true">/</span><b>Produto não encontrado</b>';
  pdp.innerHTML='<div class="empty-state"><p>Não encontramos esse produto. Ele pode ter sido removido do catálogo de demonstração.</p><a class="button" href="produtos.html">Ver todos os produtos</a></div>';
  relatedSection.innerHTML='';
}else{
  document.title=`${p.name} | Formagi3D`;
  breadcrumb.innerHTML=`<a href="index.html">Início</a><span aria-hidden="true">/</span><a href="produtos.html?categoria=${encodeURIComponent(p.category)}">${escapeHtml(categoryLabels[p.category]||p.category)}</a><span aria-hidden="true">/</span><b>${escapeHtml(p.name)}</b>`;
  const specsHtml=Object.entries(p.specs||{}).map(([k,v])=>`<div><span>${escapeHtml(k)}</span><b>${escapeHtml(v)}</b></div>`).join('');
  const gallery=(p.images||[]).filter(safeAsset);
  pdp.innerHTML=`
<div class="pdp-media">${image(p,'pdp-image')}${gallery.length>1?`<div class="pdp-thumbs">${gallery.map((src,i)=>`<button type="button" data-gallery="${i}" aria-label="Ver foto ${i+1}"><img src="${src}" alt=""></button>`).join('')}</div>`:''}</div>
<div class="pdp-info">
${p.badge?`<span class="badge" style="background:${/^#[0-9a-f]{6}$/i.test(p.color)?p.color:'#ff008a'}">${escapeHtml(p.badge)}</span>`:''}
<h1>${escapeHtml(p.name)}</h1>
<p class="price">${money(p.price)}</p>
<p class="pdp-desc">${escapeHtml(p.desc)}</p>
<div class="pdp-specs">${specsHtml}</div>
<div class="pdp-qty"><span>Quantidade</span><div class="qty-stepper"><button id="pdp-minus" type="button" aria-label="Diminuir quantidade" data-icon="minus"></button><span id="pdp-qty">1</span><button id="pdp-plus" type="button" aria-label="Aumentar quantidade" data-icon="plus"></button></div></div>
<div class="pdp-actions"><button class="button" id="pdp-add" type="button">Adicionar ao carrinho</button></div>
</div>`;
  pdp.querySelectorAll('[data-gallery]').forEach(button=>button.onclick=()=>{const main=pdp.querySelector('.pdp-image');if(main?.tagName==='IMG')main.src=gallery[Number(button.dataset.gallery)]});
  pdp.querySelectorAll('[data-icon]').forEach(el=>el.innerHTML=`<svg viewBox="0 0 24 24" aria-hidden="true">${icons[el.dataset.icon]}</svg>`);
  let qty=1;
  const qtyEl=document.querySelector('#pdp-qty');
  document.querySelector('#pdp-minus').onclick=()=>{if(qty>1){qty--;qtyEl.textContent=qty}};
  document.querySelector('#pdp-plus').onclick=()=>{qty++;qtyEl.textContent=qty};
  document.querySelector('#pdp-add').onclick=()=>addToCart(p.id,qty);
  const sameCategory=products.filter(r=>r.category===p.category&&r.id!==p.id);
  const related=(sameCategory.length?sameCategory:products.filter(r=>r.id!==p.id)).slice(0,3);
  relatedSection.innerHTML=`<h2>Você também pode gostar</h2><div class="products">${related.map(productCard).join('')}</div>`;
}
