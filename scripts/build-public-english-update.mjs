import {mkdir, mkdtemp, readFile, writeFile, rm, cp} from 'node:fs/promises';
import {join, resolve} from 'node:path';
import {tmpdir} from 'node:os';
import {execFileSync} from 'node:child_process';
import {createHash} from 'node:crypto';
import {editsFromDiff, applyTextEdits} from './installer-text-edits.mjs';

const [destination, liveAssetsDirectory] = process.argv.slice(2);
if (!destination || !liveAssetsDirectory) {
  throw new Error('Usage: node scripts/build-public-english-update.mjs NEW_DIRECTORY LIVE_ASSETS_DIRECTORY');
}
const root = resolve(new URL('..', import.meta.url).pathname);
const output = resolve(destination);
const live = resolve(liveAssetsDirectory);
const stage = await mkdtemp(join(tmpdir(), 'mahanpro-english-'));
const sha = bytes => createHash('sha256').update(bytes).digest('hex');
try {
  execFileSync(process.execPath, [join(root, 'scripts/build-collector-easy-update.mjs'), output, join(live, 'admin.js')]);
  const oldSource = join(stage, 'old-source');
  await mkdir(oldSource);
  const archive = execFileSync('git', ['archive', '529d064cff5fdaf8eafdb346eb978902052278f0'], {cwd: root, maxBuffer: 30000000});
  execFileSync('tar', ['-x', '-C', oldSource], {input: archive});
  const oldSite = join(stage, 'old-site');
  const newSite = join(stage, 'new-site');
  execFileSync(process.execPath, [join(oldSource, 'scripts/build-cpanel-php.mjs'), oldSite]);
  execFileSync(process.execPath, [join(root, 'scripts/build-cpanel-php.mjs'), newSite]);
  const payload = join(output, 'mahanpro-private/collector-setup/payload');
  const manifest = JSON.parse(await readFile(join(payload, 'manifest.json'), 'utf8'));
  for (const entry of manifest.filter(item => item.before !== null)) {
    const sourcePath = entry.path.replace('public_html/', 'cpanel/public/').replace('mahanpro-private/', 'cpanel/private/');
    let previous;
    try {
      previous = execFileSync('git', ['show', '529d064cff5fdaf8eafdb346eb978902052278f0:' + sourcePath], {cwd: root});
    } catch {
      continue;
    }
    const reference = join(stage, 'installed-reference');
    await writeFile(reference, previous);
    let diff;
    try {
      diff = execFileSync('git', ['diff', '--no-index', '--unified=3', '--', reference, join(payload, 'files', entry.path)], {encoding: 'utf8'});
    } catch (error) {
      if (error.status !== 1) throw error;
      diff = error.stdout;
    }
    const edits = editsFromDiff(diff);
    if (edits.length) entry.alternateEdits = [edits];
  }
  for (const name of ['i18n.js', 'site-content.js', 'app.js']) {
    const before = await readFile(join(live, name), 'utf8');
    const prior = join(oldSite, 'public_html', name);
    const next = join(newSite, 'public_html', name);
    let diff;
    try {
      diff = execFileSync('git', ['diff', '--no-index', '--unified=3', '--', prior, next], {encoding: 'utf8'});
    } catch (error) {
      if (error.status !== 1) throw error;
      diff = error.stdout;
    }
    const edits = editsFromDiff(diff);
    const updated = before === await readFile(prior, 'utf8')
      ? await readFile(next, 'utf8') : applyTextEdits(before, edits);
    const path = 'public_html/' + name;
    await writeFile(join(payload, 'files', path), updated);
    await writeFile(join(payload, 'reference', path), before);
    manifest.push({path, before: sha(before), after: sha(updated), edits});
  }
  await writeFile(join(payload, 'manifest.json'), JSON.stringify(manifest, null, 2));
  const sql = await readFile(join(payload, '20261118-layout-collector.sql'));
  await writeFile(join(payload, 'release.sha256'), sha(Buffer.concat([Buffer.from(JSON.stringify(manifest, null, 2)), sql])) + '\n');
  await cp(join(root, 'cpanel/PUBLIC-ENGLISH.md'), join(output, 'INGILIZCE.md'));
} finally {
  await rm(stage, {recursive: true, force: true});
}
console.log(output);
