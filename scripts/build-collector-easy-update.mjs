import { integrateStudioCollector } from './studio-collector-integration.mjs';
import {mkdir, mkdtemp, cp, readFile, writeFile, rm} from 'node:fs/promises';
import {join, resolve} from 'node:path';
import {tmpdir} from 'node:os';
import {execFileSync} from 'node:child_process';
import {createHash} from 'node:crypto';

const [destination, liveStudioScript] = process.argv.slice(2);
if (!destination) throw new Error('Usage: node scripts/build-collector-easy-update.mjs NEW_DIRECTORY');
const root = resolve(new URL('..', import.meta.url).pathname);
const output = resolve(destination);
await mkdir(output);
const stage = await mkdtemp(join(tmpdir(), 'mahanpro-easy-'));
try {
  const payload = join(stage, 'payload');
  execFileSync(process.execPath, [join(root, 'scripts/build-collector-update.mjs'), payload]);
  const setup = join(output, 'mahanpro-private/collector-setup');
  const web = join(output, 'public_html');
  await mkdir(setup, {recursive: true});
  await mkdir(web);
  await cp(payload, join(setup, 'payload'), {recursive: true});
  await cp(join(root, 'cpanel/collector-installer-core.php'), join(setup, 'core.php'));
  await cp(join(root, 'cpanel/collector-setup-page.php'), join(setup, 'page.php'));
  await cp(join(root, 'cpanel/collector-setup-entry.php'), join(web, 'mahanpro-setup.php'));
  const runtime = await readFile(join(root, 'cpanel/studio-collector.js'), 'utf8');
  let admin;
  if (liveStudioScript) {
    admin = await readFile(resolve(liveStudioScript), 'utf8');
  } else {
    const built = join(stage, 'site');
    execFileSync(process.execPath, [join(root, 'scripts/build-cpanel-php.mjs'), built]);
    admin = await readFile(join(built, 'public_html/admin.js'), 'utf8');
  }
  await writeFile(join(web, 'admin.js'), integrateStudioCollector(admin, runtime));
  await cp(join(root, 'cpanel/public/studio-collector.css'), join(web, 'studio-collector.css'));
  await cp(join(root, 'cpanel/collector-setup.css'), join(web, 'mahanpro-setup.css'));
  const sql = await readFile(join(payload, '20261118-layout-collector.sql'));
  await writeFile(join(setup, 'payload/migration.sha256'), createHash('sha256').update(sql).digest('hex') + '\n');
  const manifest = await readFile(join(setup, 'payload/manifest.json'));
  await writeFile(join(setup, 'payload/release.sha256'), createHash('sha256').update(manifest).update(sql).digest('hex') + '\n');
  await cp(join(root, 'cpanel/COLLECTOR-EASY-INSTALL.md'), join(output, 'BASLA.md'));
} finally {
  await rm(stage, {recursive: true, force: true});
}
console.log(output);
