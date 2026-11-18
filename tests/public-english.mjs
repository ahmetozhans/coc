import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
import {JSDOM} from 'jsdom';
const source = await readFile(new URL('../i18n.js', import.meta.url), 'utf8');
for (const page of ['home','bases','clan','armies','attacks','upgrade']) {
  const dom = new JSDOM('<html lang="tr"><body data-page="' + page + '"><div class="header-actions"><label for="language">Dil</label><select id="language"><option value="tr">Türkçe</option></select></div><p>Gece Köyü</p></body></html>', {
    url:'https://mahanpro.fun/' + page, runScripts:'outside-only',
  });
  Object.defineProperty(dom.window.navigator, 'language', {value:'tr-TR'});
  dom.window.localStorage.setItem('vf-language', 'tr');
  dom.window.eval(source + '\nwindow.testLanguage=I18n;');
  assert.equal(dom.window.testLanguage.language, 'en');
  assert.equal(dom.window.document.documentElement.lang, 'en');
  assert.equal(dom.window.document.getElementById('language'), null);
  assert.match(dom.window.document.body.textContent, /Night Village/);
  dom.window.testLanguage.setLanguage('tr');
  assert.equal(dom.window.testLanguage.language, 'en');

}
const admin = new JSDOM('<html lang="tr"><body data-page="admin"><p>Genel bakış</p></body></html>', {
  url:'https://mahanpro.fun/admin',runScripts:'outside-only',
});
admin.window.localStorage.setItem('vf-language','tr');
admin.window.eval(source + '\nwindow.testLanguage=I18n;');
assert.equal(admin.window.testLanguage.language,'tr');
assert.match(admin.window.document.body.textContent,/Genel bakış/);

console.log('PASS six public pages ignore Turkish browser/storage preferences and remove language selection; admin retains Turkish.');
