import { cp, mkdir, mkdtemp, readFile, rename, rm, rmdir, writeFile } from 'node:fs/promises';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const [destination, ...extra] = process.argv.slice(2);
if (!destination || extra.length) throw new Error('Usage: node scripts/build-cpanel-php.mjs NEW_DIRECTORY');
const source = fileURLToPath(new URL('../', import.meta.url));
const output = resolve(destination);
await mkdir(dirname(output), { recursive: true });
await mkdir(output);
let stage;
try {
  stage = await mkdtemp(join(dirname(output), '.mahanpro-php-'));
  const web = join(stage, 'public_html');
  const privateDir = join(stage, 'mahanpro-private');
  await cp(join(source, 'cpanel/public'), web, { recursive: true });
  await cp(join(source, 'cpanel/private'), privateDir, { recursive: true });
  await cp(join(source, 'cpanel/database/schema.sql'), join(privateDir, 'schema.sql'));
  await cp(join(source, 'assets'), join(web, 'assets'), { recursive: true });
  for (const file of ['favicon.svg', 'mahanpro-night.css', 'i18n.js', 'admin-studio.css']) {
    await cp(join(source, file), join(web, file));
  }
  for (const file of ['catalog.json', 'guides.json', 'content-inbox.json', 'site-defaults.json']) {
    await cp(join(source, file), join(privateDir, file));
  }
  const app = await readFile(join(source, 'app.js'), 'utf8');
  await writeFile(join(web, 'app.js'), app.replaceAll('attacks.html#guide-', '/attacks#guide-'));
  const content = await readFile(join(source, 'site-content.js'), 'utf8');
  await writeFile(join(web, 'site-content.js'), content.replace(
    'https://mahanpro-layout-stats.aerialmandrill32.chatgpt.site/api/content', '/api/content'
  ).replace("a.getAttribute('href')===file", "a.getAttribute('href')===(key==='home'?'/':'/'+key)"));
  const metrics = await readFile(join(source, 'metrics.js'), 'utf8');
  await writeFile(join(web, 'metrics.js'), metrics.replace(
    'https://mahanpro-layout-stats.aerialmandrill32.chatgpt.site', ''
  ));
  await mkdir(join(privateDir, 'templates'));
  const studio = await readFile(join(source, 'admin.html'), 'utf8');
  await writeFile(join(privateDir, 'templates', 'studio.html'), studio
    .replace('</head>', '<!-- STUDIO_CSRF --></head>')
    .replace(/<section data-panel="suggestions"[\s\S]*?<\/section>/,
      '<section data-panel="suggestions" id="suggestions" hidden><h1>İçerik önerileri</h1><p><a class="button primary" href="/admin">Önerileri incele</a></p></section>')
    .replace(/<script src="review-inbox\.js[^"]*" defer><\/script>/, '')
    .replace(/href="index\.html"/g, 'href="/"'));
  const admin = await readFile(join(source, 'admin.js'), 'utf8');
  await writeFile(join(web, 'admin.js'), admin
    .replace('https://mahanpro-layout-stats.aerialmandrill32.chatgpt.site', '')
    .replace("headers:{'Content-Type':'application/json'}", "headers:{'Content-Type':'application/json','X-Mahanpro-CSRF':document.querySelector('meta[name=mahanpro-csrf]').content}"));
  for (const name of ['index', 'bases', 'clan', 'attacks', 'armies', 'upgrade']) {
    let html = await readFile(join(source, name + '.html'), 'utf8');
    html = html
      .replace(/href="(index|bases|clan|attacks|armies|upgrade)\.html(?=["?#])/g,
        (_, page) => 'href="/' + (page === 'index' ? '' : page));
    if (name === 'bases') {
      html = html.replace(/(<div\b[^>]*\bid="publicGallery"[^>]*>)<div class="empty">.*?<\/div>/s,
        '$1<!-- MAHANPRO_CONTENT -->');
    }
    if (name === 'clan') {
      html = html.replace(' Live statistics are not connected.</p>', '</p>')
        .replace('</body>', '<script src="/clan-live.js?v=1" defer></script></body>');
    }
    if (name === 'attacks' || name === 'armies') {
      html = html.replace(/(<div class="guide-grid" id="guideGallery">).*?(<\/div>(?:<section class="panel practice-panel">|<\/div><\/main>))/s,
        '$1<!-- MAHANPRO_CONTENT -->$2');
    }
    if (['bases', 'attacks', 'armies'].includes(name) && !html.includes('<!-- MAHANPRO_CONTENT -->')) {
      throw new Error('Content region missing in ' + name);
    }
    await writeFile(join(privateDir, 'templates', name + '.html'), html);
  }
  await rename(stage, output);
  stage = undefined;
} catch (error) {
  await rmdir(output);
  throw error;
} finally {
  if (stage) await rm(stage, { recursive: true, force: true });
}
console.log('Built public_html and mahanpro-private under ' + output);
