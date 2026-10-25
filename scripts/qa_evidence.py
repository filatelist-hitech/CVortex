#!/usr/bin/env python3
"""Deterministic PR #46 accounting, coverage and Git-safe evidence integrity."""
import argparse
import collections
import gzip
import hashlib
import json
from pathlib import Path, PurePosixPath
import re
import sys
import subprocess
import xml.etree.ElementTree as ET

PACKAGE = Path('docs/09-QA/evidence/full-2026-10-11')
QA = Path('docs/09-QA')
STATUSES = ('PASS', 'FAIL', 'BLOCKED', 'NOT_RUN')
REPORTS = ('FULL-FUNCTIONAL-AUDIT-2026-10-11.md', 'FUNCTIONAL-COVERAGE-MATRIX.md',
           'FUNCTIONAL-DEFECT-REGISTER.md', 'PR46-REMEDIATION.md')


def strict_json(path):
    def pairs(items):
        result = {}
        for key, value in items:
            if key in result:
                raise ValueError(f'duplicate JSON key: {key}')
            result[key] = value
        return result
    return json.loads(path.read_text(), object_pairs_hook=pairs)


def dump(value):
    return json.dumps(value, ensure_ascii=False, indent=2) + '\n'


def safe_file(root, name):
    path = PurePosixPath(name)
    if path.is_absolute() or '..' in path.parts or path.as_posix() != name:
        raise ValueError(f'unsafe path: {name}')
    target = root / name
    if target.is_symlink() or not target.resolve().is_relative_to(root.resolve()) or not target.is_file():
        raise ValueError(f'missing or unsafe file: {name}')
    return target


def classify_career(log, exit_code):
    if exit_code is None or log is None:
        return 'NOT_RUN', 'UNEXECUTED'
    if exit_code == 124:
        return 'BLOCKED', 'TIMEOUT'
    if re.search(r'\b\w*(?:Exception|Error)\b|career-review-verifier: FAIL|final verifier failed', log):
        return 'FAIL', 'VERIFIER_EXCEPTION'
    if exit_code != 0:
        return 'FAIL', 'EXECUTION_FAILURE'
    markers = re.findall(r'^verified: (?:one confirmed transition and one PASS Claim|(?:confirm|reject) transition and valid Claim state)$', log, re.M)
    if len(markers) != 1:
        return 'BLOCKED', 'INCOMPLETE_FINAL_VERIFICATION'
    return 'PASS', 'SUCCESSFUL_FINAL_VERIFICATION'


def counts(rows, weight='count', status='result'):
    totals = dict.fromkeys(STATUSES, 0)
    seen = set()
    for row in rows:
        if not isinstance(row['id'], str) or not row['id'] or row['id'] in seen:
            raise ValueError('missing/duplicate record identity')
        seen.add(row['id'])
        value = row.get(weight, 1)
        if type(value) is not int or value < 1 or row[status] not in STATUSES:
            raise ValueError(f'invalid status/weight: {row["id"]}')
        totals[row[status]] += value
    return totals


def junit_cases(package):
    canonical = {}
    for name in ('qa-sqlite.xml', 'qa-postgres.xml', 'qa-added.xml'):
        cases = ET.parse(package / name).findall('.//testcase')
        seen = set()
        for case in cases:
            identity = case.get('classname') + ':' + case.get('name')
            if identity in seen:
                raise ValueError(f'duplicate JUnit identity in {name}: {identity}')
            seen.add(identity)
            status = ('FAIL' if case.find('failure') is not None or case.find('error') is not None
                      else 'BLOCKED' if case.find('skipped') is not None else 'PASS')
            if name == 'qa-added.xml':
                key = 'QA-TRUTH:' + case.get('name')
            else:
                key = 'PHPUNIT:' + identity
            if name == 'qa-postgres.xml' and key not in canonical:
                raise ValueError('PostgreSQL case has no canonical SQLite identity')
            canonical[key] = (status, name)
    return canonical


