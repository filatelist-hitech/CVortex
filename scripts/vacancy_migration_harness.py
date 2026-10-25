"""QA-002: native Laravel migrations on a newly owned, disposable Compose stack."""
import argparse
import hashlib
import json
import os
from pathlib import Path
import secrets
import shutil
import signal
import subprocess
import sys
import tempfile

ROOT = Path(__file__).resolve().parents[1]
FIXTURES = ROOT / 'scripts/fixtures/vacancy-duplicate-migration'
LEGACY = '2026_09_19_000008_create_vacancy_core_tables'
REPAIRS = (
    '2026_09_19_000009_remediate_vacancy_core_review_findings',
    '2026_09_21_000010_allow_reappearing_vacancy_snapshot_content',
    '2026_09_24_000011_reconcile_vacancy_aggregate_state',
    '2026_09_24_000012_recover_completed_extraction_without_analysis',
)
LATER = '2099_01_01_000001_qa002_unrelated_control'


def require(condition, message):
    if not condition:
        raise RuntimeError(message)


def reject_external_targets(env):
    selectors = sorted(k for k in env if k.startswith(('DB_', 'POSTGRES_', 'COMPOSE_', 'PG')))
    require(not selectors, 'BLOCKED: external database/Compose selectors: ' + ', '.join(selectors))


def migration_plan(directory):
    names = sorted(p.stem for p in directory.glob('*.php'))
    require(LEGACY in names and all(n in names for n in REPAIRS), 'missing named migration boundary')
    legacy = [n for n in names if n <= LEGACY]
    require(names[names.index(LEGACY) + 1:names.index(REPAIRS[-1]) + 1] == list(REPAIRS),
            'unexpected migration inside the approved repair boundary')
    return legacy, names


def validate_resources(project, containers, volumes, networks):
    """Never delete an unowned resource, or a volume/network used by another project."""
    for resource in containers + volumes + networks:
        labels = resource.get('Config', {}).get('Labels', {}) if 'Config' in resource else resource.get('Labels', {})
        require(labels.get('com.docker.compose.project') == project and
                labels.get('cvortex.qa002.run') == project, 'BLOCKED: resource ownership mismatch')
    names = {v['Name'] for v in volumes}
    ids = {c['Id'] for c in containers}
    for c in containers:
        for mount in c.get('Mounts', []):
            if mount['Type'] == 'volume':
                require(mount['Name'] in names, 'BLOCKED: shared/unowned volume')
    for network in networks:
        require(set(network.get('Containers', {})) <= ids, 'BLOCKED: shared network')


