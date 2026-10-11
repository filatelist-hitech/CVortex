"""Versioned B01 promotion audit. Historical assertions/baselines stay unchanged."""
from pathlib import Path
import hashlib, json, subprocess, datetime
root = Path(__file__).resolve().parents[5]
out = Path(__file__).resolve().parent
ref = root / 'docs/07-Design/reference'
candidate = root / 'docs/07-Design/candidates/b01-frontend-redesign'
archive = root / 'docs/07-Design/archive/b01-pre-orbita-2026-10-10'
sha = lambda p: hashlib.sha256(p.read_bytes()).hexdigest()
read = lambda p: json.loads(p.read_text())
before = read(out/'session-before.json')['files']
checks = []
def check(name, value):
    checks.append({'name': name, 'pass': bool(value)})
    assert value, name
# Exact permitted edits; no blanket governance exclusion.
allowed = {
 '.agents/state/STATUS.md': 'B01 completed promotion outcome and remaining gates',
 '.agents/state/BLOCKERS.md': 'Canonical choice resolved; manual review gates remain',
 '.agents/tasks/application-workspace-reference-foundation.md': 'Explicit owner promotion and bounded closure scope',
 'docs/07-Design/Design-Foundation.md': 'Current canonical readiness',
 'docs/07-Design/Design-Handoff.md': 'Single active mapping and review readiness',
 'docs/07-Design/candidates/b01-frontend-redesign/README.md': 'Historical source promotion pointer',
 'docs/07-Design/candidates/b01-frontend-redesign/REVIEW.md': 'Append superseding direction decision',
 'docs/07-Design/candidates/b01-frontend-redesign/manifest.json': 'Promotion pointer; generated hashes remain historical',
}
for name in ['README.md','REVIEW.md','index.html','app.js','style.css','tokens.css','manifest.json','build-reference.cjs','preview-server.cjs','validate-browser.cjs']:
    allowed['docs/07-Design/reference/'+name] = 'Authorized canonical promotion, derivation/validation or review record'
actual_diffs=[]
for file, old in before.items():
    current=sha(root/file) if (root/file).is_file() else None
    if current!=old:
        check('Authorized session difference: '+file,file in allowed)
        actual_diffs.append({'file':file,'before':old,'after':current,'reason':allowed[file]})
    else:
        check('Session protected unchanged: '+file,True)
preexisting_allowed={
 'docs/07-Design/reference/REVIEW.md': 'Existing exact canonical owner question/review-preparation record',
 'docs/07-Design/reference/manifest.json': 'Canonical manifest drift corrected before this block',
 'docs/07-Design/Design-Handoff.md': 'Existing scope-limited candidate and personal-preview approval',
 '.agents/tasks/application-workspace-reference-foundation.md': 'Existing task review scope recorded',
 '.agents/tasks/README.md': 'Existing B02 specification pointer',
 '.gitignore': 'Existing private personal-preview .local exclusion',
}
historical=[]
for name in ['protected-before.json','typography/protected-before.json','apple-protected-before.json']:
    baseline=read(candidate/'validation'/name)
    differences=[]
    for file, old in baseline.items():
        start=before.get(file)
        current=sha(root/file) if (root/file).is_file() else None
        if start!=old:
            check('Known preexisting governance difference: '+file,file in preexisting_allowed)
        if current!=old:
            check('Authorized historical difference: '+file,file in allowed or (file in preexisting_allowed and current==start))
            differences.append({'file':file,'historical':old,'sessionStart':start,'current':current,'preexisting':start!=old,'reason':allowed.get(file,preexisting_allowed.get(file))})
        else: check('Historical protected unchanged: '+name+': '+file,True)
    historical.append({'baseline':name,'baselineSha256':sha(candidate/'validation'/name),'files':len(baseline),'strictHistoricalEquality':'FAIL' if differences else 'PASS','authorizedDifferences':differences})
for f,v in read(out/'archive-hashes.json').items(): check('Previous draft archive unchanged: '+f,sha(archive/f)==v)
# The historical reference evidence is retained verbatim at its original path too.
for p in (archive/'validation').rglob('*'):
    if p.is_file(): check('Historical canonical evidence unchanged: '+str(p.relative_to(archive)),sha(ref/p.relative_to(archive))==sha(p))
manifest=read(ref/'manifest.json')
check('Canonical token hash',sha(root/'brand/tokens/cvortex.tokens.json')==manifest['generated']['canonicalTokenSha256']==before['brand/tokens/cvortex.tokens.json'])
source=[]
for f,old in manifest['promotion']['sourceFiles'].items():
    check('Selected source retained: '+f,sha(candidate/f)==old)
    if f=='index.html':
        expected=(candidate/f).read_text().replace('Орбита решения · Кандидат B01 · CVortex','Орбита решения · Canonical B01 · CVortex').replace('Кандидат B01, <b>демо-данные. Ждёт проверки владельцем.</b>','Canonical B01, <b>демо-данные. Направление утверждено; проверка состояний ожидается.</b>')
        check('Only canonical identity/review-status HTML labels changed',(ref/f).read_text()==expected)
    else: check('Byte-identical selected source promotion: '+f,sha(ref/f)==old)
    source.append({'file':f,'selected':old,'canonical':sha(ref/f)})
for p in (candidate/'assets').iterdir(): check('Only selected synthetic/local assets: '+p.name,p.is_file() and sha(ref/'assets'/p.name)==sha(p))
check('No private .local in canonical/archive',not any(p.name=='.local' for base in [ref,archive] for p in base.rglob('*')))
check('Synthetic fixture identity retained',"company: 'Northstar Labs'" in (ref/'app.js').read_text() and "company: 'Cedar Systems'" in (ref/'app.js').read_text())
check('Exactly one active mapping',manifest['isActiveReference'] and not read(candidate/'manifest.json')['isActiveReference'] and not read(root/'docs/07-Design/candidates/b01-personal-preview/manifest.json')['isActiveReference'])
check('No false full review',manifest['lifecycle']=='draft' and manifest['review']['status']=='awaiting-review')
production_diff=subprocess.check_output(['git','diff','HEAD','--','apps','brand','infra','docker-compose.yml','compose.yml','package.json','pnpm-lock.yaml']).decode()
check('No tracked production/token/infrastructure diff',not production_diff)
check('NEXT stays B01',(root/'.agents/state/NEXT.md').read_text().strip()=='application-workspace-reference-foundation')
check('Staged index unchanged',subprocess.check_output(['git','diff','--cached','--name-only']).decode()=='')
subprocess.run(['node',str(ref/'build-reference.cjs'),'--check'],check=True)
subprocess.run(['git','diff','--check'],check=True)
report={'status':'PASS','checkedAt':datetime.datetime.now(datetime.timezone.utc).isoformat(),'scope':'Authorized promotion integrity; strict historical equality is reported separately and remains FAIL where changed','historicalBaselines':historical,'preexistingGovernanceAllowlist':preexisting_allowed,'sessionChanges':actual_diffs,'sessionProtectedFiles':len(before),'archiveFiles':len(read(out/'archive-hashes.json')),'sourceDerivation':source,'checks':checks,'productionUnchanged':True,'canonicalTokenSha256':sha(root/'brand/tokens/cvortex.tokens.json'),'privacyEvidence':'Allowlisted selected synthetic sources/assets copied; exact-source derivation above; no .local copied. This is a scoped source audit, not a universal PII detector.'}
(out/'integrity-results.json').write_text(json.dumps(report,ensure_ascii=False,indent=2)+'\n')
print('Closure integrity: PASS;',len(checks),'assertions;',len(before),'session protected files')
