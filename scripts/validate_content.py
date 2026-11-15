from pathlib import Path
import json
from content_policy import fingerprint,valid_layout,valid_guide
root=Path(__file__).resolve().parent.parent
catalog=json.loads((root/'catalog.json').read_text());guides=json.loads((root/'guides.json').read_text());inbox=json.loads((root/'content-inbox.json').read_text())
assert catalog['version']==guides['version']==inbox['version']==1
links=[valid_layout(x)for x in catalog['layouts']];assert len(links)==len(set(links))
for x in guides['guides']:valid_guide(x)
assert len({x['id']for x in guides['guides']})==len(guides['guides'])
assert len(inbox['proposals'])<=100 and len({p['id']for p in inbox['proposals']})==len(inbox['proposals'])
for p in inbox['proposals']:
 assert p['kind'] in ['layout','guide'] and fingerprint(p)==p['fingerprint']
 if p['kind']=='layout':valid_layout(p['payload'],True);assert len(p['imageSha256'])==64
 else:valid_guide(p['payload'])
print(f"PASS: {len(links)} published layouts, {len(guides['guides'])} published guides, {len(inbox['proposals'])} valid review suggestions")
