//carrinho.js — página de carrinho completa (lista, quantidades, resumo do pedido)
function renderCartPage(){
  const itemsEl=document.querySelector('#cart-page-items');
  const summaryEl=document.querySelector('#order-summary');
  const entries=[...cart];
  if(!entries.length){
    itemsEl.innerHTML='<div class="empty-state"><p>Seu carrinho está vazio por enquanto.</p><a class="button" href="produtos.html">Ver produtos</a></div>';
    summaryEl.innerHTML='';
    return;
  }
  itemsEl.innerHTML=entries.map(([id,q])=>{
    const p=findProduct(id);
    if(!p)return'';
    return `<article class="cart-page-line">${image(p)}<div class="cart-line-body"><a href="produto.html?id=${p.id}"><h3>${p.name}</h3></a><p class="price">${money(p.price)}</p><div class="qty-stepper"><button data-qty="-1" data-id="${id}" type="button" aria-label="Diminuir quantidade de ${p.name}" data-icon="minus"></button><span aria-label="Quantidade">${q}</span><button data-qty="1" data-id="${id}" type="button" aria-label="Aumentar quantidade de ${p.name}" data-icon="plus"></button></div><button class="text-button remove" data-remove="${id}" type="button">Remover</button></div></article>`;
  }).join('');
  itemsEl.querySelectorAll('[data-icon]').forEach(el=>el.innerHTML=`<svg viewBox="0 0 24 24" aria-hidden="true">${icons[el.dataset.icon]}</svg>`);
  const{total,count}=cartTotals();
  summaryEl.innerHTML=`<h2>Resumo do pedido</h2><p>${count} ite${count===1?'m':'ns'} no carrinho</p><div class="subtotal"><span>Subtotal</span><strong>${money(total)}</strong></div><a class="button" href="checkout.html">Finalizar compra →</a><p>Frete e formas de pagamento são calculados na próxima etapa.</p>`;
}
document.addEventListener('cart:change',renderCartPage);
renderCartPage();
