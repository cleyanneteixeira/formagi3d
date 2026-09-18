//contato.js — formulário de contato desta demonstração (não envia de verdade)
const contactForm=document.querySelector('#contact-form');
contactForm.addEventListener('submit',e=>{
  e.preventDefault();
  toast('Mensagem recebida! O envio real ainda não está ativo nesta demonstração.');
  contactForm.reset();
});
