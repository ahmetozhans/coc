import { integrateStudioCollector } from './studio-collector-integration.mjs';
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
  const schema = await readFile(join(source, 'cpanel/database/schema.sql'), 'utf8');
  const adminSchema = await readFile(join(source, 'cpanel/database/admin-login.sql'), 'utf8');
  await writeFile(join(privateDir, 'schema.sql'), schema + '\n' + adminSchema);
  await cp(join(source, 'assets'), join(web, 'assets'), { recursive: true });
  for (const file of ['favicon.svg', 'mahanpro-night.css', 'i18n.js', 'admin-studio.css']) {
    await cp(join(source, file), join(web, file));
  }
  for (const file of ['catalog.json', 'guides.json', 'content-inbox.json', 'site-defaults.json']) {
    await cp(join(source, file), join(privateDir, file));
  }
  const app = await readFile(join(source, 'app.js'), 'utf8');
  await writeFile(join(web, 'app.js'), app
    .replaceAll('attacks.html#guide-', '/attacks#guide-')
    .replace('Number(x.th)>18', 'Number(x.th)>19')
    .replace('return {...provenance(x),id:', 'return {...provenance(x),detailUrl:x.detailUrl,id:')
    .replace('<div class="layout-actions">', '<div class="layout-actions"><a class="button small" href="\'+esc(/^\\/bases\\/[a-z0-9-]+--[a-f0-9]{12}$/.test(x.detailUrl||\'\')?x.detailUrl:\'/bases/\'+encodeURIComponent(x.id))+\'">\'+(I18n.language===\'tr\'?\'Düzeni incele\':\'View layout\')+\'</a>'));
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
    .replace(/src="admin\.js\?[^"]+"/, 'src="admin.js?v=base-seo"')
    .replace(/href="index\.html"/g, 'href="/"'));
  const admin = await readFile(join(source, 'admin.js'), 'utf8');
  await writeFile(join(web, 'admin.js'), integrateStudioCollector(admin
    .replace('https://mahanpro-layout-stats.aerialmandrill32.chatgpt.site', '')
    .replace('Array.from({length:16},', 'Array.from({length:17},')
    .replace("area('editDescription','Açıklama',x.description,160,2)", "area('editDescription','Detay sayfası açıklaması ve savunma notları',x.description,1200,5)")
    .replace("headers:{'Content-Type':'application/json'}", "headers:{'Content-Type':'application/json','X-Mahanpro-CSRF':document.querySelector('meta[name=mahanpro-csrf]').content}"), await readFile(join(source, 'cpanel/studio-collector.js'), 'utf8')));
  for (const name of ['index', 'bases', 'attacks', 'armies']) {
    let html = await readFile(join(source, name + '.html'), 'utf8');
    html = html
      .replace(/href="(index|bases|clan|attacks|armies|upgrade)\.html(?=["?#])/g,
        (_, page) => 'href="/' + (page === 'index' ? '' : page));
    if (name === 'bases') {
      const filterGroup = /(<div class="filters" id="publicFilters"[^>]*>)([\s\S]*?)(<\/div>)/;
      if (!filterGroup.test(html)) throw new Error('Town Hall filter region missing');
      html = html.replace(filterGroup, (_, open, children, close) => {
        const all = children.match(/<button\b[^>]*data-public-th="all"[^>]*>[\s\S]*?<\/button>/)?.[0];
        if (!all) throw new Error('All Town Halls control missing');
        const castleLevelsByTownHall = [1, 2, 2, 3, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15];
        const levels = castleLevelsByTownHall.map((castleLevel, index) => {
          const level = index + 3;
          const latest = level === 19 ? ' castle-filter--latest' : '';
          return '<button type="button" class="filter castle-filter' + latest + '" data-public-th="' + level +
            '" aria-pressed="false"><span class="castle-filter__icon"><img src="/assets/clan-castles/level-' + castleLevel +
            '.webp" width="32" height="32" alt=""></span><span>TH' + level + '</span></button>';
        });
        return open.replace('class="filters"', 'class="filters castle-filters"') + all + levels.join('') + close;
      }).replace('</head>', '<link rel="stylesheet" href="/th-filters.css?v=2"></head>')
        .replace(/src="app\.js\?[^"]+"/, 'src="app.js?v=base-seo"');
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
