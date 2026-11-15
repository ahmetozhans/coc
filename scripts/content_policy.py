"""Validation and approval gate shared by the unattended content task."""
import hashlib,json,re
from urllib.parse import urlparse,parse_qs

def fingerprint(proposal):
    body={'kind':proposal['kind'],'payload':proposal['payload'],'imageSha256':proposal.get('imageSha256','')}
    return hashlib.sha256(json.dumps(body,ensure_ascii=False,sort_keys=True,separators=(',',':')).encode()).hexdigest()

def approved_proposals(inbox,decisions):
    decisions={r['proposal_id']:r for r in decisions}
    return [p for p in inbox['proposals'] if (r:=decisions.get(p['id'])) and r['decision']=='approved' and not(p.get('publication',{}).get('status')=='published' and p.get('publication',{}).get('fingerprint')==p.get('fingerprint')) and r['fingerprint']==p.get('fingerprint')==fingerprint(p)]

def valid_layout(x,proposal=False):
    assert isinstance(x,dict) and re.fullmatch(r'[A-Za-z0-9_-]{1,100}',x['id'])
    assert isinstance(x['th'],int) and 3<=x['th']<=18
    assert x['type'] in ['War','Trophy','Farming','Hybrid']
    assert isinstance(x['title'],str) and 0<len(x['title'].strip())<=70
    u=urlparse(x['url']);q=parse_qs(u.query)
    assert u.scheme=='https' and u.hostname=='link.clashofclans.com' and not u.username and not u.password
    assert q.get('action')==['OpenLayout'] and q.get('id',[''])[0].startswith(f"TH{x['th']}:")
    image=urlparse(x['image']);assert image.scheme=='https' and not image.username and not image.password
    if not proposal:assert image.hostname=='ahmetozhans.github.io' and image.path.startswith('/coc/assets/layouts/')
    return q['id'][0]

def valid_guide(x):
    assert re.fullmatch(r'[A-Za-z0-9_-]{1,100}',x['id']) and x['category'] in ['Air','Ground','War']
    assert isinstance(x['townHalls'],list) and x['townHalls'] and all(isinstance(n,int) and 3<=n<=18 for n in x['townHalls'])
    for lang in ['en','tr']:
        assert isinstance(x['title'][lang],str) and 0<len(x['title'][lang].strip())<=100
        assert isinstance(x['summary'][lang],str) and 0<len(x['summary'][lang].strip())<=500
        assert isinstance(x['steps'][lang],list) and 2<=len(x['steps'][lang])<=8
        assert all(isinstance(v,str) and 0<len(v.strip())<=350 for v in x['steps'][lang])
    assert len(x['steps']['tr'])==len(x['steps']['en'])
