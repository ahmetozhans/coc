'use strict';
const $ = id => document.getElementById(id);
const t = (key, values) => I18n.t(key, values);
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const read = (key, fallback = []) => { try { return JSON.parse(localStorage.getItem(key)) ?? fallback; } catch { return fallback; } };
const put = (key, value) => { try { localStorage.setItem(key, JSON.stringify(value)); return true; } catch { alert(t('Storage is unavailable or full. Export a backup before removing entries.')); return false; } };
const valid = value => { try { const u = new URL(value); return u.protocol === 'https:' && !u.username && !u.password && u.hostname === 'link.clashofclans.com' && u.searchParams.get('action') === 'OpenLayout' && !!u.searchParams.get('id'); } catch { return false; } };
const imageURL = value => { if (!value) return true; try { const u = new URL(value); return u.protocol === 'https:' && !u.username && !u.password; } catch { return false; } };
function layout(x) {
  if (!x || typeof x !== 'object' || !valid(x.url) || !Number.isInteger(Number(x.th)) || Number(x.th)<3 || Number(x.th)>18 || !['War','Farming','Trophy','Hybrid'].includes(x.type)) return null;
  const name = x.title;
  if (typeof name !== 'string' || !name.trim() || name.length > 70) return null;
  const image = typeof x.image === 'string' && imageURL(x.image) ? x.image : '';
  return {id: String(x.id || x.url), name:name.trim(), title:name.trim(), th:Number(x.th), type:x.type, url:new URL(x.url).href, image, description:String(x.description || '').slice(0,160), checked: /^\d{4}-\d{2}-\d{2}$/.test(x.checked || '') ? x.checked : ''};
}
function upgrade(x) {
  if (!x || typeof x.name !== 'string' || !x.name.trim() || x.name.length>70 || !Number.isFinite(Number(x.days)) || Number(x.days)<=0 || Number(x.days)>999 || !Number.isSafeInteger(Number(x.cost)) || Number(x.cost)<0 || !['Gold','Elixir','Dark Elixir'].includes(x.resource)) return null;
  return {id:String(x.id || crypto.randomUUID()),name:x.name.trim(),days:Number(x.days),cost:Number(x.cost),resource:x.resource};
}
const records = (key, normalize) => { const data=read(key); return Array.isArray(data) ? data.map(normalize).filter(Boolean).slice(0,1000) : []; };
function download(name, data) { const url=URL.createObjectURL(new Blob([JSON.stringify(data,null,2)],{type:'application/json'})), a=document.createElement('a'); a.href=url; a.download=name; document.body.append(a); a.click(); a.remove(); setTimeout(()=>URL.revokeObjectURL(url),1000); }
async function backup(input, type) { const file=input.files?.[0]; if(!file || file.size>32*1024*1024) throw Error(t('Choose a JSON backup under 32 MB.')); const data=JSON.parse(await file.text()); if(data.type!==type || !Array.isArray(data.items) || data.items.length>1000) throw Error(t('Invalid backup.')); return data; }
const page=document.body.dataset.page;
if(page==='clan') $('clanForm').onsubmit=e=>{e.preventDefault();const tag=$('clanTag').value.trim().toUpperCase().replace(/^#/,'').replace(/O/g,'0');if(!/^[0289PYLQGRJCUV]{3,15}$/.test(tag)){$('clanResult').textContent=t('Invalid clan tag. Allowed characters: 0289PYLQGRJCUV');return;}const url='https://link.clashofclans.com/en?action=OpenClanProfile&tag='+encodeURIComponent('#'+tag);$('clanResult').innerHTML='<a href="'+esc(url)+'" target="_blank" rel="noopener noreferrer">'+t('Open official clan profile ↗')+'</a><p class="muted">'+t('Live clan statistics are not connected.')+'</p>';};
if(page==='bases') {
  let published=[],publicTh='all',publicSearch='',publicCategory='all',onlyFavorites=false;
  const storedFavorites=read('vf-favorites');let favorites=Array.isArray(storedFavorites)?storedFavorites.filter(x=>typeof x==='string'&&valid(x)):[];
  const fold=s=>s.toLocaleLowerCase(I18n.language==='tr'?'tr':'en');
  const card=x=>'<article class="layout-card"><div class="layout-visual">'+(x.image?'<img loading="lazy" decoding="async" alt="'+esc(x.name)+'" src="'+esc(x.image)+'">':'<span class="fallback" aria-hidden="true">🏰</span>')+'<span class="th">TH '+x.th+'</span></div><div class="layout-body"><span class="chip">'+t(x.type)+'</span><h3>'+esc(x.name)+'</h3><p>'+esc(x.description||t('Official Clash of Clans layout link'))+'</p>'+(x.checked?'<p class="muted">'+t('Checked: {date}',{date:esc(x.checked)})+'</p>':'')+'<div class="layout-actions"><a class="button primary small" href="'+esc(x.url)+'" target="_blank" rel="noopener noreferrer">'+t('Copy to game ↗')+'</a><button class="button small" data-copy="'+esc(x.url)+'">'+t('Copy link')+'</button><button class="button small" data-favorite="'+esc(x.url)+'" aria-pressed="'+favorites.includes(x.url)+'">'+(favorites.includes(x.url)?t('★ Saved favorite'):t('☆ Favorite'))+'</button>'+'</div></div></article>';
  function render() {
    const items=published.filter(x=>(publicTh==='all'||String(x.th)===publicTh)&&(publicCategory==='all'||x.type===publicCategory)&&fold(x.name+' '+x.description).includes(fold(publicSearch))&&(!onlyFavorites||favorites.includes(x.url)));
    const box=$('publicGallery');$('publicCount').textContent=t('{count} layouts',{count:items.length});
    box.innerHTML=items.length?items.map(card).join(''):'<div class="empty">'+t('No published layouts match your filters.')+'</div>';
    box.querySelectorAll('[data-copy]').forEach(b=>b.onclick=async()=>{try{await navigator.clipboard.writeText(b.dataset.copy);b.textContent=t('Copied ✓');}catch{prompt(t('Copy link'),b.dataset.copy);}});
    box.querySelectorAll('[data-favorite]').forEach(b=>b.onclick=()=>{const url=b.dataset.favorite,next=favorites.includes(url)?favorites.filter(x=>x!==url):[...favorites,url];if(put('vf-favorites',next)){favorites=next;render();}});
  }
  document.querySelectorAll('[data-public-th]').forEach(b=>b.onclick=()=>{publicTh=b.dataset.publicTh;document.querySelectorAll('[data-public-th]').forEach(y=>{y.classList.toggle('active',y===b);y.setAttribute('aria-pressed',String(y===b));});render();});
  $('publicSearch').oninput=e=>{publicSearch=e.target.value.trim();render();};$('publicCategory').onchange=e=>{publicCategory=e.target.value;render();};$('favoritesOnly').onchange=e=>{onlyFavorites=e.target.checked;render();};
  fetch('catalog.json',{cache:'no-store'}).then(r=>{if(!r.ok)throw Error();return r.json();}).then(o=>{published=Array.isArray(o.layouts)?o.layouts.map(layout).filter(Boolean).slice(0,1000).filter((x,i,a)=>a.findIndex(y=>y.url===x.url)===i):[];render();}).catch(()=>{$('publicGallery').textContent=t('The public catalog could not be loaded. Please reload.');});
  document.addEventListener('languagechange',render);render();
}
if(page==='upgrade') {
  let items=records('vf-upgrades-v3',upgrade);const savedBuilders=Number(read('vf-builders',5));$('builders').value=Number.isInteger(savedBuilders)&&savedBuilders>=1&&savedBuilders<=6?String(savedBuilders):'5';
  function commit(next){if(!put('vf-upgrades-v3',next))return false;items=next;render();return true;}
  function render(){const n=Number($('builders').value),loads=Array(n).fill(0),queues=Array.from({length:n},()=>[]),costs={};[...items].sort((a,b)=>b.days-a.days).forEach(x=>{const i=loads.indexOf(Math.min(...loads));loads[i]+=x.days;queues[i].push(x);costs[x.resource]=(costs[x.resource]||0)+x.cost;});const duration=Math.max(0,...loads);$('totalDays').textContent=t('{days} days',{days:duration.toFixed(2)});$('totalCount').textContent=items.length;$('totalCost').textContent=Object.entries(costs).map(([k,v])=>t(k)+': '+v.toLocaleString(I18n.language)).join(' · ')||'0';$('finishDate').textContent=items.length?new Date(Date.now()+duration*86400000).toLocaleDateString(I18n.language==='tr'?'tr-TR':'en-GB'):'—';$('builderTimeline').innerHTML=queues.map((q,i)=>'<div class="builder-lane"><strong>'+t('Builder {number} · {days} days',{number:i+1,days:loads[i].toFixed(2)})+'</strong><div class="builder-track">'+(q.length?q.map(x=>'<span style="flex:'+Math.max(.01,x.days)+'" title="'+esc(x.name)+'">'+esc(x.name)+'</span>').join(''):'<span style="background:transparent">'+t('Available')+'</span>')+'</div></div>').join('');$('upgradeList').innerHTML=items.length?items.map(x=>'<div class="upgrade-row"><div><strong>'+esc(x.name)+'</strong><small>'+t('{days} days',{days:x.days})+' · '+x.cost.toLocaleString(I18n.language)+' '+t(x.resource)+'</small></div><button class="button small danger" data-delete="'+esc(x.id)+'">'+t('Remove')+'</button></div>').join(''):'<div class="notice">'+t('No upgrades added yet.')+'</div>';$('upgradeList').querySelectorAll('[data-delete]').forEach(b=>b.onclick=()=>commit(items.filter(x=>x.id!==b.dataset.delete)));}
  $('upgradeForm').onsubmit=e=>{e.preventDefault();const x=upgrade({id:crypto.randomUUID(),name:$('upgradeName').value.trim(),days:$('days').value,cost:$('cost').value,resource:$('resource').value});if(!x)return;if(items.length>=1000){alert(t('Maximum 1000 entries. Export a backup first.'));return;}if(commit([...items,x])){const builders=$('builders').value;e.target.reset();$('builders').value=builders;render();}};
  $('builders').onchange=()=>{const previous=String(read('vf-builders',5));if(!put('vf-builders',Number($('builders').value)))$('builders').value=previous;render();};
  $('clearUpgrades').onclick=()=>{if(confirm(t('Clear your upgrade queue?')))commit([]);};$('exportUpgrades').onclick=()=>download('villageforge-upgrades.json',{type:'villageforge-upgrades',builders:Number($('builders').value),items});$('importUpgrades').onclick=()=>$('importUpgradesFile').click();
  $('importUpgradesFile').onchange=async e=>{try{const o=await backup(e.target,'villageforge-upgrades'),incoming=o.items.map(x=>upgrade({...x,id:crypto.randomUUID()}));if(incoming.some(x=>!x))throw Error(t('Invalid backup.'));if(items.length+incoming.length>1000)throw Error(t('Maximum 1000 entries. Export a backup first.'));if(commit([...items,...incoming])&&Number.isInteger(o.builders)&&o.builders>=1&&o.builders<=6){if(put('vf-builders',o.builders))$('builders').value=String(o.builders);render();}}catch(err){alert(err.message);}finally{e.target.value='';}};
  document.addEventListener('languagechange',render);render();
}
