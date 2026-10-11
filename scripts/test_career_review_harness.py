"""Exercise wrapper failure propagation with a fake Docker CLI, never a real DB."""
import os
from pathlib import Path
import subprocess
import tempfile
import unittest

FAKE_DOCKER = r'''#!/usr/bin/env python3
import os, sys
args = ' '.join(sys.argv[1:])
mode = os.environ['HARNESS_TEST_MODE']
winner = 'reject' if mode == 'reject' else 'confirm'
if 'printenv POSTGRES_USER' in args:
    print('synthetic_admin')
elif 'prepare_career_review_concurrency.php' in args:
    print('owner\nfact')
elif 'SELECT count(*) FROM career_review_test_barrier' in args:
    print('2')
elif 'career_review_worker.php' in args:
    action = sys.argv[-2]
    print(('applied ' if action == winner else 'conflict ') + action)
    if mode == 'worker-failure' and action == winner: sys.exit(1)
elif 'verify_career_review_concurrency.php' in args:
    if mode == 'exception-zero': print('RuntimeException\nfailed invariant')
    elif mode == 'missing-zero': pass
    elif mode == 'nonzero': sys.exit(1)
    elif mode == 'extra-output': print('verified: confirm transition and valid Claim state\nunexpected output')
    else: print('verified: ' + winner + ' transition and valid Claim state')
'''


class CareerWrapperRegressionTest(unittest.TestCase):
    def execute(self, mode):
        with tempfile.TemporaryDirectory() as tmp:
            docker = Path(tmp) / 'docker'
            docker.write_text(FAKE_DOCKER)
            docker.chmod(0o755)
            env = dict(os.environ, PATH=tmp + os.pathsep + os.environ['PATH'], HARNESS_TEST_MODE=mode)
            root = Path(__file__).resolve().parents[1]
            return subprocess.run(['bash', 'scripts/test-career-review-postgres-concurrency.sh'],
                                  cwd=root, env=env, capture_output=True, text=True, timeout=30)

    def test_both_valid_winners_pass(self):
        for mode in ('confirm', 'reject'):
            with self.subTest(mode=mode):
                result = self.execute(mode)
                self.assertEqual(result.returncode, 0, result.stderr)
                self.assertIn(f'verified: {mode}', result.stdout)

    def test_exception_and_incomplete_verification_never_pass(self):
        for mode in ('exception-zero', 'missing-zero', 'nonzero', 'extra-output', 'worker-failure'):
            with self.subTest(mode=mode):
                result = self.execute(mode)
                self.assertNotEqual(result.returncode, 0)
                self.assertNotIn('career-review-postgres-concurrency: PASS', result.stdout)


if __name__ == '__main__':
    unittest.main()
