const PUBLIC_ORIGIN='https://ahmetozhans.github.io';
const ID=/^[a-zA-Z0-9_-]{1,100}$/;
const VISITOR=/^[a-f0-9-]{36}$/i;
function headers(origin){return {'Content-Type':'application/json; charset=utf-8','Cache-Control':'no-store','Access-Control-Allow-Origin':origin===PUBLIC_ORIGIN?PUBLIC_ORIGIN:origin?.startsWith('http://127.0.0.1:')?origin:PUBLIC_ORIGIN,'Access-Control-Allow-Methods':'GET, POST, OPTIONS','Access-Control-Allow-Headers':'Content-Type','Vary':'Origin'};}
function respond(value,status=200,origin){return new Response(JSON.stringify(value),{status,headers:headers(origin)});}
async function hash(value){const bytes=await crypto.subtle.digest('SHA-256',new TextEncoder().encode('mahanpro-v1:'+value));return [...new Uint8Array(bytes)].map(x=>x.toString(16).padStart(2,'0')).join('');}
function statsStatement(db,ids){const placeholders=ids.map(()=>'?').join(',');return db.prepare(`WITH keys AS (SELECT layout_id FROM layout_events WHERE layout_id IN (${placeholders}) UNION SELECT layout_id FROM layout_favorites WHERE layout_id IN (${placeholders})), events AS (SELECT layout_id, SUM(CASE WHEN kind='view' THEN 1 ELSE 0 END) AS views, SUM(CASE WHEN kind='copy' THEN 1 ELSE 0 END) AS downloads FROM layout_events WHERE layout_id IN (${placeholders}) GROUP BY layout_id), favorites AS (SELECT layout_id,SUM(active) AS favorites FROM layout_favorites WHERE layout_id IN (${placeholders}) GROUP BY layout_id) SELECT keys.layout_id AS id,COALESCE(events.views,0) AS views,COALESCE(events.downloads,0) AS downloads,COALESCE(favorites.favorites,0) AS favorites FROM keys LEFT JOIN events USING(layout_id) LEFT JOIN favorites USING(layout_id)`).bind(...ids,...ids,...ids,...ids);}
function resultMap(ids,rows){const result=Object.fromEntries(ids.map(id=>[id,{views:0,downloads:0,favorites:0}]));for(const row of rows||[])result[row.id]={views:Number(row.views),downloads:Number(row.downloads),favorites:Number(row.favorites)};return result;}
export default {
 async fetch(request,env){
  const origin=request.headers.get('Origin');const url=new URL(request.url);
  if(origin&&origin!==PUBLIC_ORIGIN&&!/^http:\/\/127\.0\.0\.1:\d+$/.test(origin))return respond({error:'Origin not allowed'},403,origin);
  if(request.method==='OPTIONS')return new Response(null,{status:204,headers:headers(origin)});
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
