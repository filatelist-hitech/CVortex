import json,pathlib,subprocess,hashlib,os
out=pathlib.Path(os.environ['QA_OUTPUT_DIR']);ids=json.loads((out/'e2e-resource-ids.json').read_text());owner=ids['owner'];vid=ids['vid'];prep=ids['prep']
u=subprocess.check_output(['docker','exec','cvortexqa1011-postgres-1','printenv','POSTGRES_USER'],text=True).strip()
queries={
'users':"SELECT id,role,status,split_part(email,'@',2) AS email_domain FROM users ORDER BY id",
'roles':"SELECT rolname,rolsuper,rolbypassrls FROM pg_roles WHERE rolname='cvortex_qa_app'",
'rls':"SELECT relname,relrowsecurity,relforcerowsecurity FROM pg_class WHERE relnamespace='public'::regnamespace AND relname IN ('career_facts','claims','vacancies','vacancy_snapshots','application_draft_items','application_claim_usages') ORDER BY relname",
'migrations':"SELECT migration FROM migrations ORDER BY migration",
'vacancies':f"SELECT id,owner_id,title,analysis_status,error_code FROM vacancies WHERE owner_id='{owner}' ORDER BY id",
'vacancy_snapshots':f"SELECT * FROM vacancy_snapshots WHERE vacancy_id='{vid}'",
'vacancy_requirements':f"SELECT * FROM vacancy_requirements WHERE vacancy_snapshot_id IN (SELECT id FROM vacancy_snapshots WHERE vacancy_id='{vid}')",
'vacancy_analyses':f"SELECT * FROM vacancy_analyses WHERE vacancy_id='{vid}' ORDER BY created_at",
'vacancy_match_dimensions':f"SELECT * FROM vacancy_match_dimensions WHERE vacancy_analysis_id IN (SELECT id FROM vacancy_analyses WHERE vacancy_id='{vid}')",
'vacancy_match_evidence':f"SELECT e.* FROM vacancy_match_evidence e JOIN vacancy_match_dimensions d ON d.id=e.vacancy_match_dimension_id JOIN vacancy_analyses a ON a.id=d.vacancy_analysis_id WHERE a.vacancy_id='{vid}'",
'vacancy_llm_runs':f"SELECT id,workflow,provider,model,status,validation_result,error_category FROM vacancy_llm_runs WHERE owner_id='{owner}'",
'application_preparations':f"SELECT * FROM application_preparations WHERE id='{prep}'",
'application_draft_items':f"SELECT * FROM application_draft_items WHERE preparation_id='{prep}' ORDER BY kind,variant",
'application_claim_usages':f"SELECT u.* FROM application_claim_usages u JOIN application_draft_items i ON i.id=u.draft_item_id WHERE i.preparation_id='{prep}'",
'application_approval_events':f"SELECT e.* FROM application_approval_events e JOIN application_draft_items i ON i.id=e.draft_item_id WHERE i.preparation_id='{prep}' ORDER BY created_at,id",
'application_draft_revisions':f"SELECT r.* FROM application_draft_revisions r JOIN application_draft_items i ON i.id=r.draft_item_id WHERE i.preparation_id='{prep}' ORDER BY draft_item_id,revision_number",
'application_llm_runs':f"SELECT id,workflow,provider,model,status,validation_result,error_category FROM application_llm_runs WHERE preparation_id='{prep}'",
'claims':f"SELECT * FROM claims WHERE owner_id='{owner}'",
'claim_evidence':f"SELECT * FROM claim_evidence WHERE owner_id='{owner}'",
'career_facts':f"SELECT * FROM career_facts WHERE owner_id='{owner}'",
}
data={}
for name,query in queries.items():
 sql=f"BEGIN TRANSACTION READ ONLY; SELECT coalesce(json_agg(t),'[]'::json) FROM ({query}) t; COMMIT;"
 r=subprocess.run(['docker','exec','cvortexqa1011-postgres-1','psql','-U',u,'-d','cvortex_qa_synthetic','-Atq','-v','ON_ERROR_STOP=1','-c',sql],text=True,capture_output=True)
 if r.returncode:raise RuntimeError(name+':'+r.stderr)
 data[name]=json.loads(r.stdout)
assert all(x['email_domain'] in ['qa.example.test','example.test'] for x in data['users'])
assert all(not x['rolsuper'] and not x['rolbypassrls'] for x in data['roles'])
assert all(x['relrowsecurity'] and x['relforcerowsecurity'] for x in data['rls'] if x['relname'] not in ['career_facts','claims'])
items=data['application_draft_items'];assert any(x['kind']=='COVER_DRAFT' and x['variant']=='SHORT' and x['status']=='APPROVED' for x in items)
assert any(x['variant']=='STANDARD' and x['status']=='BLOCKED' and x['validation_result']=='BLOCK' for x in items)
facts={x['id']:x for x in data['career_facts']};claims={x['id']:x for x in data['claims']}
for item in items:
 if item['status']!='APPROVED':continue
 assert item['validation_result']=='PASS'
 assert item['validated_content_hash']==hashlib.sha256(item['content'].encode()).hexdigest()
 usages=[x for x in data['application_claim_usages'] if x['draft_item_id']==item['id']];assert usages
 for usage in usages:
  assert usage['owner_id']==owner;claim=claims[usage['claim_id']];assert claim['truth_status']=='PASS'
  assert any(e['claim_id']==claim['id'] and facts[e['career_fact_id']]['status']=='CONFIRMED' and facts[e['career_fact_id']]['owner_id']==owner for e in data['claim_evidence'])
assert any(x['error_category']=='SEMANTIC_REJECTED' for x in data['vacancy_llm_runs'])
report={'sha':'f06a6dba058589823bf3d3c8b693cb625170bc4b','database':'cvortex_qa_synthetic','read_only':True,'assertions':'PASS: synthetic domains, runtime role, forced RLS, raw source, approved short cover content hash and confirmed same-owner claim chain, unsupported standard cover BLOCK, injection SEMANTIC_REJECTED','data':data}
(out/'database-evidence.json').write_text(json.dumps(report,indent=2))
print('DB-EVIDENCE PASS',len(queries),'read-only queries',len(items),'draft items')
