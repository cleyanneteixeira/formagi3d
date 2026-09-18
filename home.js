// Categorias, banners e produtos publicados no gerenciador.
const categoryTrack=document.querySelector('.categories');
categoryTrack.innerHTML=categories.map(c=>`<button class="category" data-category="${escapeHtml(c.id)}">${safeAsset(c.image)?`<img src="${c.image}" alt="" loading="lazy">`:'<span class="category-placeholder"></span>'}<strong>${escapeHtml(c.name)}</strong><small>${escapeHtml(c.subtitle)}</small><span class="arrow" aria-hidden="true">→</span></button>`).join('');
const categoryControls=document.querySelector('.category-controls');
function updateCategoryControls(){
  const hasOverflow=categoryTrack.scrollWidth>categoryTrack.clientWidth+2;
  categoryControls.hidden=!hasOverflow;
  categoryControls.querySelector('[data-slide="prev"]').disabled=categoryTrack.scrollLeft<2;
  categoryControls.querySelector('[data-slide="next"]').disabled=categoryTrack.scrollLeft+categoryTrack.clientWidth>=categoryTrack.scrollWidth-2;
}
categoryControls.addEventListener('click',event=>{
  const arrow=event.target.closest('[data-slide]');
  if(!arrow)return;
  const card=categoryTrack.querySelector('.category');
  if(!card)return;
  const gap=parseFloat(getComputedStyle(categoryTrack).gap)||0;
  categoryTrack.scrollBy({left:(card.getBoundingClientRect().width+gap)*(arrow.dataset.slide==='next'?1:-1),behavior:'smooth'});
});
categoryTrack.addEventListener('scroll',updateCategoryControls,{passive:true});
window.addEventListener('resize',updateCategoryControls);
updateCategoryControls();
document.querySelector('#product-grid').innerHTML=products.filter(p=>p.destaque).slice(0,6).map(productCard).join('');
const heroBanner=content.banners?.find(b=>b.id==='hero');
const heroArt=document.querySelector('.hero-art');
if(heroArt){
  heroArt.style.backgroundImage=heroBanner?.enabled&&safeAsset(heroBanner.image)?`url("${heroBanner.image}")`:'none';
  heroArt.classList.toggle('original-character',heroBanner?.image==='assets/banner-personagem-limpo.jpg');
  heroArt.parentElement.classList.toggle('original-character-banner',heroBanner?.image==='assets/banner-personagem-limpo.jpg');
}
for(const id of ['personal','gift']){
  const banner=content.banners?.find(b=>b.id===id);
  const card=document.querySelector(`.promo.${id}`);
  if(card&&banner?.enabled&&safeAsset(banner.image))card.style.setProperty('--promo-image',`url("${banner.image}")`);
}
document.querySelector('.hero .eyebrow').textContent=settings.heroEyebrow||'';
const heroTitle=document.querySelector('.hero h1');
if((settings.heroTitle||'')==='MAIS QUE OBJETOS, HISTÓRIAS EM 3D.')heroTitle.innerHTML='<span class="line">MAIS QUE</span> <span class="line">OBJETOS,</span> <span class="line"><em>HISTÓRIAS</em></span> <span class="line"><mark>EM 3D.</mark></span>';
else heroTitle.textContent=settings.heroTitle||'';
document.querySelector('.hero p').textContent=settings.heroDescription||'';
const catalogNote=document.querySelector('.catalog-note');
if(catalogNote)catalogNote.textContent=settings.catalogNote||'';
