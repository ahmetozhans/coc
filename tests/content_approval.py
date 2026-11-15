import sys,json,copy,unittest
from pathlib import Path
root=Path(__file__).resolve().parent.parent;sys.path.insert(0,str(root/'scripts'))
from content_policy import approved_proposals
class ApprovalGate(unittest.TestCase):
 def setUp(self):self.inbox=json.loads((root/'content-inbox.json').read_text());self.p=self.inbox['proposals'][0]
 def decision(self,state='approved'):return {'proposal_id':self.p['id'],'decision':state,'fingerprint':self.p['fingerprint']}
 def test_pending_is_never_published(self):self.assertEqual(approved_proposals(self.inbox,[]),[])
 def test_rejected_is_never_published(self):self.assertEqual(approved_proposals(self.inbox,[self.decision('rejected')]),[])
 def test_exact_approved_version_is_selected(self):self.assertEqual(approved_proposals(self.inbox,[self.decision()]),[self.p])
 def test_changed_payload_requires_new_approval(self):
  changed=copy.deepcopy(self.inbox);changed['proposals'][0]['payload']['title']='Changed title';self.assertEqual(approved_proposals(changed,[self.decision()]),[])
 def test_published_version_is_not_republished(self):
  self.p['publication']={'status':'published','fingerprint':self.p['fingerprint']};self.assertEqual(approved_proposals(self.inbox,[self.decision()]),[])
if __name__=='__main__':unittest.main()
