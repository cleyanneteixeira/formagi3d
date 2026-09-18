//rastreio.js — rastreamento de demonstração (nenhum pedido real existe para rastrear)
const trackForm=document.querySelector('#track-form');
trackForm.addEventListener('submit',e=>{
  e.preventDefault();
  document.querySelector('#track-result').innerHTML='<div class="demo-note">Rastreamento ainda não está disponível — esta demonstração não processa pedidos reais, então não existe um envio de verdade pra acompanhar.</div>';
});
