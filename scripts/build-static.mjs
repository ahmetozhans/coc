import { cp, mkdir, mkdtemp, readFile, rename, rm, rmdir, writeFile } from 'node:fs/promises';
import { basename, dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const [address, destination, ...extra] = process.argv.slice(2);
if (!address || !destination || extra.length) {
  throw new Error('Usage: node scripts/build-static.mjs https://example.com/ path/to/new-output');
}

const publicUrl = new URL(address);
if (publicUrl.protocol !== 'https:' || publicUrl.username || publicUrl.password ||
    publicUrl.search || publicUrl.hash || /[<>"'&]/.test(publicUrl.href)) {
  throw new Error('Supply the final HTTPS site base URL without credentials, query or fragment.');
}
if (!publicUrl.pathname.endsWith('/')) publicUrl.pathname += '/';

const root = fileURLToPath(new URL('../', import.meta.url));
const output = resolve(destination);
const oldBase = 'https://ahmetozhans.github.io/coc/';
const publicFiles = [
  'index.html', 'bases.html', 'attacks.html', 'armies.html', 'clan.html', 'upgrade.html',
  'admin.html', 'admin-studio.css', 'admin.js', 'review-inbox.js', 'app.js', 'i18n.js',
  'metrics.js', 'site-content.js', 'mahanpro-night.css', 'favicon.svg', 'catalog.json',
  'guides.json', 'site-defaults.json', 'content-inbox.json', 'robots.txt', 'sitemap.xml',
];
const urlFiles = new Set([...publicFiles.filter(name => name.endsWith('.html')), 'robots.txt', 'sitemap.xml']);

await mkdir(dirname(output), { recursive: true });
// Reserve the destination so an existing deployment cannot be overwritten.
await mkdir(output);
let stage;
try {
  stage = await mkdtemp(join(dirname(output), '.mahanpro-'));
  for (const name of publicFiles) {
    const target = join(stage, name);
    if (urlFiles.has(name)) {
      const source = await readFile(join(root, name), 'utf8');
      await writeFile(target, source.replaceAll(oldBase, () => publicUrl.href));
    } else {
      await cp(join(root, name), target);
    }
  }
  await cp(join(root, 'assets'), join(stage, 'assets'), { recursive: true });
  await rename(stage, output);
  stage = undefined;
} catch (error) {
  await rmdir(output);
  throw error;
} finally {
  if (stage) await rm(stage, { recursive: true, force: true });
}
console.log(`Built ${basename(output)} for ${publicUrl.href}`);
