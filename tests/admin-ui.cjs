const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const vm=require('node:vm');
const {JSDOM,VirtualConsole}=require('jsdom');
const {DatabaseSync}=require('node:sqlite');
const {pathToFileURL}=require('node:url');
const root=path.resolve(__dirname,'..');
const source=name=>fs.readFileSync(path.join(root,name),'utf8');
const files=Object.fromEntries(['catalog.json','guides.json','site-defaults.json','content-inbox.json'].map(name=>[name,JSON.parse(source(name))]));
const wait=async(fn,label)=>{for(let i=0;i<100;i++){if(fn())return;await new Promise(r=>setTimeout(r,10));}throw Error('Timed out: '+label);};
(async()=>{
 const {default:worker}=await import(pathToFileURL(path.join(root,'services/layout-stats/index.js')));
 global.fetch=async url=>new Response(JSON.stringify(files[String(url).split('/').pop()]));
 const db=new DatabaseSync(':memory:');for(const name of ['schema.sql','content-reviews.sql','admin-content.sql'])db.exec(source('services/layout-stats/'+name));
 const env={DB:{prepare(sql){const s=db.prepare(sql);let args=[];return {bind(...v){args=v;return this;},async first(){return s.get(...args)||null;},async all(){return {results:s.all(...args)};},async run(){return {meta:{changes:Number(s.run(...args).changes)}};}};}}};
 let failSave=false,failLoad=false;
 const publicState=async()=>{const r=await worker.fetch(new Request('https://cms.example/api/content'),env);return r.json();};
 function makeWindow(file){const errors=[],console=new VirtualConsole();console.on('jsdomError',e=>errors.push(e));const dom=new JSDOM(source(file),{url:'https://ahmetozhans.github.io/coc/'+file,runScripts:'outside-only',virtualConsole:console,pretendToBeVisual:true});const w=dom.window;const observers=[];const Observer=w.MutationObserver;w.MutationObserver=class extends Observer{constructor(fn){super(fn);observers.push(this);}};w.__qaObservers=observers;w.AbortSignal=global.AbortSignal;w.structuredClone=global.structuredClone;w.scrollTo=()=>{};w.confirm=()=>true;w.HTMLDialogElement.prototype.showModal=function(){this.setAttribute('open','');};w.HTMLDialogElement.prototype.close=function(){this.removeAttribute('open');};w.fetch=async(url,opt={})=>{
  const u=new URL(url,w.location.href);
  if(u.host==='mahanpro-layout-stats.aerialmandrill32.chatgpt.site'){
   if(failLoad&&opt.method!=='POST')return new Response(JSON.stringify({error:'Test service unavailable'}),{status:503});
   if(failSave&&opt.method==='POST')return new Response(JSON.stringify({error:'Stale revision test'}),{status:409});
   return worker.fetch(new Request('https://cms.example'+u.pathname,{...opt,headers:{Origin:'https://ahmetozhans.github.io',...(opt.headers||{})}}),env);
  }
  return new Response(JSON.stringify(files[u.pathname.split('/').pop()]));
 };return {dom,w,d:w.document,errors,run:name=>vm.runInContext(source(name),dom.getInternalVMContext(),{filename:name})};}
 const ui=makeWindow('admin.html'),{w,d}=ui;ui.run('admin.js');ui.run('review-inbox.js');
 await wait(()=>d.querySelectorAll('#layoutGrid .content-card').length===20,'initial layouts');
 await wait(()=>d.querySelectorAll('.review-card').length===3,'review proposals');
 assert.equal(d.querySelector('#queueBadge').textContent,'3');assert.equal(d.querySelectorAll('[data-panel]:not([hidden])').length,1);assert.equal(d.querySelector('a[href*="signin-with-chatgpt"]'),null);
 d.querySelector('[data-create="layout"]').click();assert.ok(d.querySelector('#editorDialog').open);
 for(const [id,value]of Object.entries({editTitle:'CMS new layout',editTh:'16',editType:'War',editUrl:'https://link.clashofclans.com/en?action=OpenLayout&id=TH16%3ATEST%3Anew-manual',editDescription:'Manual description'}))d.getElementById(id).value=value;
 d.querySelector('#editorForm').dispatchEvent(new w.Event('submit',{cancelable:true}));await wait(()=>!d.querySelector('#editorDialog').open,'save layout');
 let data=await publicState(),created=data.layouts.find(x=>x.title==='CMS new layout');assert.ok(created);assert.equal(data.layouts.length,21);
 d.querySelector('[data-section="layouts"]').click();assert.equal(d.querySelector('[data-panel="layouts"]').hidden,false);
 d.querySelector('[data-toggle-layout="'+created.id+'"]').click();await wait(()=>d.querySelector('[data-toggle-layout="'+created.id+'"]').textContent==='Yayınla','unpublish');assert.equal((await publicState()).layouts.some(x=>x.id===created.id),false);
 d.querySelector('[data-edit-layout="'+created.id+'"]').click();d.querySelector('#editTitle').value='Must not save';failSave=true;d.querySelector('#editorForm').dispatchEvent(new w.Event('submit',{cancelable:true}));await wait(()=>d.querySelector('#editorError').textContent.includes('Stale'),'stale error');assert.equal(d.querySelector('#editTitle').value,'Must not save');assert.equal(d.querySelector('#editorDialog').open,true);d.querySelector('#closeEditor').click();failSave=false;
 d.querySelector('[data-create="guide"]').click();for(const [id,value]of Object.entries({editTitleTr:'Yeni CMS rehberi',editTitleEn:'New CMS guide',editSummaryTr:'Özet',editSummaryEn:'Summary',editTownHalls:'15, 16',editStepsTr:'Birinci adım\nİkinci adım',editStepsEn:'First step\nSecond step'}))d.getElementById(id).value=value;
 d.querySelector('#editorForm').dispatchEvent(new w.Event('submit',{cancelable:true}));await wait(()=>!d.querySelector('#editorDialog').open,'save guide');assert.equal((await publicState()).guides.length,4);
 d.querySelector('[data-section="pages"]').click();const text=d.querySelector('[data-text-id="home-0"][data-lang="tr"]');assert.ok(text);text.value='Yeni gece köyü';d.querySelector('#pageSeoDescription').value='CMS custom SEO';text.dispatchEvent(new w.Event('input',{bubbles:true}));const revision=(await publicState()).revision;d.querySelector('#pageForm').dispatchEvent(new w.Event('submit',{cancelable:true}));await wait(()=>d.querySelector('#saveInfo').textContent.includes('v'+(revision+1)),'save page text');assert.equal((await publicState()).site.texts['home-0'].tr,'Yeni gece köyü');
 d.querySelector('[data-section="brand"]').click();d.querySelector('#brandName').value='Mahanpro Test';d.querySelector('#brandName').dispatchEvent(new w.Event('input',{bubbles:true}));assert.ok(d.querySelector('#brandPreview').textContent.includes('Mahanpro Test'));const revision2=(await publicState()).revision;d.querySelector('#brandForm').dispatchEvent(new w.Event('submit',{cancelable:true}));await wait(()=>d.querySelector('#saveInfo').textContent.includes('v'+(revision2+1)),'save brand');assert.equal((await publicState()).site.name,'Mahanpro Test');
 d.querySelector('[data-section="analytics"]').click();assert.equal(d.querySelectorAll('#statsRows tr').length,21);
 d.querySelector('[data-section="suggestions"]').click();assert.equal(d.querySelector('#suggestions').hidden,false);d.querySelector('[data-decision="approved"]').click();await wait(()=>d.querySelectorAll('.review-card').length===2,'approve proposal');assert.equal(d.querySelector('#queueBadge').textContent,'2');assert.equal((await publicState()).guides.length,4);
 const publicPage=makeWindow('index.html');publicPage.run('i18n.js');publicPage.run('site-content.js');await wait(()=>publicPage.d.querySelector('.brand').textContent.includes('Mahanpro Test'),'public brand');vm.runInContext("I18n.setLanguage('tr')",publicPage.dom.getInternalVMContext());await wait(()=>publicPage.d.querySelector('[data-cms-text="home-0"]').textContent==='Yeni gece köyü','custom localized text');
 assert.equal(publicPage.d.querySelector('meta[name="description"]').content,'CMS custom SEO');
 const bases=makeWindow('bases.html');bases.run('i18n.js');bases.run('site-content.js');bases.run('app.js');await wait(()=>bases.d.querySelectorAll('.layout-card').length===20,'public gallery respects hidden records');assert.equal(bases.d.querySelector('form[id*=admin]'),null);
 const attacks=makeWindow('attacks.html');attacks.run('i18n.js');attacks.run('site-content.js');attacks.run('app.js');await wait(()=>attacks.d.querySelectorAll('.guide-card').length===4,'public guides from CMS');
 const emptyRevision=(await publicState()).revision;
 const contentRow=JSON.parse(db.prepare('SELECT document FROM admin_content WHERE id=1').get().document);
 const allGuides=(await publicState()).guides;for(const g of allGuides)contentRow.guideOverrides[g.id]={...g,hidden:true};
 db.prepare('UPDATE admin_content SET document=?,revision=revision+1 WHERE id=1').run(JSON.stringify(contentRow));
 const emptyGuides=makeWindow('attacks.html');emptyGuides.run('i18n.js');emptyGuides.run('site-content.js');emptyGuides.run('app.js');await wait(()=>emptyGuides.d.querySelector('#guideGallery .empty'),'empty guides');assert.equal(emptyGuides.d.querySelectorAll('.guide-card').length,0);
 contentRow.site.maintenance=true;contentRow.site.maintenanceText={tr:'Güncelleniyor',en:'Updating'};db.prepare('UPDATE admin_content SET document=?,revision=revision+1 WHERE id=1').run(JSON.stringify(contentRow));
 const maintenance=makeWindow('upgrade.html');maintenance.run('i18n.js');maintenance.run('site-content.js');maintenance.run('app.js');await wait(()=>maintenance.d.querySelector('[data-cms-notice]'),'maintenance');assert.ok(maintenance.d.querySelector('#upgradeForm'));assert.ok(maintenance.d.querySelector('#upgradeForm').closest('[hidden]'));
 failLoad=true;const failedPublic=makeWindow('attacks.html');failedPublic.run('i18n.js');failedPublic.run('site-content.js');failedPublic.run('app.js');await wait(()=>failedPublic.d.querySelector('#guideGallery').textContent.includes('could not be loaded'),'public outage');assert.equal(failedPublic.d.querySelectorAll('.guide-card').length,0);
 d.querySelector('#reloadState').click();await wait(()=>!d.querySelector('#globalMessage').hidden,'service outage');assert.ok(d.querySelector('#globalMessage').textContent.includes('Test service unavailable'));
 await new Promise(resolve=>setTimeout(resolve,100));
 for(const page of [ui,publicPage,bases,attacks,emptyGuides,maintenance,failedPublic]){assert.equal(page.errors.length,0,page.errors.map(x=>x.message).join(';'));for(const observer of page.w.__qaObservers)observer.disconnect();page.dom.window.close();}
 db.close();console.log('PASS: admin DOM workflows, persistent content edits, stale-save recovery, review decisions, public content integration, bilingual text, hidden records, outage recovery. This is DOM QA; browser layout is not verified.');
})().catch(err=>{console.error(err);process.exitCode=1;});
