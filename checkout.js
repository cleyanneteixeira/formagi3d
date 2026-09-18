let paymentConfig={enabled:false};
let shippingOptions=[];
let selectedShipping='';
let shippingMessage='Informe o CEP para calcular o frete.';
let quoteKey='';
let quoteTimer;
const form=document.querySelector('#checkout-form');
const submit=form.querySelector('button[type="submit"]');
const message=document.querySelector('#payment-message');
const cepInput=document.querySelector('#cep');
submit.disabled=true;
fetch('api/config.php',{cache:'no-store'}).then(r=>r.json()).then(config=>{
  paymentConfig=config;
  message.textContent=config.enabled?'Pagamento seguro por Pix ou cartão no Checkout InfinitePay.':'Pagamento temporariamente indisponível. A loja ainda está configurando o checkout.';
  renderSummary();
}).catch(()=>{message.textContent='Não foi possível consultar o pagamento. Tente novamente mais tarde.'});
const items=()=>[...cart].map(([id,quantity])=>({id,quantity}));
function currentQuoteKey(){return JSON.stringify({cep:cepInput.value.replace(/\D/g,''),items:items()})}
function renderSummary(){
  const summary=document.querySelector('#order-summary');
  const {total,count}=cartTotals();
  if(!count){summary.innerHTML='<h2>Resumo do pedido</h2><p>Seu carrinho está vazio.</p><a class="button" href="produtos.html">Ver produtos</a>';submit.disabled=true;return}
  const rows=[...cart].map(([id,q])=>{const p=findProduct(id);return p?`<div class="subtotal"><span>${escapeHtml(p.name)} × ${q}</span><span>${money(p.price*q)}</span></div>`:''}).join('');
  const option=shippingOptions.find(o=>o.id===selectedShipping);
  const choices=shippingOptions.map(o=>`<label class="shipping-choice"><input type="radio" name="shipping-option" value="${escapeHtml(o.id)}" ${o.id===selectedShipping?'checked':''}><span>${escapeHtml(o.label)}${o.deliveryDays?`<small>Até ${o.deliveryDays} dias úteis após a postagem</small>`:''}</span><strong>${o.shippingCents===0?'Grátis':money(o.shippingCents/100)}</strong></label>`).join('');
  summary.innerHTML=`<h2>Resumo do pedido</h2>${rows}<div class="subtotal"><span>Subtotal</span><span>${money(total)}</span></div><div class="shipping-options" role="group" aria-label="Opções de frete">${choices||`<p role="status">${escapeHtml(shippingMessage)}</p>`}</div><div class="subtotal"><strong>Total</strong><strong>${option?money(total+option.shippingCents/100):'A calcular'}</strong></div><p>${count} item(ns) no carrinho.</p>`;
  summary.querySelectorAll('input[name="shipping-option"]').forEach(input=>input.addEventListener('change',()=>{selectedShipping=input.value;renderSummary()}));
  submit.disabled=!paymentConfig.enabled||!option;
}
async function quoteShipping(){
  const key=currentQuoteKey(); const cep=cepInput.value.replace(/\D/g,'');
  if(cep.length!==8||!cart.size){quoteKey='';shippingOptions=[];selectedShipping='';shippingMessage='Informe o CEP com 8 dígitos para calcular o frete.';renderSummary();return}
  if(key===quoteKey&&shippingOptions.length)return;
  quoteKey=key;shippingOptions=[];selectedShipping='';shippingMessage='Calculando frete…';renderSummary();
  try{
    const response=await fetch('api/shipping.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({cep,items:items()})});
    const result=await response.json();
    if(!response.ok||!Array.isArray(result.options))throw new Error(result.error||'Não foi possível calcular o frete.');
    if(currentQuoteKey()!==key)return;
    shippingOptions=result.options;selectedShipping=shippingOptions[0]?.id||'';
    shippingMessage=shippingOptions.length?'':'Nenhuma opção de frete disponível.';
  }catch(error){if(currentQuoteKey()!==key)return;shippingMessage=error.message||'Não foi possível calcular o frete.';quoteKey=''}
  renderSummary();
}
function scheduleQuote(){clearTimeout(quoteTimer);quoteTimer=setTimeout(quoteShipping,350)}
cepInput.addEventListener('input',()=>{quoteKey='';shippingOptions=[];selectedShipping='';shippingMessage='Calculando frete…';renderSummary();scheduleQuote()});
document.addEventListener('cart:change',()=>{quoteKey='';shippingOptions=[];selectedShipping='';scheduleQuote();renderSummary()});
renderSummary();
form.addEventListener('submit',async e=>{
  e.preventDefault();
  if(!cart.size||!paymentConfig.enabled||!selectedShipping)return;
  submit.disabled=true;message.textContent='Conferindo o frete e abrindo o pagamento…';
  const value=id=>document.getElementById(id).value.trim();
  const payload={customer:{name:value('nome'),email:value('email'),phone:value('telefone')},shipping:{street:value('endereco'),number:value('numero'),complement:value('complemento'),city:value('cidade'),state:value('estado'),cep:value('cep')},items:items(),shippingOption:selectedShipping};
  try{
    const response=await fetch('api/checkout.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
    const result=await response.json();
    if(!response.ok||!result.url)throw new Error(result.error||'Não foi possível abrir o pagamento.');
    sessionStorage.setItem('formagi_last_order',result.orderId);
    location.href=result.url;
  }catch(error){message.textContent=error.message||'Falha ao iniciar pagamento.';renderSummary()}
});
