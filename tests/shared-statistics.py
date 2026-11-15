from playwright.sync_api import sync_playwright
import uuid,json,os,shutil
base=os.environ.get('MAHAN_STATS_URL','https://mahanpro-layout-stats.aerialmandrill32.chatgpt.site').rstrip('/')
preview=os.environ.get('VILLAGEFORGE_QA_URL','http://127.0.0.1:8765/')
key='qa-'+uuid.uuid4().hex;one=str(uuid.uuid4());two=str(uuid.uuid4())
with sync_playwright() as p:
 b=p.chromium.launch(executable_path=os.environ.get('BROWSER_BIN')or shutil.which('chromium')or shutil.which('google-chrome'),args=['--no-sandbox'])
 c1=b.new_context(ignore_https_errors=os.environ.get('MAHAN_QA_IGNORE_HTTPS_ERRORS')=='1');c2=b.new_context(ignore_https_errors=os.environ.get('MAHAN_QA_IGNORE_HTTPS_ERRORS')=='1');a=c1.new_page();z=c2.new_page();a.goto(preview);z.goto(preview)
 def event(page,visitor,kind,active=None):
  body={'id':key,'visitor':visitor,'type':kind}
  if active is not None:body['active']=active
  result=page.evaluate('''async ({base,body})=>{const r=await fetch(base+'/api/events',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});return {status:r.status,data:await r.json()};}''',{'base':base,'body':body});assert result['status']==200,result;return result['data']['stats']
 assert event(a,one,'view')['views']==1
 assert event(a,one,'view')['views']==1
 assert event(z,two,'view')['views']==2
 assert event(a,one,'copy')['downloads']==1
 assert event(a,one,'copy')['downloads']==1
 assert event(a,one,'favorite',True)['favorites']==1
 assert event(a,one,'favorite',True)['favorites']==1
 assert event(z,two,'favorite',True)['favorites']==2
 assert event(a,one,'favorite',False)['favorites']==1
 got=z.evaluate('''async({base,key})=>{const r=await fetch(base+'/api/stats?ids='+key);return await r.json();}''',{'base':base,'key':key})['stats'][key]
 assert got=={'views':2,'downloads':1,'favorites':1},got
 print(json.dumps({'cross_device_counts':got,'duplicate_events':'deduplicated','unfavorite':'decrements','cors':'passed','database':'persistent shared D1'},ensure_ascii=False))
 c1.close();c2.close();b.close()