class Harness:
    def __init__(self, controls):
        self.controls = controls
        self.project = 'qa002_' + secrets.token_hex(8)
        self.database = self.project + '_fixture'
        self.admin = self.project + '_admin'
        self.runtime = self.project + '_runtime'
        self.password = secrets.token_hex(32)
        self.work = Path(tempfile.mkdtemp(prefix=self.project + '_'))
        self.started = False
        self.compose = ['docker', 'compose', '--project-name', self.project,
                        '--env-file', str(self.work / 'empty.env'), '-f', str(self.work / 'compose.json')]

    def command(self, args, input=None, check=True):
        result = subprocess.run(args, input=input, text=True, capture_output=True)
        if check and result.returncode:
            raise RuntimeError(f'command failed (exit={result.returncode}): {result.stdout}{result.stderr}')
        return result

    def docker_json(self, kind, ids):
        return json.loads(self.command(['docker', kind, 'inspect', *ids]).stdout) if ids else []

    def resources(self):
        result = []
        for kind in ('container', 'volume', 'network'):
            extra = ['-a'] if kind == 'container' else []
            ids = self.command(['docker', kind, 'ls', *extra, '-q', '--filter',
                                f'label=com.docker.compose.project={self.project}']).stdout.split()
            result.append(self.docker_json(kind, ids))
        return result

    def inspect_owned(self):
        resources = self.resources()
        validate_resources(self.project, *resources)
        # Check all containers, including stopped foreign containers, for shared mounts.
        ids = self.command(['docker', 'container', 'ls', '-aq']).stdout.split()
        volumes = {v['Name'] for v in resources[1]}
        owned = {c['Id'] for c in resources[0]}
        for container in self.docker_json('container', ids):
            if container['Id'] not in owned:
                require(not any(m.get('Name') in volumes for m in container.get('Mounts', [])),
                        'BLOCKED: owned volume mounted by foreign container')
        return resources

    def prepare(self):
        require(not any(self.resources()), 'BLOCKED: Compose project already exists')
        for volume in ('postgres_data', 'vendor'):
            result = self.command(['docker', 'volume', 'inspect', self.project + '_' + volume], check=False)
            require(result.returncode != 0, 'BLOCKED: volume name already exists')
        # Copy only executable source; no .env, storage, keys, caches or host vendor.
        backend = self.work / 'backend'
        backend.mkdir()
        files = self.command(['git', '-C', str(ROOT), 'ls-files', 'apps/backend']).stdout.splitlines()
        for name in files:
            relative = Path(name).relative_to('apps/backend')
            if (relative.parts[0] in ('app', 'config', 'routes') or
                    relative.parent == Path('database/migrations') or
                    str(relative) in ('artisan', 'composer.json', 'composer.lock', 'bootstrap/app.php', 'bootstrap/providers.php')):
                source = ROOT / name
                require(not source.is_symlink(), 'BLOCKED: source symlink')
                target = backend / relative
                target.parent.mkdir(parents=True, exist_ok=True)
                shutil.copyfile(source, target)
        for directory in ('bootstrap/cache', 'storage/logs', 'storage/framework/cache',
                          'storage/framework/sessions', 'storage/framework/views'):
            (backend / directory).mkdir(parents=True, exist_ok=True)
        self.legacy, self.names = migration_plan(backend / 'database/migrations')
        if self.controls:
            require(LATER not in self.names, 'control migration name already exists')
            (backend / 'database/migrations' / (LATER + '.php')).write_text(
                "<?php return new class extends \\Illuminate\\Database\\Migrations\\Migration { "
                "public function up(): void { \\Illuminate\\Support\\Facades\\DB::statement('CREATE TABLE qa002_later_control (id integer PRIMARY KEY)'); } "
                "public function down(): void { \\Illuminate\\Support\\Facades\\DB::statement('DROP TABLE qa002_later_control'); } };\n")
            self.names = sorted([*self.names, LATER])
        labels = {'cvortex.qa002.run': self.project}
        backend_image = self.docker_json('image', ['cvortex-backend'])[0]['Id']
        print('backend image: ' + backend_image, flush=True)
        config = {'services': {
            'postgres': {'image': 'postgres:18.6-bookworm', 'labels': labels,
                         'environment': {'POSTGRES_DB': self.database, 'POSTGRES_USER': self.admin,
                                         'POSTGRES_PASSWORD': self.password},
                         'volumes': ['postgres_data:/var/lib/postgresql'],
                         'healthcheck': {'test': ['CMD', 'pg_isready', '-U', self.admin, '-d', self.database],
                                         'interval': '1s', 'timeout': '3s', 'retries': 30}},
            'runner': {'image': backend_image, 'labels': labels, 'command': ['sleep', 'infinity'],
                       'working_dir': '/var/www/html',
                       'volumes': [str(backend) + ':/var/www/html', 'vendor:/var/www/html/vendor'],
                       'environment': {'APP_ENV': 'testing', 'APP_KEY': '', 'LOG_CHANNEL': 'stderr',
                                       'DB_CONNECTION': 'pgsql', 'DB_HOST': 'postgres', 'DB_PORT': '5432',
                                       'DB_DATABASE': self.database, 'DB_USERNAME': self.admin,
                                       'DB_PASSWORD': '', 'DB_URL': '', 'DB_ADMIN_URL': '',
                                       'DB_RUNTIME_ROLE': self.runtime, 'CACHE_STORE': 'array',
                                       'SESSION_DRIVER': 'array', 'QUEUE_CONNECTION': 'sync',
                                       'AI_PROVIDER': 'fake', 'MCP_ENABLED': 'false'}}},
                  'volumes': {n: {'name': self.project + '_' + n, 'labels': labels}
                              for n in ('postgres_data', 'vendor')},
                  'networks': {'default': {'internal': True, 'labels': labels}}}
        config['services']['runner']['environment']['DB_PASSWORD'] = config['services']['postgres']['environment']['POSTGRES_PASSWORD']
        (self.work / 'empty.env').touch(mode=0o600)
        (self.work / 'compose.json').write_text(json.dumps(config))
        (self.work / 'compose.json').chmod(0o600)

    def sql(self, sql, check=True):
        return self.command(self.compose + ['exec', '-T', 'postgres', 'psql', '-X', '-U', self.admin,
                                          '-d', self.database, '-v', 'ON_ERROR_STOP=1', '-Atq'], input=sql, check=check)

    def assert_file(self, name):
        result = self.sql((FIXTURES / name).read_text())
        if result.stdout.strip():
            print(result.stdout.strip(), flush=True)

    def recorded(self):
        return self.sql('SELECT migration FROM migrations ORDER BY migration;').stdout.split()

    def migrate(self, name=None):
        args = ['php', 'artisan', 'migrate', '--force', '--no-interaction']
        if name:
            args.append('--path=database/migrations/' + name + '.php')
        print(self.command(self.compose + ['exec', '-T', 'runner', *args]).stdout.strip(), flush=True)

    def control_aggregate_rebinding(self):
        target_snapshot = '00000000000000000000000023'
        rebound_vacancy = '00000000000000000000000011'
        expected_failure = 'snapshot source_url or owner_id differs from parent vacancy'

        count = self.sql(f"SELECT count(*) FROM vacancy_snapshots WHERE id = '{target_snapshot}';").stdout.strip()
        require(count == '1', f'QA002-14 precondition failed: target snapshot {target_snapshot} does not exist (count={count})')

        rebound_sql = (
            "BEGIN;\n"
            "ALTER TABLE vacancy_snapshots DISABLE TRIGGER vacancy_snapshots_immutable;\n"
            "DO $$\n"
            "DECLARE\n"
            "    updated integer;\n"
            "BEGIN\n"
            f"    UPDATE vacancy_snapshots SET vacancy_id = '{rebound_vacancy}', "
            f"version = (SELECT COALESCE(max(version), 0) + 1 FROM vacancy_snapshots WHERE vacancy_id = '{rebound_vacancy}') "
            f"WHERE id = '{target_snapshot}';\n"
            "    GET DIAGNOSTICS updated = ROW_COUNT;\n"
            "    IF updated <> 1 THEN\n"
            "        RAISE EXCEPTION 'QA002-14 precondition failed: expected exactly 1 updated row, got %', updated;\n"
            "    END IF;\n"
            "END;\n"
            "$$;\n" +
            (FIXTURES / 'reconciled.sql').read_text() +
            "\nROLLBACK;"
        )
        result = self.sql(rebound_sql, check=False)
        require(result.returncode != 0, 'QA002-14: aggregate rebinding negative control falsely passed with exit 0')
        require('QA002-14 precondition failed:' not in result.stderr,
                f'QA002-14: update precondition failed: {result.stderr}')
        require(expected_failure in result.stderr,
                f'QA002-14: aggregate rebinding failed with unexpected error signature: {result.stderr}')
        print(f'QA002-14: expected aggregate rebinding mismatch FAIL exit={result.returncode}; transaction rolled back', flush=True)

    def run(self):
        self.prepare()
        print(f'identity: project={self.project} db={self.database} admin={self.admin} runtime={self.runtime}', flush=True)
        self.started = True  # Cleanup also covers partial Compose startup.
        self.command(self.compose + ['up', '-d', '--wait', '--wait-timeout', '60', 'postgres'])
        containers, volumes, _ = self.inspect_owned()
        require(len(containers) == 1 and len(volumes) == 1, 'unexpected initial stack resources')
        pg = containers[0]
        require(pg['Config']['Labels']['com.docker.compose.service'] == 'postgres' and
                not pg['HostConfig']['PortBindings'], 'unexpected PostgreSQL identity/exposure')
        require(self.sql('SELECT current_database() || chr(124) || current_user;').stdout.strip() ==
                self.database + '|' + self.admin, 'BLOCKED: database/role mismatch')
        require(self.sql("SELECT count(*) FROM pg_tables WHERE schemaname='public';").stdout.strip() == '0',
                'BLOCKED: database is not empty')
        print('guard: owned fresh container=' + pg['Id'] + ' volumes=' + ','.join(v['Name'] for v in volumes), flush=True)
        self.sql('CREATE ROLE ' + self.runtime + ' NOLOGIN NOSUPERUSER NOBYPASSRLS;')
        self.command(self.compose + ['up', '-d', '--wait', '--wait-timeout', '60', 'runner'])
        containers, volumes, _ = self.inspect_owned()
        runner = next(c for c in containers if c['Config']['Labels']['com.docker.compose.service'] == 'runner')
        runner_env = dict(e.split('=', 1) for e in runner['Config']['Env'])
        require(runner_env['DB_PASSWORD'] == self.password and not any(
            value for key, value in runner_env.items() if key.endswith(('API_KEY', 'ACCESS_TOKEN', 'CLIENT_SECRET'))),
            'BLOCKED: runner credentials not isolated')
        require(len(containers) == 2 and len(volumes) == 2, 'unexpected runner stack resources')
        # Boot actual config before migration writes; copied source has no cached config or .env.
        config = self.command(self.compose + ['exec', '-T', 'runner', 'php', '-r',
            'require "vendor/autoload.php"; $app=require "bootstrap/app.php"; '
            '$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); '
            'echo json_encode([config("database.default"), config("database.connections.pgsql.host"), '
            'config("database.connections.pgsql.database"), config("database.connections.pgsql.username"), '
            'config("database.connections.pgsql.url")]);']).stdout
        effective = json.loads(config)
        require(effective[:4] == ['pgsql', 'postgres', self.database, self.admin] and effective[4] in (None, ''),
                'BLOCKED: effective Laravel target mismatch')
        for name in self.legacy:
            self.migrate(name)
        require(self.recorded() == self.legacy, 'legacy migration ledger mismatch')
        print('legacy sequence: ' + ', '.join(self.legacy), flush=True)
        if self.controls:
            result = self.sql("BEGIN; CREATE UNIQUE INDEX vacancies_owner_source_url_unique ON vacancies(owner_id, source_url);\n" +
                              (FIXTURES / 'legacy-schema.sql').read_text(), check=False)
            require(result.returncode != 0 and 'legacy schema:' in result.stderr, 'wrong-schema control falsely passed')
            print(f'QA002-11: expected wrong-schema FAIL exit={result.returncode}; transaction rolled back', flush=True)
        self.assert_file('legacy-schema.sql')
        self.assert_file('legacy.sql')
        print('legacy counts: ' + self.counts(), flush=True)
        require(self.counts() == '1|8|10|5|3', 'legacy duplicate fixture counts mismatch')
        history = self.history_fingerprint()
        for name in REPAIRS:
            self.migrate(name)
        self.assert_file('reconciled.sql')
        self.assert_file('repaired-schema.sql')
        require(self.history_fingerprint() == history, 'remediation changed snapshot/analysis/LLM history')
        print('reconciled counts: ' + self.counts(), flush=True)
        if self.controls:
            self.control_aggregate_rebinding()
        preserved = self.fingerprint()
        for name in reversed(REPAIRS):
            ledger = self.recorded()
            require(ledger[-1] == name, 'rollback outside approved boundary')
            batch = self.sql(f"SELECT batch FROM migrations WHERE migration='{name}';").stdout.strip()
            require(self.sql(f'SELECT count(*) FROM migrations WHERE batch={int(batch)};').stdout.strip() == '1',
                    'rollback batch contains unexpected migrations')
            result = self.command(self.compose + ['exec', '-T', 'runner', 'php', 'artisan', 'migrate:rollback',
                '--force', '--no-interaction', '--batch=' + batch, '--path=database/migrations/' + name + '.php'])
            print(result.stdout.strip(), flush=True)
            require(self.recorded() == ledger[:-1], 'named rollback did not remove exactly one migration')
            require(self.fingerprint() == preserved, 'rollback changed preserved fixture data')
        self.assert_file('legacy-schema.sql')
        for name in REPAIRS:
            self.migrate(name)
        self.assert_file('reconciled.sql')
        self.assert_file('repaired-schema.sql')
        require(self.fingerprint() == preserved, 'reapply changed preserved fixture data')
        self.migrate()  # Only now apply unrelated later migrations, in native Laravel ordering.
        require(self.recorded() == self.names, 'current migration ledger mismatch')
        require(self.counts() == '1|5|10|5|3', 'current schema fixture counts mismatch')
        self.assert_file('reconciled.sql')
        self.assert_file('repaired-schema.sql')
        require(self.history_fingerprint() == history, 'later migrations changed snapshot/analysis/LLM history')
        if self.controls:
            require(self.sql("SELECT to_regclass('qa002_later_control') IS NOT NULL;").stdout.strip() == 't',
                    'unrelated later control did not run')
        print(self.command(self.compose + ['exec', '-T', 'runner', 'php', 'artisan', 'migrate:status']).stdout, flush=True)
        print('final counts: ' + self.counts() + '; preserved fingerprint=' + preserved, flush=True)

    def counts(self):
        return self.sql("SELECT (SELECT count(*) FROM users) || '|' || (SELECT count(*) FROM vacancies) || '|' || "
                        "(SELECT count(*) FROM vacancy_snapshots) || '|' || (SELECT count(*) FROM vacancy_analyses) || '|' || "
                        "(SELECT count(*) FROM vacancy_llm_runs);").stdout.strip()

    def fingerprint(self):
        # updated_at on aggregates is deliberately refreshed by forward repairs.
        rows = []
        for table in ('users', 'vacancies', 'vacancy_snapshots', 'vacancy_analyses', 'vacancy_llm_runs'):
            rows.append(self.sql(f"SELECT (to_jsonb(t) - 'updated_at')::text FROM {table} t ORDER BY id;").stdout)
        return hashlib.sha256(''.join(rows).encode()).hexdigest()

    def history_fingerprint(self):
        rows = []
        for table, excluded in (
                ('vacancy_snapshots', "ARRAY['vacancy_id', 'version']"),
                ('vacancy_analyses', "ARRAY['vacancy_id']"),
                ('vacancy_llm_runs', 'ARRAY[]::text[]')):
            rows.append(self.sql(f'SELECT (to_jsonb(t) - {excluded})::text FROM {table} t ORDER BY id;').stdout)
        return hashlib.sha256(''.join(rows).encode()).hexdigest()

    def cleanup(self):
        if self.started:
            self.inspect_owned()
            self.command(self.compose + ['down', '--volumes', '--timeout', '10'])
            require(not any(self.resources()), 'cleanup left owned resources')
            print('cleanup: PASS (owned containers, volumes and network absent)', flush=True)
        shutil.rmtree(self.work)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--regression-controls', action='store_true',
                        help='add a disposable unrelated future migration and wrong-schema negative control')
    args = parser.parse_args()
    harness = None
    result = 0
    def interrupted(_signum, _frame):
        raise KeyboardInterrupt()

    signal.signal(signal.SIGTERM, interrupted)
    try:
        reject_external_targets(os.environ)
        harness = Harness(args.regression_controls)
        harness.run()
    except (RuntimeError, OSError, ValueError, KeyboardInterrupt) as error:
        print(f'vacancy-postgres-duplicate-migration: FAIL: {error}', file=sys.stderr, flush=True)
        result = 1
    finally:
        if harness:
            try:
                harness.cleanup()
            except (RuntimeError, OSError, ValueError) as error:
                print(f'cleanup: BLOCKED/FAIL: {error}; retained private workspace={harness.work}', file=sys.stderr)
                result = 1
    if result == 0:
        print('vacancy-postgres-duplicate-migration: PASS (deterministic boundary; owned cleanup verified)')
    return result


if __name__ == '__main__':
    sys.exit(main())