def derive(root):
    package = root / PACKAGE
    ledger = strict_json(package / 'scenario-ledger.json')
    rows = ledger['scenarios']
    checks = strict_json(package / 'checks.json')
    check_ids = set()
    row_index = {row['id']: row for row in rows}
    for check in checks:
        if check['id'] in check_ids:
            raise ValueError('duplicate command identity')
        check_ids.add(check['id'])
        safe_file(package, check['log'])
        row = row_index['CONTROL:' + check['id']]
        if check['id'] == 'career-review-postgres-concurrency':
            row['result'], row['outcome_kind'] = classify_career(
                (package / check['log']).read_text(), check['exit_code'])
            row['note'] = ('Historical final verifier exception; wrapper exit 0/PASS is insufficient. '
                           'New remediation execution is recorded separately; historical FAIL retained.')
    totals = counts(rows)
    cases = junit_cases(package)
    actual_cases = {r['id']: (r['result'], r['evidence']) for r in rows
                    if r['id'].startswith(('PHPUNIT:', 'QA-TRUTH:'))}
    if set(actual_cases) != set(cases):
        raise ValueError('ledger/JUnit identities differ')
    for identity, (status, proof) in cases.items():
        actual, evidence = actual_cases[identity]
        if actual != status or proof not in evidence:
            raise ValueError(f'ledger/JUnit result mismatch: {identity}')
    ledger['summary'].update(total=sum(totals.values()), results=totals,
                             records=len(rows), weighted_units=sum(totals.values()))
    inventory = strict_json(package / 'function-inventory.json')
    functions = inventory['functions']
    function_totals = counts(functions)
    inventory['summary'].update(total=len(functions), checked=sum(f['checked'] for f in functions),
                                result_counts=function_totals)
    named_executed = sum(status != 'BLOCKED' for status, _ in cases.values())
    metrics = {
        'scope': 'Historical audit only; remediation tests/runs are separate and never inflate these totals.',
        'records': len(rows), 'weighted_units': sum(totals.values()), 'weighted_results': totals,
        'unique_named_test_identities': len(cases), 'executed_named_tests': named_executed,
        'named_test_results': dict(collections.Counter(s for s, _ in cases.values())),
        'frontend_reported_test_cases': row_index['VITEST:9-suites']['count'],
        'frontend_unique_named_identities': None,
        'frontend_limitation': '108 reported cases in 9 suites; individual identities were not exported.',
        'executed_weighted_units': totals['PASS'] + totals['FAIL'],
        'unique_browser_scenarios': ledger['summary']['unique_original_browser'],
        'inventory': inventory['summary'],
    }
    return ledger, inventory, metrics


def validate_inventory(root, inventory):
    package = root / PACKAGE
    suite_cases = ET.parse(package / 'qa-sqlite.xml').findall('.//testcase')
    suites = {re.split(r'[.\\]', c.get('classname'))[-1] for c in suite_cases}
    for function in inventory['functions']:
        for field in ('implementation', 'entry_points', 'tests', 'evidence_files', 'coverage_status', 'limitations'):
            if field not in function:
                raise ValueError(f'inventory missing {field}: {function["id"]}')
        for reference in function['implementation'] + function.get('ui_files', []) + function['tests']:
            safe_file(root, reference)
        for reference in function['evidence_files']:
            safe_file(package, reference)
        for suite in function.get('executed_suites', []):
            if suite not in suites:
                raise ValueError(f'no execution evidence for suite: {suite}')
        if function['result'] == 'PASS' and not function['evidence_files']:
            raise ValueError('PASS without evidence')
    routes = strict_json(package / 'routes.json')
    expected = {r['method'] + ' ' + r['uri'] for r in routes
                if r['uri'].startswith('api/v1/') and '_diagnostics-test' not in r['uri']}
    mapped = inventory['route_mapping']
    if set(mapped) != expected:
        raise ValueError(f'API route mapping drift: {sorted(expected ^ set(mapped))}')
    ids = {f['id'] for f in inventory['functions']}
    for endpoint, features in mapped.items():
        if not features or not set(features) <= ids:
            raise ValueError(f'invalid route mapping: {endpoint}')
        for identity in features:
            function = next(f for f in inventory['functions'] if f['id'] == identity)
            if endpoint not in function['entry_points']:
                raise ValueError(f'route/function entry mismatch: {endpoint}: {identity}')
    # Bind the saved route inventory to actual source, including grouped prefixes.
    source = (root / 'apps/backend/routes/api.php').read_text()
    literals = re.findall(r"(?:Route::|->)(get|post|patch|delete)\('([^']+)'", source)
    live = set()
    for method, literal in literals:
        if '_diagnostics-test' in literal or literal in ('/settings', '/test-connection'):
            continue
        suffix = literal.lstrip('/')
        if suffix.startswith('connections'):
            suffix = 'chatgpt/' + suffix
        live.add(('GET|HEAD' if method == 'get' else method.upper()) + ' api/v1/' + suffix)
    if live != expected:
        raise ValueError('saved route inventory differs from source routes')
    for page, features in inventory['page_mapping'].items():
        safe_file(root, page)
        if not features or not set(features) <= ids:
            raise ValueError('unmapped frontend page')
    actual_pages = {p.relative_to(root).as_posix() for p in (root / 'apps/frontend/src/app').rglob('page.tsx')
                    if p.parent.name != 'settings'}
    if set(inventory['page_mapping']) != actual_pages:
        raise ValueError('frontend page mapping drift')


