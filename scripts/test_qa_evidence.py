import copy
import json
from pathlib import Path
import shutil
import subprocess
import sys
import tempfile
import unittest

import qa_evidence as qa


class EvidenceRegressionTest(unittest.TestCase):
    def test_historical_exception_overrides_zero_exit_and_pass_banner(self):
        log = 'RuntimeException\nReview race did not preserve one confirmed fact and one valid Claim.\ncareer-review-postgres-concurrency: PASS'
        self.assertEqual(qa.classify_career(log, 0), ('FAIL', 'VERIFIER_EXCEPTION'))
        for error in ('LogicException', 'TypeError', 'PDOException'):
            self.assertEqual(qa.classify_career(error + '\nverified: confirm transition and valid Claim state', 0)[0], 'FAIL')

    def test_success_requires_final_verification(self):
        for log in ('', 'career-review-postgres-concurrency: PASS', 'verified: confirm transition and valid Claim state\n' * 2):
            self.assertEqual(qa.classify_career(log, 0)[0], 'BLOCKED')
        for action in ('confirm', 'reject'):
            self.assertEqual(qa.classify_career(f'verified: {action} transition and valid Claim state', 0)[0], 'PASS')
        self.assertEqual(qa.classify_career(None, None), ('NOT_RUN', 'UNEXECUTED'))
        self.assertEqual(qa.classify_career('', 124), ('BLOCKED', 'TIMEOUT'))
        self.assertEqual(qa.classify_career('', 1)[0], 'FAIL')

    def test_duplicate_or_invalid_accounting_is_rejected(self):
        row = {'id': 'test', 'count': 1, 'result': 'PASS'}
        for rows in ([row, row], [{**row, 'count': True}], [{**row, 'result': 'UNKNOWN'}]):
            with self.assertRaises(ValueError):
                qa.counts(rows)

    def test_duplicate_json_keys_are_rejected(self):
        with tempfile.TemporaryDirectory() as tmp:
            path = Path(tmp) / 'duplicate.json'
            path.write_text('{"a":1,"a":2}')
            with self.assertRaises(ValueError):
                qa.strict_json(path)

    def clone_package(self, tmp):
        source = Path(__file__).resolve().parents[1]
        root = Path(tmp)
        shutil.copytree(source / qa.QA, root / qa.QA)
        inv = qa.strict_json(source / qa.PACKAGE / 'function-inventory.json')
        refs = {'apps/backend/routes/api.php', *inv['page_mapping'],
                '.agents/tasks/b01-current-manifest-integrity-reconciliation.md'}
        for function in inv['functions']:
            refs.update(function['implementation'] + function['tests'] + function['ui_files'])
        for name in refs:
            destination = root / name
            destination.parent.mkdir(parents=True, exist_ok=True)
            shutil.copyfile(source / name, destination)
        return root

    def test_manifest_missing_corrupt_and_unlisted_files_exit_nonzero(self):
        for mutation in ('hash', 'missing', 'unlisted'):
            with self.subTest(mutation=mutation), tempfile.TemporaryDirectory() as tmp:
                root = self.clone_package(tmp)
                baseline = subprocess.run([sys.executable, str(Path(qa.__file__)), '--root', str(root)], capture_output=True)
                self.assertEqual(baseline.returncode, 0, baseline.stderr.decode())
                target = root / qa.PACKAGE / 'frontend-production-build.log'
                if mutation == 'hash':
                    target.write_bytes(target.read_bytes() + b'altered')
                elif mutation == 'missing':
                    target.unlink()
                else:
                    (target.parent / 'unlisted.txt').write_text('unexpected')
                result = subprocess.run([sys.executable, str(Path(qa.__file__)), '--root', str(root)], capture_output=True)
                self.assertNotEqual(result.returncode, 0)

    def test_git_clean_filter_cannot_silently_invalidate_manifest(self):
        import hashlib
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            subprocess.run(['git', 'init', '-q', str(root)], check=True)
            (root / '.gitattributes').write_text('* text=auto eol=lf\n')
            raw = b'progress\r\nfinished\n'
            (root / 'build.log').write_bytes(raw)
            subprocess.run(['git', 'add', '.'], cwd=root, capture_output=True, check=True)
            manifest = {'files': {'build.log': hashlib.sha256(raw).hexdigest()}}
            with self.assertRaisesRegex(ValueError, 'staged Git blob hash mismatch'):
                qa.verify_index(root, manifest)
            manifest['files']['build.log'] = hashlib.sha256(raw.replace(b'\r\n', b'\n')).hexdigest()
            qa.verify_index(root, manifest)

    def test_unsafe_manifest_path_is_rejected(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            for path in ('../outside', '/absolute', './not-normalized'):
                with self.assertRaises(ValueError):
                    qa.safe_file(root, path)

    def test_missing_route_mapping_is_rejected(self):
        root = Path(__file__).resolve().parents[1]
        inv = copy.deepcopy(qa.strict_json(root / qa.PACKAGE / 'function-inventory.json'))
        inv['route_mapping'].pop(next(iter(inv['route_mapping'])))
        with self.assertRaisesRegex(ValueError, 'route mapping drift'):
            qa.validate_inventory(root, inv)


if __name__ == '__main__':
    unittest.main()
