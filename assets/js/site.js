(()=>{
 const header=document.querySelector('.site-header');
 const toggle=document.querySelector('.menu-toggle');
 const nav=document.querySelector('.site-header nav');
 const isArabic=document.documentElement.lang==='ar';
 const menuLabel=(open)=>isArabic?(open?'إغلاق القائمة':'فتح القائمة'):(open?'Close navigation':'Open navigation');
 const syncHeader=()=>{if(header)header.classList.toggle('scrolled',window.scrollY>18);};
 syncHeader();window.addEventListener('scroll',syncHeader,{passive:true});
 if(toggle&&nav){
  toggle.addEventListener('click',()=>{const open=nav.classList.toggle('open');toggle.setAttribute('aria-expanded',String(open));toggle.setAttribute('aria-label',menuLabel(open));});
  nav.querySelectorAll('a').forEach(link=>link.addEventListener('click',()=>{nav.classList.remove('open');toggle.setAttribute('aria-expanded','false');toggle.setAttribute('aria-label',menuLabel(false));}));
 }
 document.querySelectorAll('.language-switcher a').forEach(link=>link.addEventListener('click',()=>{
  if(!window.location.hash)return;
  const target=new URL(link.href,window.location.href);target.hash=window.location.hash;link.href=target.toString();
 }));
 const reduced=window.matchMedia('(prefers-reduced-motion: reduce)').matches;
 if('IntersectionObserver'in window&&!reduced){
  const sections=document.querySelectorAll('main>section');document.documentElement.classList.add('has-reveal');
  const observer=new IntersectionObserver(entries=>entries.forEach(entry=>{if(entry.isIntersecting){entry.target.classList.add('is-visible');observer.unobserve(entry.target);}}),{threshold:.04});
  sections.forEach(section=>observer.observe(section));
 }
})();
