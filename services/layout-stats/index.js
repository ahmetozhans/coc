const PUBLIC_ORIGIN='https://ahmetozhans.github.io';
const ID=/^[a-zA-Z0-9_-]{1,100}$/;
const VISITOR=/^[a-f0-9-]{36}$/i;
function headers(origin){return {'Content-Type':'application/json; charset=utf-8','Cache-Control':'no-store','Access-Control-Allow-Origin':origin===PUBLIC_ORIGIN?PUBLIC_ORIGIN:origin?.startsWith('http://127.0.0.1:')?origin:PUBLIC_ORIGIN,'Access-Control-Allow-Methods':'GET, POST, OPTIONS','Access-Control-Allow-Headers':'Content-Type','Vary':'Origin'};}
function respond(value,status=200,origin){return new Response(JSON.stringify(value),{status,headers:headers(origin)});}
async function hash(value){const bytes=await crypto.subtle.digest('SHA-256',new TextEncoder().encode('mahanpro-v1:'+value));return [...new Uint8Array(bytes)].map(x=>x.toString(16).padStart(2,'0')).join('');}
function statsStatement(db,ids){const placeholders=ids.map(()=>'?').join(',');return db.prepare(`WITH keys AS (SELECT layout_id FROM layout_events WHERE layout_id IN (${placeholders}) UNION SELECT layout_id FROM layout_favorites WHERE layout_id IN (${placeholders})), events AS (SELECT layout_id, SUM(CASE WHEN kind='view' THEN 1 ELSE 0 END) AS views, SUM(CASE WHEN kind='copy' THEN 1 ELSE 0 END) AS downloads FROM layout_events WHERE layout_id IN (${placeholders}) GROUP BY layout_id), favorites AS (SELECT layout_id,SUM(active) AS favorites FROM layout_favorites WHERE layout_id IN (${placeholders}) GROUP BY layout_id) SELECT keys.layout_id AS id,COALESCE(events.views,0) AS views,COALESCE(events.downloads,0) AS downloads,COALESCE(favorites.favorites,0) AS favorites FROM keys LEFT JOIN events USING(layout_id) LEFT JOIN favorites USING(layout_id)`).bind(...ids,...ids,...ids,...ids);}
function resultMap(ids,rows){const result=Object.fromEntries(ids.map(id=>[id,{views:0,downloads:0,favorites:0}]));for(const row of rows||[])result[row.id]={views:Number(row.views),downloads:Number(row.downloads),favorites:Number(row.favorites)};return result;}

const CONTENT_ORIGIN='https://raw.githubusercontent.com/ahmetozhans/coc/main/';
const canonical=value=>Array.isArray(value)?'['+value.map(canonical).join(',')+']':value&&typeof value==='object'?'{'+Object.keys(value).sort().map(k=>JSON.stringify(k)+':'+canonical(value[k])).join(',')+'}':JSON.stringify(value);
async function proposalHash(p){const bytes=await crypto.subtle.digest('SHA-256',new TextEncoder().encode(canonical({kind:p.kind,payload:p.payload,imageSha256:p.imageSha256||''})));return [...new Uint8Array(bytes)].map(x=>x.toString(16).padStart(2,'0')).join('');}
async function publishedFile(name){const r=await fetch(CONTENT_ORIGIN+name,{headers:{Accept:'application/json'},cache:'no-store'});if(!r.ok)throw Error('Content unavailable');const text=await r.text();if(text.length>1000000)throw Error('Content too large');return JSON.parse(text);}
async function inbox(){const x=await publishedFile('content-inbox.json');if(x.version!==1||!Array.isArray(x.proposals)||x.proposals.length>100)throw Error('Invalid inbox');const result=[];for(const p of x.proposals){if(!p||!ID.test(p.id)||!['layout','guide'].includes(p.kind)||!p.payload||!/^[a-f0-9]{64}$/.test(p.fingerprint)||await proposalHash(p)!==p.fingerprint)continue;result.push(p);}return result;}
async function reviewRoute(request,env,url){
 const origin=request.headers.get('Origin');
 const reply=(value,status=200)=>respond(value,status,origin);
 if(url.pathname==='/admin')return Response.redirect(PUBLIC_ORIGIN+'/coc/admin.html#suggestions',302);
 if(!env.DB)return reply({error:'Review storage unavailable'},503);
 try{
  if(url.pathname==='/api/reviews'&&request.method==='GET'){
   const proposals=await inbox();const reviews=await env.DB.prepare('SELECT proposal_id,fingerprint,decision,reviewed_at FROM content_reviews').all();const byId=new Map((reviews.results||[]).map(x=>[x.proposal_id,x]));
   return reply({proposals:proposals.map(p=>{const r=byId.get(p.id);return {...p,status:p.publication?.status==='published'&&p.publication?.fingerprint===p.fingerprint?'published':r?.fingerprint===p.fingerprint?r.decision:'pending',reviewedAt:r?.fingerprint===p.fingerprint?r.reviewed_at:null};})});
  }
  if(url.pathname==='/api/reviews'&&request.method==='POST'){
   if((![PUBLIC_ORIGIN,url.origin].includes(origin)&&!/^http:\/\/127\.0\.0\.1:\d+$/.test(origin||''))||!request.headers.get('Content-Type')?.startsWith('application/json'))return reply({error:'Invalid request origin'},403);
   const text=await request.text();if(text.length>4096)return reply({error:'Request too large'},413);let body;try{body=JSON.parse(text);}catch{return reply({error:'Invalid JSON'},400);}
   if(!ID.test(body.id)||!['approved','rejected'].includes(body.decision)||!/^[a-f0-9]{64}$/.test(body.fingerprint||''))return reply({error:'Invalid decision'},400);
   const p=(await inbox()).find(x=>x.id===body.id);if(!p||p.fingerprint!==body.fingerprint)return reply({error:'Suggestion changed; reload and review it again'},409);
   const catalog=await publishedFile(p.kind==='layout'?'catalog.json':'guides.json');if((catalog.layouts||catalog.guides||[]).some(x=>x.id===p.payload.id&&x.approvalFingerprint===p.fingerprint))return reply({error:'This version is already being published'},409);
   await env.DB.prepare('INSERT INTO content_reviews (proposal_id,fingerprint,decision,reviewer_id,reviewed_at) VALUES (?,?,?,?,?) ON CONFLICT(proposal_id) DO UPDATE SET fingerprint=excluded.fingerprint,decision=excluded.decision,reviewer_id=excluded.reviewer_id,reviewed_at=excluded.reviewed_at').bind(p.id,p.fingerprint,body.decision,'public-admin',new Date().toISOString()).run();
   return reply({ok:true,id:p.id,decision:body.decision});
  }
  return reply({error:'Not found'},404);
 }catch(error){console.error('Content review failed',error.message);return reply({error:'Suggestions could not be loaded. Please try again.'},503);}
}

