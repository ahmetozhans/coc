import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
import {JSDOM} from 'jsdom';
import {integrateStudioCollector} from '../scripts/studio-collector-integration.mjs';

const source = await readFile(new URL('../admin.js', import.meta.url), 'utf8');
const runtime = await readFile(new URL('../cpanel/studio-collector.js', import.meta.url), 'utf8');
const integrated = integrateStudioCollector(source, runtime);
assert.equal(integrateStudioCollector(integrated, runtime), integrated);
const documentSource = await readFile(new URL('../admin.html', import.meta.url), 'utf8');
const defaults = JSON.parse(await readFile(new URL('../site-defaults.json', import.meta.url), 'utf8'));
const dom = new JSDOM(documentSource, {url:'https://mahanpro.fun/studio#collector', runScripts:'outside-only'});
const {window} = dom;
window.scrollTo = () => {};
window.AbortSignal = globalThis.AbortSignal;
window.confirm = () => true;
const posted = [];
window.fetch = async (input, options = {}) => {
  const url = new URL(input, window.location.origin);
  let text;
  if (url.pathname === '/api/admin/content') {
    return {ok:true,json:async()=>({revision:0,document:{site:{name:'Mahanpro',texts:{},pages:{}},history:[]},
      layouts:[],guides:[],stats:{},defaults})};
  }
  if (url.pathname === '/mahanpro-setup.php') {
    text = options.method === 'POST'
      ? '<main class="setup-shell"><section class="panel"><p data-collector-installed>Installed</p></section></main>'
      : '<main class="setup-shell"><section class="panel"><h1>Kurulum</h1><form method="post"><input name="csrf" value="token"><button>Kurulumu tamamla</button></form></section></main>';
  } else if (url.pathname === '/admin/collector') {
    if (options.method === 'POST') posted.push(options.body.get('action'));
    text = '<body class="collector-page"><main><h1>Otomatik Köy Toplama</h1><form id="test-review"><input name="action" value="publish"><button>Onayla</button></form><a href="?view=settings">Ayarlar</a><img src="?image=1"></main></body>';
  } else {
    throw new Error('Unexpected request ' + url.href);
  }
  return {ok:true,url:url.href,text:async()=>text};
};
const settle = () => new Promise(resolve => setTimeout(resolve, 20));
window.eval(integrated);
await settle();
const panel = window.document.getElementById('studioCollectorPanel');
assert.ok(!panel.hidden);
assert.match(panel.textContent, /Kurulumu tamamla/);
assert.equal(window.location.pathname, '/studio');
panel.querySelector('form').dispatchEvent(new window.SubmitEvent('submit', {bubbles:true,cancelable:true}));
await settle();
assert.match(panel.textContent, /Onayla/);
assert.equal(panel.querySelector('img').src, 'https://mahanpro.fun/admin/collector?image=1');
panel.querySelector('form').dispatchEvent(new window.SubmitEvent('submit', {bubbles:true,cancelable:true}));
await settle();
assert.deepEqual(posted, ['publish']);
window.document.querySelector('[data-section=layouts]').click();
assert.ok(panel.hidden);
assert.ok(!window.document.querySelector('[data-panel=layouts]').hidden);
window.document.querySelector('[data-section=collector]').click();
assert.ok(!panel.hidden);
assert.equal(window.location.pathname, '/studio');
assert.equal(window.document.querySelectorAll('[data-section=collector]').length, 1);
window.close();
console.log('PASS Studio-native setup, inline queue POST, relative URLs, integration idempotency and existing navigation.');
