import subprocess,json,pathlib,os
p=pathlib.Path(os.environ['QA_OUTPUT_DIR']);ids=json.loads((p/'e2e-resource-ids.json').read_text());fixtures=json.loads((p/'fixture-private.json').read_text());snapshot=json.loads((p/'database-evidence.json').read_text())['data']['vacancy_snapshots'][0]['id'];u=subprocess.check_output(['docker','exec','cvortexqa1011-postgres-1','printenv','POSTGRES_USER'],text=True).strip()
sql=f"""BEGIN;
DO $$ BEGIN
  INSERT INTO vacancy_requirements (id,owner_id,vacancy_snapshot_id,dimension,importance,label,normalized_value,source_excerpt,confidence,extracted_by,candidate_hash,created_at,updated_at)
  VALUES ('000000000000000000000000qa','{fixtures['user-b']}','{snapshot}','TECHNICAL','MANDATORY','Laravel','laravel','Laravel is required.',1,'synthetic','qa-cross-owner-rollback',now(),now());
  RAISE EXCEPTION 'cross-owner insert unexpectedly succeeded';
EXCEPTION WHEN foreign_key_violation THEN RAISE NOTICE 'PASS cross-owner snapshot requirement FK rejects write'; END $$;
ROLLBACK;
BEGIN TRANSACTION READ ONLY;
SET LOCAL ROLE cvortex_qa_app;
SELECT set_config('cvortex.owner_id','{fixtures['user-b']}',true);
SELECT 'user-b-visible-vacancy=' || count(*) FROM vacancies WHERE id='{ids['vid']}';
SELECT 'user-b-visible-preparation=' || count(*) FROM application_preparations WHERE id='{ids['prep']}';
SELECT set_config('cvortex.owner_id','',true);
SELECT 'unset-owner-visible-vacancy=' || count(*) FROM vacancies;
ROLLBACK;
"""
r=subprocess.run(['docker','exec','-i','cvortexqa1011-postgres-1','psql','-U',u,'-d','cvortex_qa_synthetic','-At','-v','ON_ERROR_STOP=1'],input=sql,text=True,capture_output=True)
assert r.returncode==0,r.stderr
assert 'PASS cross-owner' in r.stderr
assert all(x in r.stdout for x in ['user-b-visible-vacancy=0','user-b-visible-preparation=0','unset-owner-visible-vacancy=0'])
(p/'pg-constraint-probe.json').write_text(json.dumps({'sha':'f06a6dba058589823bf3d3c8b693cb625170bc4b','result':'PASS','sql':sql,'stdout':r.stdout,'stderr':r.stderr,'committed_mutations':False,'scope':'DB FK and runtime RLS supplement; exact RefreshDatabase PHPUnit test not rerun'},indent=2));print('PG FK / RLS negative probe PASS; transactions rolled back')