def expected_files(root):
    files = {p.relative_to(root).as_posix() for p in (root / PACKAGE).iterdir()
             if p.is_file() and p.name != 'evidence-manifest.json'}
    files.update((QA / name).as_posix() for name in REPORTS)
    return files


def make_manifest(root):
    files = {}
    for name in sorted(expected_files(root)):
        data = safe_file(root, name).read_bytes()
        # Root .gitattributes normalizes CRLF. Refuse pre-clean bytes instead of
        # silently hashing content that Git will change during staging.
        if Path(name).suffix != '.png' and b'\r\n' in data:
            raise ValueError(f'pre-export CRLF must be explicitly normalized: {name}')
        files[name] = hashlib.sha256(data).hexdigest()
    return {'baseline_sha': strict_json(root / PACKAGE / 'baseline.json')['sha'],
            'note': 'Hashes cover published LF-normalized bytes; original/export transformations are recorded in export-provenance.json. Manifest excludes itself.',
            'files': files}


def verify_manifest(root, manifest, expected):
    if set(manifest['files']) != set(expected):
        raise ValueError('unlisted/unexpected manifest payload')
    for name, digest in manifest['files'].items():
        if not re.fullmatch(r'[0-9a-f]{64}', digest):
            raise ValueError('invalid SHA-256 digest')
        if hashlib.sha256(safe_file(root, name).read_bytes()).hexdigest() != digest:
            raise ValueError(f'hash mismatch: {name}')


def verify_index(root, manifest):
    for name, digest in manifest['files'].items():
        result = subprocess.run(['git', 'show', ':' + name], cwd=root, capture_output=True)
        if result.returncode or hashlib.sha256(result.stdout).hexdigest() != digest:
            raise ValueError(f'staged Git blob hash mismatch: {name}')


def coverage_text(inventory, metrics):
    summary = inventory['summary']
    lines = ['---', 'title: Functional coverage matrix', 'status: changes-required',
             'owner: project', 'created: 2026-10-11', 'updated: 2026-10-12',
             'tags: [qa, coverage]', '---', '', '# Functional coverage matrix', '',
             'Generated by `python3 scripts/qa_evidence.py --write`; JSON inventory is the mapping source.', '',
             f"Inventory: {summary['total']} rows; {summary['checked']} checked. Results: {summary['result_counts']}.", '',
             f"Historical accounting: {metrics['records']} records, {metrics['weighted_units']} weighted units; {metrics['weighted_results']}.", '',
             f"{metrics['unique_named_test_identities']} unique named backend/QA test identities; {metrics['executed_named_tests']} executed. Frontend reports {metrics['frontend_reported_test_cases']} executed cases in 9 suites; individual frontend identities unavailable. Controls repeat suite behavior and are not unique tests.", '',
             'Mappings group routes into workflows; every current API v1 endpoint and frontend page is mapped. Framework OAuth/discovery is grouped under F38–F41 via routes/ai.php. External providers and human acceptance remain separate gaps. Local fake HTTP tests are not live account E2E.', '',
             '| ID | Area | Workflow | Result | Coverage / limitations | Evidence |',
             '|---|---|---|---|---|---|']
    for f in inventory['functions']:
        lines.append('| ' + ' | '.join(str(v).replace('|', '\\|') for v in
                     (f['id'], f['group'], f['function'], f['result'], f['coverage_status'] + '; ' + f['limitations'],
                      ', '.join(f['evidence_files']) or f['evidence'])) + ' |')
    lines += ['', 'Implementation, API/UI, test paths and executed-suite references for every row: [function-inventory.json](evidence/full-2026-10-11/function-inventory.json).',
              'Historical records: [scenario-ledger.json](evidence/full-2026-10-11/scenario-ledger.json). Reproducible metrics: [accounting.json](evidence/full-2026-10-11/accounting.json).',
              'Remediation and reproduction: [PR46-REMEDIATION.md](PR46-REMEDIATION.md).', '']
    return '\n'.join(lines)


