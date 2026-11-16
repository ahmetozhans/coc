import {respond,publishedFile,statsStatement,resultMap} from './index.js';
const CMS_ID=/^[A-Za-z0-9_-]{1,100}$/;
const PAGE_KEYS=['home','bases','clan','attacks','upgrade'];
const emptyStore=()=>({layoutOverrides:{},guideOverrides:{},site:{texts:{},pages:{}},history:[]});
const requireValue=(condition,message)=>{if(!condition)throw new Error(message);};
const shortText=(v,max=500)=>typeof v==='string'&&v.length<=max;
const httpsURL=v=>{try{const u=new URL(v);return u.protocol==='https:'&&!u.username&&!u.password;}catch{return false;}};
function checkLayout(x){
 requireValue(x&&CMS_ID.test(x.id)&&shortText(x.title,70)&&x.title.trim()&&Number.isInteger(x.th)&&x.th>=3&&x.th<=18&&['War','Farming','Trophy','Hybrid'].includes(x.type),'Düzen bilgileri geçersiz.');
 let u;try{u=new URL(x.url);}catch{}
 requireValue(u&&httpsURL(x.url)&&u.hostname==='link.clashofclans.com'&&u.searchParams.get('action')==='OpenLayout'&&u.searchParams.get('id')?.startsWith('TH'+x.th+':'),'Belediye binasıyla eşleşen resmi düzen bağlantısı gerekiyor.');
 requireValue((!x.image||httpsURL(x.image))&&shortText(x.description||'',160)&&typeof x.hidden==='boolean','Görsel veya açıklama geçersiz.');
}
function checkGuide(x){
 requireValue(x&&CMS_ID.test(x.id)&&['Air','Ground','War'].includes(x.category)&&Array.isArray(x.townHalls)&&x.townHalls.length>0&&x.townHalls.length<=16&&x.townHalls.every(n=>Number.isInteger(n)&&n>=3&&n<=18)&&typeof x.hidden==='boolean','Rehber bilgileri geçersiz.');
 for(const lang of ['tr','en'])requireValue(shortText(x.title?.[lang],100)&&x.title[lang].trim()&&shortText(x.summary?.[lang],500)&&Array.isArray(x.steps?.[lang])&&x.steps[lang].length>=2&&x.steps[lang].length<=8&&x.steps[lang].every(t=>shortText(t,350)&&t.trim()),'Her iki dilde başlık, özet ve 2–8 adım gerekiyor.');
 requireValue(x.steps.tr.length===x.steps.en.length,'İki dilde adım sayısı eşit olmalı.');
}
function checkSite(s){
 requireValue(s&&typeof s==='object'&&!Array.isArray(s),'Site ayarları geçersiz.');
 for(const key of Object.keys(s))requireValue(['name','tagline','logo','heroImage','accent','announcement','maintenance','maintenanceText','footer','pages','texts'].includes(key),'Bilinmeyen ayar.');
 for(const key of ['name','tagline'])if(key in s)requireValue(shortText(s[key],60)&&s[key].trim(),'Marka bilgileri geçersiz.');
 for(const key of ['logo','heroImage'])if(key in s)requireValue(!s[key]||httpsURL(s[key]),'HTTPS görsel bağlantısı gerekiyor.');
 if('accent'in s)requireValue(/^#[a-fA-F0-9]{6}$/.test(s.accent),'Renk geçersiz.');
 for(const key of ['announcement','maintenanceText','footer'])if(key in s)for(const lang of ['tr','en'])requireValue(shortText(s[key]?.[lang],500),'Metin en fazla 500 karakter olabilir.');
 if('maintenance'in s)requireValue(typeof s.maintenance==='boolean','Bakım ayarı geçersiz.');
 requireValue(s.pages&&Object.keys(s.pages).every(k=>PAGE_KEYS.includes(k)),'Sayfa ayarları geçersiz.');
 for(const x of Object.values(s.pages)){requireValue(typeof x.enabled==='boolean'&&shortText(x.seoTitle,120)&&shortText(x.seoDescription,300),'Sayfa bilgileri geçersiz.');}
 requireValue(s.texts&&typeof s.texts==='object'&&!Array.isArray(s.texts)&&Object.keys(s.texts).length<=250,'Metin alanları geçersiz.');
 for(const [key,x] of Object.entries(s.texts)){requireValue(/^(home|bases|clan|attacks|upgrade)-\d+$/.test(key),'Metin kimliği geçersiz.');for(const lang of ['tr','en'])requireValue(shortText(x?.[lang],1200),'Sayfa metni çok uzun.');}
}
function mergeRecords(base,overrides){const map=new Map(base.map(x=>[x.id,{...x,hidden:false}]));for(const [id,value]of Object.entries(overrides))map.set(id,value);return [...map.values()];}
async function loadCMS(env){const row=await env.DB.prepare('SELECT revision,document,updated_at FROM admin_content WHERE id=1').first();return row?{revision:row.revision,document:JSON.parse(row.document),updatedAt:row.updated_at}:{revision:0,document:emptyStore(),updatedAt:null};}
async function cmsBaseline(){const [catalog,guides,defaults]=await Promise.all([publishedFile('catalog.json'),publishedFile('guides.json'),publishedFile('site-defaults.json')]);return {layouts:catalog.layouts,guides:guides.guides,defaults};}
async function contentRoute(request,env,url){
 const origin=request.headers.get('Origin'),reply=(x,status=200)=>respond(x,status,origin);
 if(!env.DB)return reply({error:'İçerik veritabanına ulaşılamıyor.'},503);
 try{
  const saved=await loadCMS(env);
  if(request.method==='GET'){
   const base=await cmsBaseline(),layouts=mergeRecords(base.layouts,saved.document.layoutOverrides),guides=mergeRecords(base.guides,saved.document.guideOverrides);
   if(url.pathname==='/api/content')return reply({version:1,revision:saved.revision,site:saved.document.site,layouts:layouts.filter(x=>!x.hidden),guides:guides.filter(x=>!x.hidden)});
   const ids=layouts.map(x=>x.id),rows=[];for(let i=0;i<ids.length;i+=20)rows.push(...(await statsStatement(env.DB,ids.slice(i,i+20)).all()).results);const totals=resultMap(ids,rows);
   return reply({version:1,...saved,layouts,guides,defaults:base.defaults,stats:totals});
  }
  if(request.method!=='POST'||url.pathname!=='/api/admin/content')return reply({error:'Not found'},404);
  if(!request.headers.get('Content-Type')?.startsWith('application/json'))return reply({error:'JSON gerekiyor.'},400);
  const raw=await request.text();if(raw.length>1500000)return reply({error:'Yedek en fazla 1,5 MB olabilir.'},413);
  let body;try{body=JSON.parse(raw);}catch{return reply({error:'JSON geçersiz.'},400);}
  if(!Number.isInteger(body.revision)||body.revision!==saved.revision)return reply({error:'Panel başka bir sekmede değişmiş. Yenileyip tekrar dene.'},409);
  const doc=structuredClone(saved.document);let description='';
  if(body.action==='layout'||body.action==='guide'){
   const kind=body.action,x=body.item;kind==='layout'?checkLayout(x):checkGuide(x);
   const key=kind==='layout'?'layoutOverrides':'guideOverrides';
   const base=await cmsBaseline(),items=mergeRecords(kind==='layout'?base.layouts:base.guides,doc[key]);
   if(kind==='layout')requireValue(!items.some(y=>y.id!==x.id&&!y.hidden&&new URL(y.url).searchParams.get('id')===new URL(x.url).searchParams.get('id')),'Bu düzen zaten kayıtlı.');
   requireValue(items.some(y=>y.id===x.id)||items.length<(kind==='layout'?300:100),'İçerik kapasitesine ulaşıldı.');
   doc[key][x.id]={...x,updated:new Date().toISOString()};description=(kind==='layout'?'Köy: ':'Rehber: ')+(kind==='layout'?x.title:x.title.tr)+(x.hidden?' · yayında değil':' · yayında');
  }else if(body.action==='site'){checkSite(body.site);doc.site=body.site;description='Site ayarları ve sayfa metinleri güncellendi';}
  else if(body.action==='restore'){
   const data=body.backup;requireValue(data?.type==='mahanpro-cms-backup'&&data.version===1&&data.document,'Geçersiz panel yedeği.');
   const d=data.document;checkSite(d.site);
   for(const [key,max,check]of [['layoutOverrides',300,checkLayout],['guideOverrides',100,checkGuide]]){requireValue(d[key]&&typeof d[key]==='object'&&!Array.isArray(d[key])&&Object.keys(d[key]).length<=max,'Yedek içerikleri geçersiz.');for(const [id,x]of Object.entries(d[key])){check(x);requireValue(id===x.id,'Yedek kimlikleri eşleşmiyor.');}doc[key]=d[key];}
   doc.site=d.site;description='Panel yedeği geri yüklendi';
  }else return reply({error:'Bilinmeyen işlem.'},400);
  if(body.action==='restore'){
   const base=await cmsBaseline();const layouts=mergeRecords(base.layouts,doc.layoutOverrides),guides=mergeRecords(base.guides,doc.guideOverrides);
   requireValue(layouts.length<=300&&guides.length<=100,'Yedek içerik kapasitesini aşıyor.');
   const ids=layouts.filter(x=>!x.hidden).map(x=>new URL(x.url).searchParams.get('id'));requireValue(new Set(ids).size===ids.length,'Yedekte yinelenen düzen bağlantıları var.');
  }
  doc.history=[{at:new Date().toISOString(),text:description},...(saved.document.history||[])].slice(0,30);
  const now=new Date().toISOString(),revision=saved.revision+1;
  const result=saved.revision===0?await env.DB.prepare('INSERT OR IGNORE INTO admin_content (id,revision,document,updated_at) VALUES (1,?,?,?)').bind(revision,JSON.stringify(doc),now).run():await env.DB.prepare('UPDATE admin_content SET revision=?,document=?,updated_at=? WHERE id=1 AND revision=?').bind(revision,JSON.stringify(doc),now,saved.revision).run();
  if(!result.meta?.changes)return reply({error:'Eşzamanlı değişiklik var. Yenileyip tekrar dene.'},409);
  return reply({ok:true,revision,updatedAt:now});
 }catch(error){console.error('CMS request failed',error.message);return reply({error:error.message?.includes('SQL')?'İçerik servisine ulaşılamıyor.':error.message||'İşlem tamamlanamadı.'},error.message?.includes('SQL')?503:400);}
}

export {contentRoute};