export default {
 async fetch(request,env){
  const origin=request.headers.get('Origin');const url=new URL(request.url);
  if(origin&&origin!==PUBLIC_ORIGIN&&!/^http:\/\/127\.0\.0\.1:\d+$/.test(origin))return respond({error:'Origin not allowed'},403,origin);
  if(request.method==='OPTIONS')return new Response(null,{status:204,headers:headers(origin)});
  if(url.pathname==='/admin'||url.pathname==='/api/reviews')return reviewRoute(request,env,url);
  if(url.pathname==='/')return respond({service:'Mahanpro shared layout statistics',status:'ok'},200,origin);
  if(!env.DB)return respond({error:'Database unavailable'},503,origin);
  try{
   if(url.pathname==='/api/stats'&&request.method==='GET'){
    const ids=[...new Set((url.searchParams.get('ids')||'').split(',').filter(Boolean))];if(!ids.length||ids.length>50||ids.some(x=>!ID.test(x)))return respond({error:'Invalid layout ids'},400,origin);
    const rows=await statsStatement(env.DB,ids).all();return respond({stats:resultMap(ids,rows.results)},200,origin);
   }
   if(url.pathname==='/api/events'&&request.method==='POST'){
    if(Number(request.headers.get('Content-Length'))>4096)return respond({error:'Request too large'},413,origin);
    const raw=await request.text();if(raw.length>4096)return respond({error:'Request too large'},413,origin);let body;try{body=JSON.parse(raw);}catch{return respond({error:'Invalid JSON'},400,origin);}
    const {id,visitor,type,active}=body;
    if(typeof id!=='string'||!ID.test(id)||typeof visitor!=='string'||!VISITOR.test(visitor)||!['view','copy','favorite'].includes(type)||(type==='favorite'&&typeof active!=='boolean'))return respond({error:'Invalid event'},400,origin);
    const visitorHash=await hash(visitor);const now=Date.now();const day=new Date(now).toISOString().slice(0,10);
    const total=await env.DB.prepare('SELECT COUNT(*) AS n FROM layout_events WHERE visitor_hash=? AND created_at>?').bind(visitorHash,now-86400000).first();if(Number(total?.n)>500)return respond({error:'Too many events'},429,origin);
    let statement;
    if(type==='favorite')statement=env.DB.prepare('INSERT INTO layout_favorites (layout_id,visitor_hash,active,updated_at) VALUES (?,?,?,?) ON CONFLICT(layout_id,visitor_hash) DO UPDATE SET active=excluded.active,updated_at=excluded.updated_at').bind(id,visitorHash,active?1:0,now);
    else {const bucket=type==='view'?day:String(Math.floor(now/3600000));statement=env.DB.prepare('INSERT OR IGNORE INTO layout_events (layout_id,visitor_hash,kind,bucket,created_at) VALUES (?,?,?,?,?)').bind(id,visitorHash,type,bucket,now);}
    const results=await env.DB.batch([statement,statsStatement(env.DB,[id])]);return respond({id,stats:resultMap([id],results[1].results)[id]},200,origin);
   }
   return respond({error:'Not found'},404,origin);
  }catch(error){console.error('Statistics request failed',error.message);return respond({error:'Statistics temporarily unavailable'},503,origin);}
 }
};
