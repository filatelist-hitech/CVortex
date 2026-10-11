"""QA-002 boundary selection, safety guards and failure/cleanup propagation."""
import copy
import importlib.util
import os
from pathlib import Path
import subprocess
import tempfile
import unittest
from unittest.mock import patch

SPEC = importlib.util.spec_from_file_location('harness', Path(__file__).with_name('vacancy_migration_harness.py'))
H = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(H)


class MigrationHarnessTest(unittest.TestCase):
    def test_later_migration_does_not_change_legacy_plan(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory)
            for name in (H.LEGACY, *H.REPAIRS):
                (path / (name + '.php')).touch()
            before, _ = H.migration_plan(path)
            (path / (H.LATER + '.php')).touch()
            after, all_names = H.migration_plan(path)
            self.assertEqual(before, after)
            self.assertEqual(all_names[-1], H.LATER)

    def test_missing_or_interleaved_boundary_is_rejected(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory)
            with self.assertRaisesRegex(RuntimeError, 'missing named'):
                H.migration_plan(path)
            for name in (H.LEGACY, *H.REPAIRS, H.REPAIRS[0] + '_unexpected'):
                (path / (name + '.php')).touch()
            with self.assertRaisesRegex(RuntimeError, 'unexpected migration'):
                H.migration_plan(path)

    def test_external_targets_blocked_before_any_docker_command(self):
        for selector, value in (('DB_DATABASE', 'cvortex'), ('DB_DATABASE', 'cvortex_test'),
                                ('DB_DATABASE', 'cvortex_dev2'), ('DB_URL', 'private'),
                                ('POSTGRES_PASSWORD', 'private'), ('COMPOSE_FILE', 'shared.yml'),
                                ('COMPOSE_PROJECT_NAME', 'cvortex'), ('PGHOST', 'shared')):
            with self.subTest(selector=selector, value=value), tempfile.TemporaryDirectory() as directory:
                docker = Path(directory) / 'docker'
                marker = Path(directory) / 'called'
                docker.write_text('#!/bin/sh\ntouch "' + str(marker) + '"\nexit 99\n')
                docker.chmod(0o755)
                env = {k: v for k, v in os.environ.items() if not k.startswith(('DB_', 'POSTGRES_', 'COMPOSE_', 'PG'))}
                env.update({selector: value, 'PATH': directory + os.pathsep + env['PATH']})
                result = subprocess.run(['bash', str(H.ROOT / 'scripts/test-vacancy-postgres-duplicate-migration.sh')],
                                        cwd=H.ROOT, env=env, text=True, capture_output=True, timeout=10)
                self.assertNotEqual(result.returncode, 0)
                self.assertIn('BLOCKED', result.stderr)
                self.assertNotIn('private', result.stderr)
                self.assertFalse(marker.exists())

    def resources(self):
        labels = {'com.docker.compose.project': 'qa002_owned', 'cvortex.qa002.run': 'qa002_owned'}
        return ([{'Id': 'own', 'Config': {'Labels': dict(labels)},
                  'Mounts': [{'Type': 'volume', 'Name': 'own_volume'}]}],
                [{'Name': 'own_volume', 'Labels': dict(labels)}],
                [{'Labels': dict(labels), 'Containers': {'own': {}}}])

    def test_ownership_shared_volume_and_network_guards(self):
        H.validate_resources('qa002_owned', *self.resources())
        for mode in ('project', 'token', 'volume', 'network'):
            resources = copy.deepcopy(self.resources())
            if mode == 'project':
                resources[1][0]['Labels']['com.docker.compose.project'] = 'cvortex'
            elif mode == 'token':
                del resources[0][0]['Config']['Labels']['cvortex.qa002.run']
            elif mode == 'volume':
                resources[0][0]['Mounts'][0]['Name'] = 'shared_volume'
            else:
                resources[2][0]['Containers']['foreign'] = {}
            with self.subTest(mode=mode), self.assertRaisesRegex(RuntimeError, 'BLOCKED'):
                H.validate_resources('qa002_owned', *resources)

    def test_foreign_container_mount_prevents_cleanup(self):
        harness = H.Harness(False)
        harness.project = 'qa002_owned'
        harness.started = True
        fake_command = subprocess.CompletedProcess([], 0, stdout='foreign', stderr='')
        try:
            with patch.object(harness, 'resources', return_value=self.resources()), \
                    patch.object(harness, 'command', return_value=fake_command) as command, \
                    patch.object(harness, 'docker_json', return_value=[
                        {'Id': 'foreign', 'Mounts': [{'Type': 'volume', 'Name': 'own_volume'}]}]):
                with self.assertRaisesRegex(RuntimeError, 'foreign container'):
                    harness.cleanup()
                self.assertFalse(any('down' in call.args[0] for call in command.call_args_list))
        finally:
            harness.started = False
            harness.cleanup()

    def test_cleanup_failures_and_incomplete_cleanup_are_fatal(self):
        harness = H.Harness(False)
        harness.started = True
        try:
            with patch.object(harness, 'inspect_owned'), \
                    patch.object(harness, 'command', side_effect=RuntimeError('down failed')):
                with self.assertRaisesRegex(RuntimeError, 'down failed'):
                    harness.cleanup()
            with patch.object(harness, 'inspect_owned'), patch.object(harness, 'command'), \
                    patch.object(harness, 'resources', return_value=self.resources()):
                with self.assertRaisesRegex(RuntimeError, 'left owned resources'):
                    harness.cleanup()
        finally:
            harness.started = False
            harness.cleanup()

    def test_main_never_passes_on_setup_sql_or_cleanup_failure(self):
        for run_error, cleanup_error in ((RuntimeError('SQL failure'), None),
                                         (None, RuntimeError('cleanup failure')),
                                         (KeyboardInterrupt(), None)):
            with self.subTest(run_error=run_error, cleanup_error=cleanup_error), \
                    patch.object(H, 'reject_external_targets'), patch.object(H, 'Harness') as factory, \
                    patch.object(H.sys, 'argv', ['harness']), patch('builtins.print') as output:
                factory.return_value.run.side_effect = run_error
                factory.return_value.cleanup.side_effect = cleanup_error
                self.assertEqual(H.main(), 1)
                factory.return_value.cleanup.assert_called_once()
                self.assertFalse(any(str(call.args[0]).startswith('vacancy-postgres-duplicate-migration: PASS')
                                     for call in output.call_args_list))


if __name__ == '__main__':
    unittest.main()
