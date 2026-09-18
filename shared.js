//shared.js — carregado em toda página. Ícones, dados do catálogo, carrinho (persistido)
//e o comportamento comum de header/footer/dialogs. Cada página só usa o que existe no DOM dela.
const icons={search:'<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/>',cart:'<path d="M2 3h3l3 13h11l3-10H6M10 20h.01M18 20h.01"/><circle cx="10" cy="20" r="1"/><circle cx="18" cy="20" r="1"/>',user:'<circle cx="12" cy="7" r="4"/><path d="M3 22v-3a9 9 0 0 1 18 0v3Z"/>',menu:'<path d="M3 6h18M3 12h18M3 18h18"/>',heart:'<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0l-1 1-1-1a5.5 5.5 0 0 0-7.8 7.8L12 22l8.8-9.6a5.5 5.5 0 0 0 0-7.8Z"/>',box:'<path d="m12 2 10 5v10l-10 5-10-5V7Zm0 10v10M2 7l10 5 10-5M7 4.5l10 5V15"/>',spark:'<path d="m12 2 2.7 7.3L22 12l-7.3 2.7L12 22l-2.7-7.3L2 12l7.3-2.7Z"/>',truck:'<path d="M2 16V6a1 1 0 0 1 1-1h11v11H2Z"/><path d="M14 9h4l4 4v3h-8V9Z"/><circle cx="7" cy="18.5" r="1.8"/><circle cx="17.5" cy="18.5" r="1.8"/>',shield:'<path d="M12 2 4 5v6c0 5 3.4 8.7 8 11 4.6-2.3 8-6 8-11V5Z"/><path d="m9 12 2.2 2.2L15.5 10"/>',card:'<rect x="2.5" y="5.5" width="19" height="13" rx="2"/><path d="M2.5 10h19"/><path d="M6 14.5h4"/>',headset:'<path d="M4 13v-1a8 8 0 0 1 16 0v1"/><rect x="2.5" y="13" width="4" height="6" rx="1.5"/><rect x="17.5" y="13" width="4" height="6" rx="1.5"/><path d="M20 19v.5a3 3 0 0 1-3 3h-3"/>',instagram:'<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.2" cy="6.8" r="1"/>',youtube:'<rect x="2.5" y="5.5" width="19" height="13" rx="4"/><path d="M10.5 9.3v5.4l5-2.7Z"/>',tiktok:'<path d="M14 3v10.8a3.2 3.2 0 1 1-2.6-3.15"/><path d="M14 3a5.2 5.2 0 0 0 5 4.9V11a8 8 0 0 1-5-1.7"/>',whatsapp:'<path d="M4 20l1.3-3.9A8 8 0 1 1 8.6 19Z"/><path d="M9 9.5c0 3 2.5 5.5 5.5 5.5.6 0 1-.5.8-1l-.4-1.3a.8.8 0 0 0-1-.5l-.9.3a5 5 0 0 1-2.5-2.5l.3-.9a.8.8 0 0 0-.5-1L9 8c-.5-.2-1 .2-1 .8Z"/>',pin:'<path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11Z"/><circle cx="12" cy="10" r="2.5"/>',check:'<path d="m4 12 5 5L20 6"/>',minus:'<path d="M5 12h14"/>',plus:'<path d="M12 5v14M5 12h14"/>'};
document.querySelectorAll('[data-icon]').forEach(el=>el.innerHTML=`<svg viewBox="0 0 24 24" aria-hidden="true">${icons[el.dataset.icon]}</svg>`);

