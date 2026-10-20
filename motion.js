(()=>{
  const settings=window.SITE_CONFIG.motion||{},preference=matchMedia('(prefers-reduced-motion: reduce)');
  if(settings.enabled===false||preference.matches||!('IntersectionObserver' in window))return;
  const pending=new Set(),puzzles=new Set();
  const excluded='.hero,.category-hero,[data-aos],.swiper,.product-card,.occasion-flip,.showcase-card,.story-feature,.story-minis,.puzzle-grid,.material-rail,.accordion,.flip-photo,.fit-drawing,.comparison-stage,.legal-articles,form,[hidden]';
  const show=el=>{if(el.matches('.puzzle-grid')){el.classList.add('assembled');puzzles.delete(el)}else{el.classList.remove('quiet-reveal-pending');pending.delete(el)}observer.unobserve(el)};
  const observer=new IntersectionObserver(entries=>entries.forEach(entry=>{if(entry.isIntersecting)show(entry.target)}),{threshold:0,rootMargin:'0px 0px -20px 0px'});
  const viewport=window.innerHeight||document.documentElement.clientHeight;
  document.querySelectorAll('main section h2,main section p,footer h2,.inspiration-photo,.material-photo,.story-minis,.faq-horizontal,.faq-cutout').forEach(el=>{
    if(el.closest(excluded)||el.getBoundingClientRect().top<viewport+40)return;
    el.classList.add('quiet-reveal','quiet-reveal-pending');pending.add(el);observer.observe(el);
  });
  document.querySelectorAll('.puzzle-grid').forEach(grid=>{grid.classList.add('puzzle-ready');puzzles.add(grid);observer.observe(grid)});
  const revealAll=()=>{puzzles.forEach(grid=>grid.classList.add('assembled'));puzzles.clear();pending.forEach(el=>el.classList.remove('quiet-reveal-pending'));pending.clear();observer.disconnect()};
  preference.addEventListener?.('change',e=>{if(e.matches)revealAll()});
  window.addEventListener('pageshow',e=>{if(e.persisted)revealAll()});
  document.addEventListener('focusin',e=>{puzzles.forEach(grid=>{if(grid.contains(e.target))show(grid)});pending.forEach(el=>{if(el.contains(e.target))show(el)})});
  window.addEventListener('beforeprint',revealAll);
})();
