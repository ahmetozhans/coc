from playwright.sync_api import sync_playwright
import json, os, shutil
from pathlib import Path
base=os.environ.get('VILLAGEFORGE_QA_URL','http://127.0.0.1:8765/').rstrip('/')+'/'
screenshots=Path(os.environ.get('VILLAGEFORGE_QA_SCREENSHOTS','/tmp/villageforge-qa'))
screenshots.mkdir(parents=True,exist_ok=True)
errors=[];checks=[]
with sync_playwright() as p:
 browser=p.chromium.launch(executable_path=os.environ.get('BROWSER_BIN') or shutil.which('chromium') or shutil.which('google-chrome'),headless=True,args=['--no-sandbox','--disable-dev-shm-usage'])
 for width in [360,390,430,768,1024,1440]:
  for lang in ['tr','en']:
   ctx=browser.new_context(viewport={'width':width,'height':900},device_scale_factor=1,is_mobile=width<768,has_touch=width<768)
   ctx.add_init_script("localStorage.setItem('vf-language', '"+lang+"')")
   for file in ['index','bases','clan','attacks','upgrade','admin']:
    page=ctx.new_page();page.on('pageerror',lambda e:errors.append(str(e)))
    page.goto(base+file+'.html');page.wait_for_load_state('networkidle')
    assert page.locator('html').get_attribute('lang')==lang,(file,lang)
    overflow=page.evaluate('document.documentElement.scrollWidth > innerWidth + 1')
    if overflow:
     bad=page.evaluate("[...document.querySelectorAll('body *')].filter(e=>{const r=e.getBoundingClientRect();return r.width&&r.right>innerWidth+1&&getComputedStyle(e).position!=='fixed'&&!e.closest('.filters')}).map(e=>({tag:e.tagName,cls:e.className,id:e.id,w:e.getBoundingClientRect().width,right:e.getBoundingClientRect().right})).slice(0,15)")
     errors.append({'file':file,'width':width,'lang':lang,'overflow':bad})
    if width==390 and lang=='tr':page.screenshot(path=str(screenshots/('night-'+file+'-mobile.png')),full_page=True)
    if width==1440 and lang=='tr' and file=='index':page.screenshot(path=str(screenshots/'night-home-desktop.png'),full_page=True)
    if file=='admin':
     for section in ['layouts','publish','settings']:
      page.locator('[data-studio="'+section+'"]').click();assert page.locator('#'+section).is_visible()
      if page.evaluate('document.documentElement.scrollWidth > innerWidth + 1'):errors.append({'file':'admin/'+section,'width':width,'lang':lang,'overflow':True})
      if width==390 and lang=='tr' and section=='layouts':page.screenshot(path=str(screenshots/'night-admin-editor-mobile.png'),full_page=True)
    checks.append((file,width,lang));page.close()
   ctx.close()
 # Actual interactions on phone.
 ctx=browser.new_context(viewport={'width':390,'height':844},is_mobile=True,has_touch=True)
 page=ctx.new_page();page.goto(base+'upgrade.html');page.locator('#language').select_option('tr');page.locator('#builders').select_option('2');page.locator('#upgradeName').fill('Okçu Kraliçe');page.locator('#days').fill('4');page.locator('#cost').fill('2500000');page.locator('#upgradeForm button').click();assert page.locator('#builders').input_value()=='2';assert page.locator('#totalCount').inner_text()=='1';page.reload();assert page.locator('#builders').input_value()=='2';assert page.locator('#totalCount').inner_text()=='1'
 page.goto(base+'clan.html');page.locator('#clanTag').fill('#2PP');page.locator('#clanForm button').click();assert 'OpenClanProfile' in page.locator('#clanResult a').get_attribute('href')
 page.goto(base+'admin.html');page.locator('[data-studio="layouts"]').click();page.locator('#layoutTitle').fill('QA düzeni');page.locator('#layoutUrl').fill('https://link.clashofclans.com/en?action=OpenLayout&id=qa');page.locator('#layoutReady').check();page.locator('#adminLayoutForm button[type=submit]').click();assert page.locator('#metricTotal').inner_text()=='1';assert page.locator('[data-edit]').count()==1
 page.goto(base+'bases.html');assert page.locator('#publicGallery').get_by_text('QA düzeni',exact=True).count()==0;assert page.locator('#baseForm').count()==0
 ctx.close();browser.close()
print(json.dumps({'viewports_checked':len(checks),'interactions':'planner persistence, language switch, clan link, admin creation, public isolation','errors':errors},ensure_ascii=False,indent=2))
assert not errors