// Conteúdo publicado pelo gerenciador. Cada foto é um arquivo próprio.
const content=window.FORMAGI_CONTENT||{products:[],categories:[],banners:[],settings:{}};
const settings=content.settings||{};
const categories=(content.categories||[]).filter(c=>c.enabled);
const products=(content.products||[]).filter(p=>p.active).map(p=>({...p,desc:p.description||'',destaque:!!p.featured}));
const categoryLabels={Todos:'Todos os produtos',Presentes:'Presentes',Novidades:'Novidades',...Object.fromEntries(categories.map(c=>[c.id,c.name]))};
const escapeHtml=value=>String(value??'').replace(/[&<>"']/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
const safeAsset=path=>/^(assets|uploads)\/[a-zA-Z0-9_./-]+$/.test(path||'')&&!String(path).includes('..')?path:'';
const nav=document.querySelector('.nav');
if(nav){nav.innerHTML=`<button class="all" data-category="Todos"><span data-icon="menu"></span>Todas as categorias</button>${categories.map(c=>`<button data-category="${escapeHtml(c.id)}">${escapeHtml(c.name)}</button>`).join('')}<button data-category="Novidades">Novidades</button>`;nav.querySelectorAll('[data-icon]').forEach(el=>el.innerHTML=`<svg viewBox="0 0 24 24" aria-hidden="true">${icons[el.dataset.icon]}</svg>`)}
const money=n=>n.toLocaleString('pt-BR',{style:'currency',currency:'BRL'});
const image=(p,extraClass='')=>{const src=safeAsset(p.images?.[0]);return src?`<img class="product-image ${extraClass}" src="${src}" alt="${escapeHtml(p.name)}" loading="lazy">`:`<span class="product-image image-placeholder ${extraClass}" role="img" aria-label="Imagem de ${escapeHtml(p.name)} ainda não cadastrada"></span>`};
const normalize=s=>s.normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase();
//Mesma regra explícita usada no catálogo: Todos/Presentes mostram tudo (catálogo pequeno, tudo
//serve de presente), Novidades filtra por badge, as demais categorias batem o campo category.
function matchesCategory(p,cat){if(cat==='Todos'||cat==='Presentes')return true;if(cat==='Novidades')return p.badge==='Novidade';return p.category===cat}
function findProduct(id){return products.find(p=>p.id===id)}
function productCard(p){return `<article class="product"><a class="product-link" href="produto.html?id=${p.id}" aria-label="Ver detalhes de ${escapeHtml(p.name)}">${image(p)}<div class="product-body"><span class="badge" style="background:${/^#[0-9a-f]{6}$/i.test(p.color)?p.color:'#ff008a'}">${escapeHtml(p.badge)}</span><h3>${escapeHtml(p.name)}</h3><p class="price">${money(p.price)}</p></div></a><button class="button" data-add="${p.id}" aria-label="Adicionar ${escapeHtml(p.name)} ao carrinho">Comprar</button></article>`}

//Carrinho — persistido em localStorage pra sobreviver à navegação entre páginas
const CART_KEY='formagi3d_cart';
function loadCart(){try{const raw=JSON.parse(localStorage.getItem(CART_KEY)||'{}');return new Map(Object.entries(raw).map(([id,q])=>[Number(id),Number(q)]))}catch{return new Map()}}
function saveCart(){localStorage.setItem(CART_KEY,JSON.stringify(Object.fromEntries(cart)))}
const cart=loadCart();
function cartTotals(){let total=0,count=0;cart.forEach((q,id)=>{const p=findProduct(id);if(p){total+=p.price*q;count+=q}});return{total,count}}
let toastTimer;
function toast(message){const el=document.querySelector('#toast');if(!el)return;el.textContent=message;el.classList.add('visible');clearTimeout(toastTimer);toastTimer=setTimeout(()=>el.classList.remove('visible'),2800)}
function addToCart(id,qty=1){const p=findProduct(id);if(!p)throw new Error('Produto não encontrado');cart.set(id,(cart.get(id)||0)+qty);saveCart();renderCartDialog();toast(`${p.name} adicionado ao carrinho`);document.dispatchEvent(new CustomEvent('cart:change'));return{product:p.name,quantity:cart.get(id)}}
function setQty(id,qty){if(qty>0)cart.set(id,qty);else cart.delete(id);saveCart();renderCartDialog();document.dispatchEvent(new CustomEvent('cart:change'))}
function removeFromCart(id){cart.delete(id);saveCart();renderCartDialog();document.dispatchEvent(new CustomEvent('cart:change'))}
function clearCart(){cart.clear();saveCart();renderCartDialog();document.dispatchEvent(new CustomEvent('cart:change'))}

//Dialog rápido do carrinho (existe em toda página que inclui o header padrão)
function renderCartDialog(){
  const itemsEl=document.querySelector('#cart-items');
  if(!itemsEl)return;
  const{total,count}=cartTotals();
  const rows=[...cart].map(([id,q])=>{const p=findProduct(id);if(!p)return'';return`<article class="cart-line">${image(p)}<div><h3>${p.name}</h3><p>${money(p.price*q)}</p><div class="qty"><button data-qty="-1" data-id="${id}" aria-label="Diminuir quantidade de ${p.name}">−</button><span aria-label="Quantidade">${q}</span><button data-qty="1" data-id="${id}" aria-label="Aumentar quantidade de ${p.name}">+</button><button class="remove" data-remove="${id}" aria-label="Remover ${p.name}">Remover</button></div></div></article>`}).join('');
  itemsEl.innerHTML=rows||'<p>Seu carrinho está esperando uma boa ideia.</p><p>Explore os produtos e encontre a sua próxima peça favorita.</p>';
  const cartCount=document.querySelector('#cart-count');if(cartCount)cartCount.textContent=count;
  const dialogCount=document.querySelector('#dialog-count');if(dialogCount)dialogCount.textContent=count?`(${count})`:'';
  const cartTotal=document.querySelector('#cart-total');if(cartTotal)cartTotal.textContent=money(total);
  const subtotal=document.querySelector('#subtotal');if(subtotal)subtotal.textContent=money(total);
  const summary=document.querySelector('#cart-summary');if(summary)summary.hidden=!count;
}
document.addEventListener('click',e=>{
  const add=e.target.closest('[data-add]');if(add){e.preventDefault();addToCart(Number(add.dataset.add))}
  const qty=e.target.closest('[data-qty]');if(qty){const id=Number(qty.dataset.id);setQty(id,(cart.get(id)||0)+Number(qty.dataset.qty))}
  const remove=e.target.closest('[data-remove]');if(remove)removeFromCart(Number(remove.dataset.remove));
  const info=e.target.closest('[data-info]');if(info)showInfo(info.dataset.info);
  const close=e.target.closest('.close');if(close)close.closest('dialog')?.close();
});
document.querySelectorAll('dialog').forEach(d=>d.addEventListener('click',e=>{if(e.target===d){const r=d.getBoundingClientRect();if(e.clientX<r.left||e.clientX>r.right||e.clientY<r.top||e.clientY>r.bottom)d.close()}}));
const cartOpen=document.querySelector('.cart-open');if(cartOpen)cartOpen.onclick=()=>document.querySelector('#cart-dialog').showModal();
const checkoutBtn=document.querySelector('.cart-dialog .checkout');if(checkoutBtn)checkoutBtn.onclick=()=>{location.href='checkout.html'};

//"Minha conta" segue como aviso simples — não há login nesta demonstração
const information={account:['Minha conta','<p>A área de clientes ainda não está disponível nesta demonstração. Você pode explorar os produtos e montar seu carrinho sem cadastro.</p>']};
function showInfo(key){const entry=information[key];if(!entry)return;document.querySelector('#info-title').textContent=entry[0];document.querySelector('#info-content').innerHTML=entry[1];document.querySelector('#info-dialog').showModal()}

//Busca e categorias do header: na página de catálogo filtram in-page; em qualquer outra, navegam pra lá
function goToCatalog(params){
  if(document.body.dataset.page==='produtos'){document.dispatchEvent(new CustomEvent('catalog:nav',{detail:params}));return}
  const qs=new URLSearchParams(params).toString();
  location.href='produtos.html'+(qs?'?'+qs:'');
}
document.addEventListener('click',e=>{const cat=e.target.closest('[data-category]');if(cat){e.preventDefault();goToCatalog({categoria:cat.dataset.category})}});
const headerSearch=document.querySelector('.search');
if(headerSearch)headerSearch.addEventListener('submit',e=>{e.preventDefault();const q=document.querySelector('#search').value.trim();goToCatalog(q?{q}:{})});

//Newsletter do rodapé (presente em toda página)
const newsletterForm=document.querySelector('#newsletter-form');
if(newsletterForm)newsletterForm.addEventListener('submit',e=>{e.preventDefault();const input=document.querySelector('#newsletter-email');if(!input.checkValidity()){input.reportValidity();return}toast('Cadastro recebido! Envio de novidades ainda não está ativo nesta demonstração.');newsletterForm.reset()});

const yearEl=document.querySelector('#year');if(yearEl)yearEl.textContent=new Date().getFullYear();
const footerIdentity=document.querySelector('.footer-main>div:last-child');
if(footerIdentity){
  const heading=footerIdentity.querySelector('h3');if(heading)heading.textContent=settings.storeName||'Formagi3D';
  const paragraphs=footerIdentity.querySelectorAll('p');
  if(paragraphs[0])paragraphs[0].textContent=settings.tagline||'';
  const place=footerIdentity.querySelector('.footer-location');if(place)place.lastChild.textContent=settings.location||'';
  if(settings.email){const a=document.createElement('a');a.href='mailto:'+settings.email;a.textContent=settings.email;footerIdentity.appendChild(a)}
}
const instagramLink=document.querySelector('.social-links [aria-label*="Instagram"]');
if(instagramLink){if(/^https:\/\//i.test(settings.instagram||''))instagramLink.href=settings.instagram;else instagramLink.hidden=true}
const whatsappLink=document.querySelector('.social-links [aria-label*="WhatsApp"]');
if(whatsappLink){const digits=(settings.whatsapp||'').replace(/\D/g,'');if(digits)whatsappLink.href='https://wa.me/'+(digits.startsWith('55')?digits:'55'+digits);else whatsappLink.hidden=true}
document.querySelectorAll('.social-links a[href="#"]').forEach(a=>a.hidden=true);
renderCartDialog();

//Deixa o catálogo de demonstração operável por um agente (MCP), em qualquer página
if(document.modelContext?.registerTool){
  const lifecycle=new AbortController();
  const tools=[
    {name:'search_products',description:'Filtra o catálogo demonstrativo e retorna os produtos correspondentes.',inputSchema:{type:'object',properties:{query:{type:'string'}},required:['query'],additionalProperties:false},
     execute(input){if(typeof input?.query!=='string')throw new Error('Informe uma busca em texto');const q=normalize(input.query.trim());return{products:products.filter(p=>normalize(p.name+' '+p.category).includes(q)).map(({id,name,price})=>({id,name,price}))}}},
    {name:'add_to_cart',description:'Adiciona um produto ao carrinho de demonstração. Não realiza pedidos ou pagamentos.',inputSchema:{type:'object',properties:{productId:{type:'integer'}},required:['productId'],additionalProperties:false},
     execute(input){if(!Number.isInteger(input?.productId))throw new Error('Identificador inválido');return addToCart(input.productId)}}
  ];
  for(const tool of tools){try{Promise.resolve(document.modelContext.registerTool({...tool,annotations:{readOnlyHint:false,untrustedContentHint:false}},{signal:lifecycle.signal})).catch(()=>{})}catch{}}
  window.addEventListener('pagehide',()=>lifecycle.abort(),{once:true})
}
