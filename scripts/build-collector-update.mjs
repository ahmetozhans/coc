import { editsFromDiff } from './installer-text-edits.mjs';
import {mkdir, readFile, writeFile, cp} from 'node:fs/promises';
import {resolve, join} from 'node:path';
import {execFileSync} from 'node:child_process';
import {createHash} from 'node:crypto';

const [destination] = process.argv.slice(2);
if (!destination) throw new Error('Usage: node scripts/build-collector-update.mjs NEW_DIRECTORY');
const output = resolve(destination);
await mkdir(output);
const root = resolve(new URL('..', import.meta.url).pathname);
const base = 'a9734253859c3f7143c59555c3007a96d5ba39b3';
const shared = [
  'cpanel/public/index.php', 'cpanel/public/layout-detail.js', 'cpanel/public/layout-detail.css', 'cpanel/private/cms.php', 'cpanel/private/content.php',
  'cpanel/private/page-not-found.html', 'cpanel/private/reviews.php', 'cpanel/private/layout-detail.php', 'cpanel/private/publish-approved.php',
];
const added = [
  'cpanel/private/site-shell.php', 'cpanel/private/page-retired.html', 'cpanel/public/site-navigation.css',
  'cpanel/private/public-english.php', 'cpanel/private/public-english.json', 'cpanel/public/collector-admin.css', 'cpanel/public/studio-collector.css', 'cpanel/private/collector.php', 'cpanel/private/collector-sources.php',
  'cpanel/private/collector-admin.php', 'cpanel/private/collector-cron.php',
];
const targetPath = path => path.replace('cpanel/public/', 'public_html/').replace('cpanel/private/', 'mahanpro-private/');
const sha = bytes => createHash('sha256').update(bytes).digest('hex');
const manifest = [];

for (const path of [...shared, ...added]) {
  const next = await readFile(join(root, path));
  const prior = shared.includes(path) ? execFileSync('git', ['show', base + ':' + path], {cwd: root}) : null;
  const target = targetPath(path);
  await mkdir(join(output, 'files', target, '..'), {recursive: true});
  await writeFile(join(output, 'files', target), next);
  const entry = {path: target, before: prior ? sha(prior) : null, after: sha(next)};
  if (prior) {
    await mkdir(join(output, 'reference', target, '..'), {recursive: true});
    await writeFile(join(output, 'reference', target), prior);
    let diff;
    try {
      diff = execFileSync('git', ['diff', '--no-index', '--unified=3', '--', join(output, 'reference', target), join(root, path)],
        {encoding: 'utf8'});
    } catch (error) {
      if (error.status !== 1) throw error;
      diff = error.stdout;
    }
    entry.edits = editsFromDiff(diff);
  }
  manifest.push(entry);
}
await writeFile(join(output, 'manifest.json'), JSON.stringify(manifest, null, 2));
await cp(join(root, 'cpanel/database/migrations/20261118-layout-collector.sql'), join(output, '20261118-layout-collector.sql'));
await cp(join(root, 'cpanel/COLLECTOR-INSTALL.md'), join(output, 'KURULUM.md'));
await cp(join(root, 'cpanel/COLLECTOR-TEST-REPORT.md'), join(output, 'TEST-REPORT.md'));
await cp(join(root, 'cpanel/collector-installer-core.php'), join(output, 'collector-installer-core.php'));
await cp(join(root, 'cpanel/collector-install.php'), join(output, 'collector-install.php'));
console.log(output);
