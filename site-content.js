'use strict';
window.MahanContent=(()=>{
 const endpoint='https://mahanpro-layout-stats.aerialmandrill32.chatgpt.site/api/content';
 let current=null;
 const ready=fetch(endpoint,{cache:'no-store',signal:AbortSignal.timeout(8000)}).then(r=>{if(!r.ok)throw Error('Content unavailable');return r.json();}).then(data=>{if(data.version!==1||!Array.isArray(data.layouts)||!Array.isArray(data.guides))throw Error('Invalid content');current=data;return data;}).catch(()=>null);
 const local=v=>typeof v==='string'?v:v?.[(typeof I18n!=='undefined'?I18n.language:'en')]||v?.en||'';
 function apply(){
  if(!current)return;const s=current.site||{},page=document.body.dataset.page;
  if(s.name)document.querySelectorAll('.brand>span:last-child').forEach(el=>{el.dataset.noI18n='';el.textContent=s.name;const small=document.createElement('small');small.textContent=s.tagline||'NIGHT VILLAGE';el.append(small);});
  if(s.logo)document.querySelectorAll('.brand-crest').forEach(el=>{const img=document.createElement('img');img.src=s.logo;img.alt='';img.style.cssText='width:100%;height:100%;object-fit:contain';el.replaceChildren(img);});
  if(/^#[\da-f]{6}$/i.test(s.accent||''))document.documentElement.style.setProperty('--purple',s.accent);
  if(s.heroImage){const hero=document.querySelector('.hero-art');if(hero)hero.src=s.heroImage;}
  for(const el of document.querySelectorAll('[data-cms-text]')){const value=s.texts?.[el.dataset.cmsText];if(value){el.dataset.noI18n='';el.textContent=local(value);el.style.whiteSpace='pre-line';}}
  const settings=s.pages?.[page];if(settings?.seoTitle)document.title=settings.seoTitle;if(settings?.seoDescription){const meta=document.querySelector('meta[name="description"]');if(meta){meta.dataset.noI18n='';meta.setAttribute('content',settings.seoDescription);}}
  for(const [key,value] of Object.entries(s.pages||{}))if(!value.enabled){const file={home:'index.html',bases:'bases.html',clan:'clan.html',attacks:'attacks.html',upgrade:'upgrade.html'}[key];document.querySelectorAll('.top-nav a,.mobile-nav a,.quick-nav a,.tool-card').forEach(a=>{if(a.getAttribute('href')===file)a.hidden=true;});}
  if(s.footer)document.querySelectorAll('.site-footer .footer-row>div>p').forEach(el=>{el.dataset.noI18n='';el.textContent=local(s.footer);});
  let notice=document.getElementById('siteAnnouncement');const announcement=local(s.announcement);if(announcement){if(!notice){notice=document.createElement('div');notice.id='siteAnnouncement';notice.dataset.noI18n='';notice.style.cssText='padding:12px 20px;text-align:center;background:#28213f;color:#f3dda9;overflow-wrap:anywhere';document.querySelector('.site-header')?.after(notice);}notice.textContent=announcement;}else notice?.remove();
  if(s.maintenance||settings?.enabled===false){const main=document.querySelector('main');if(main){const box=document.createElement('section');box.className='container section';box.dataset.noI18n='';const title=document.createElement('h1');title.textContent=s.maintenance?(typeof I18n!=='undefined'&&I18n.language==='tr'?'Köy yenileniyor':'The village is being renewed'):(typeof I18n!=='undefined'&&I18n.language==='tr'?'Bu sayfa şu anda kapalı':'This page is currently unavailable');const description=document.createElement('p');description.textContent=local(s.maintenanceText)||'';box.append(title,description);for(const child of main.children)child.hidden=true;main.querySelector('[data-cms-notice]')?.remove();box.dataset.cmsNotice='';main.append(box);}}
 }
 ready.then(()=>{if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',apply,{once:true});else apply();});document.addEventListener('languagechange',apply);
 return {ready,async load(kind,fallback){const data=await ready;if(!data)throw Error('Content unavailable');return {version:1,[kind]:data[kind]};}};
})();