def validate_qa002(root):
    package = root / 'docs/09-QA/evidence/qa002-2026-10-12'
    if not package.is_dir():
        return
    for p in package.glob('*.json'):
        strict_json(p)
    log_exports = strict_json(package / 'log-exports.json')
    for item in log_exports:
        for field in ('path', 'raw_gzip', 'raw_sha256', 'export_sha256'):
            if field not in item:
                raise ValueError(f'qa002 log-exports missing {field}')
        target = safe_file(package, item['path'])
        if hashlib.sha256(target.read_bytes()).hexdigest() != item['export_sha256']:
            raise ValueError(f'qa002 export sha mismatch: {item["path"]}')
        raw = safe_file(package, item['raw_gzip'])
        if hashlib.sha256(gzip.decompress(raw.read_bytes())).hexdigest() != item['raw_sha256']:
            raise ValueError(f'qa002 raw gzip sha mismatch: {item["raw_gzip"]}')
    exact_runs = strict_json(package / 'exact-runs.json')
    for run_entry in exact_runs.get('runs', []):
        for field in ('id', 'exit_code', 'result', 'log', 'log_sha256'):
            if field not in run_entry:
                raise ValueError(f'qa002 exact-runs missing {field}')
        if run_entry['result'] == 'PASS' and run_entry['exit_code'] != 0:
            raise ValueError(f'qa002 run {run_entry["id"]} marked PASS with nonzero exit')
        log_file = safe_file(package, run_entry['log'])
        if hashlib.sha256(log_file.read_bytes()).hexdigest() != run_entry['log_sha256']:
            raise ValueError(f'qa002 exact-run log sha mismatch: {run_entry["log"]}')
    preservation = strict_json(package / 'development-preservation.json')
    if not preservation.get('databases'):
        raise ValueError('qa002 development-preservation missing databases')
    readme = package / 'README.md'
    if readme.is_file():
        for link in re.findall(r'\]\(([^)]+)\)', readme.read_text()):
            if not re.match(r'https?://|#', link):
                target = (readme.parent / link.split('#')[0]).resolve()
                if not target.is_relative_to(root.resolve()) or not target.exists():
                    raise ValueError(f'broken/unsafe Markdown link: qa002 README.md: {link}')


def run(root, write=False, index=False):
    package = root / PACKAGE
    for p in package.glob('*.json'):
        strict_json(p)
    for p in package.glob('*.xml'):
        ET.parse(p)
    ledger, inventory, metrics = derive(root)
    run_ids = set()
    for record in strict_json(package / 'remediation-runs.json')['runs']:
        identity = (record['id'], record['time_moscow'])
        if identity in run_ids or record['result'] not in STATUSES:
            raise ValueError('duplicate/invalid remediation execution')
        run_ids.add(identity)
        data = safe_file(package, record['log']).read_bytes()
        if hashlib.sha256(data).hexdigest() != record['log_sha256']:
            raise ValueError('remediation log digest differs from execution record')
        if record['id'] == 'career-race' and classify_career(data.decode(), record['exit_code'])[0] != record['result']:
            raise ValueError('remediation Career result mismatch')
    validate_inventory(root, inventory)
    outputs = {package / 'scenario-ledger.json': dump(ledger),
               package / 'function-inventory.json': dump(inventory),
               package / 'accounting.json': dump(metrics),
               root / QA / 'FUNCTIONAL-COVERAGE-MATRIX.md': coverage_text(inventory, metrics)}
    for path, content in outputs.items():
        if write:
            path.write_text(content)
        elif path.read_text() != content:
            raise ValueError(f'derived artifact drift: {path.name}')
    if write:
        (package / 'evidence-manifest.json').write_text(dump(make_manifest(root)))
    manifest = strict_json(package / 'evidence-manifest.json')
    verify_manifest(root, manifest, expected_files(root))
    if index:
        verify_index(root, manifest)
    validate_qa002(root)
    # References from changed QA docs must resolve inside the repository.
    for path in list((root / QA).glob('*.md')):
        for link in re.findall(r'\]\(([^)]+)\)', path.read_text()):
            if not re.match(r'https?://|#', link):
                target = (path.parent / link.split('#')[0]).resolve()
                if not target.is_relative_to(root.resolve()) or not target.exists():
                    raise ValueError(f'broken/unsafe Markdown link: {path.name}: {link}')
    print(dump({'manifest_files': len(manifest['files']), 'json': len(list(package.glob('*.json'))),
                'xml': len(list(package.glob('*.xml'))), 'accounting': metrics}))


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--root', type=Path, default=Path(__file__).resolve().parents[1])
    parser.add_argument('--write', action='store_true')
    parser.add_argument('--index', action='store_true', help='also verify staged Git blob bytes before commit')
    args = parser.parse_args()
    try:
        run(args.root.resolve(), args.write, args.index)
    except (ValueError, KeyError, TypeError, OSError, ET.ParseError) as error:
        print(f'qa-evidence: FAIL: {error}', file=sys.stderr)
        sys.exit(1)
