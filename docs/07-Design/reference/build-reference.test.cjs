const { test } = require('node:test');
const assert = require('node:assert/strict');
const { resolver, cssValue } = require('./build-reference.cjs');

test('aliases fail closed for missing targets and cycles', () => {
  assert.throws(() => resolver({ a: { $type: 'color', $value: '{absent}' } })('a'), /Missing token/);
  assert.throws(() => resolver({ a: { $type: 'color', $value: '{b}' }, b: { $type: 'color', $value: '{a}' } })('a'), /Cyclic token alias/);
  assert.throws(() => resolver({ a: { $type: 'color', $value: '{b}' }, b: { $type: 'dimension', $value: { value: 1, unit: 'px' } } })('a'), /type mismatch/);
});

test('DTCG alpha and rem tracking retain their units', () => {
  assert.equal(cssValue({ type: 'color', value: { colorSpace: 'srgb', hex: '#050812', alpha: 0.8 } }), 'rgb(5 8 18 / 0.8)');
  assert.equal(cssValue({ type: 'dimension', value: { value: 0.02, unit: 'rem' } }), '0.02rem');
  assert.throws(() => cssValue({ type: 'dimension', value: { value: 4, unit: 'em' } }), /Unsupported dimension/);
});

const fs = require('node:fs');
const path = require('node:path');
const os = require('node:os');
const crypto = require('node:crypto');
const { spawnSync, execFileSync } = require('node:child_process');
const root = path.resolve(__dirname, '../../..');
const reference = 'docs/07-Design/reference';
const evidence = 'docs/09-QA/evidence/qa-001-2026-10-12';
const json = file => JSON.parse(fs.readFileSync(path.join(root, file), 'utf8'));
const hash = bytes => crypto.createHash('sha256').update(bytes).digest('hex');
const baseline = json(`${evidence}/baseline.json`);
const upstreamSha = '564ce565c371c5e5bbf286006565a7c9aa55a9f56e7ca58d56e05d649dd61a72';
const storedSha = '18a4de52385f6b988782639d5d0cc1326e5a8c2de9a7f01d7b20d9aedcc60943';
const stored = () => fs.readFileSync(path.join(__dirname, 'assets/OFL.txt'));
const upstream = () => Buffer.from(json(`${evidence}/upstream-ofl.json`).data, 'base64');
const normalize = bytes => Buffer.from(bytes.toString('utf8').replaceAll('\r\n', '\n').replace(/[ \t]+$/gm, ''));
const check = (cwd = root, mode = '--check') => spawnSync(process.execPath, [path.join(cwd, reference, 'build-reference.cjs'), mode], { cwd, encoding: 'utf8' });

function withFixture(t, fn) {
  const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'cvortex-qa001-'));
  t.after(() => fs.rmSync(tmp, { recursive: true, force: true }));
  fs.cpSync(__dirname, path.join(tmp, reference), {
    recursive: true,
    filter: file => path.basename(file) !== 'validation',
  });
  for (const file of ['brand/tokens/cvortex.tokens.json', '.gitattributes',
    ...Object.keys(baseline.generated.sourceSha256), `${evidence}/upstream-ofl.json`]) {
    const dest = path.join(tmp, file);
    fs.mkdirSync(path.dirname(dest), { recursive: true });
    fs.copyFileSync(path.join(root, file), dest);
  }
  return fn(tmp);
}

function assertPass(result) {
  assert.equal(result.status, 0, result.stderr || result.stdout);
  assert.match(result.stdout, /B01 reference check: PASS/);
}

function assertFail(result, pattern) {
  assert.equal(result.status, 1, result.stderr || result.stdout);
  assert.match(result.stderr, pattern);
  assert.doesNotMatch(result.stdout, /PASS/);
}

test('QA001-01 current canonical manifest passes the real read-only checker', () => {
  assertPass(check());
});

test('QA001-02 tracked OFL matches the explicit repository byte policy', () => {
  const bytes = stored();
  const tracked = execFileSync('git', ['show', `HEAD:${reference}/assets/OFL.txt`], { cwd: root });
  const metadata = json(`${reference}/assets/font-source.json`).files['OFL.txt'];
  assert.deepEqual(bytes, tracked);
  assert.equal(hash(bytes), storedSha);
  assert.equal(metadata.stored.sha256, storedSha);
  assert.equal(metadata.stored.bytes, bytes.length);
  assert.equal(metadata.stored.bytePolicy, 'lf-trim-trailing-horizontal-whitespace');
  assert.equal(bytes.includes('\r'), false);
  assert.doesNotMatch(bytes.toString(), /[ \t]+$/m);
  assert.equal(json(`${reference}/manifest.json`).generated.referenceAssetSha256['assets/OFL.txt'], storedSha);
});

test('QA001-03 pinned upstream provenance is separately verifiable and fails closed', t => {
  const metadata = json(`${reference}/assets/font-source.json`);
  const bytes = upstream();
  assert.equal(metadata.revision, '03507d024a01282884232081fc6011c09ff4e849');
  assert.equal(metadata.files['OFL.txt'].sha256, upstreamSha);
  assert.equal(metadata.files['OFL.txt'].bytes, 4495);
  assert.equal(hash(bytes), upstreamSha);
  assert.equal(bytes.length, 4495);
  assert.equal(bytes.toString().match(/\r\n/g).length, 93);
  assert.notEqual(upstreamSha, storedSha);
  withFixture(t, tmp => {
    const file = path.join(tmp, evidence, 'upstream-ofl.json');
    const fixture = JSON.parse(fs.readFileSync(file, 'utf8'));
    const mutated = Buffer.from(fixture.data, 'base64');
    mutated[0] ^= 1;
    fixture.data = mutated.toString('base64');
    fs.writeFileSync(file, JSON.stringify(fixture));
    assertFail(check(tmp), /OFL upstream provenance drift/);
    assertFail(check(tmp, '--write'), /OFL upstream provenance drift/);
  });
});

test('QA001-04 license text is unchanged from the pinned upstream and tracked baseline', () => {
  assert.deepEqual(normalize(upstream()), stored());
  assert.equal(hash(stored()), storedSha);
  // Only the documented CRLF and one trailing space differ; all other text is exact.
  assert.equal(upstream().toString().replaceAll('\r\n', '\n')
    .replace('bundled, embedded, \n', 'bundled, embedded,\n'), stored().toString());
});

test('QA001-05 a one-byte tracked OFL mutation fails check and write on a temporary fixture', t => {
  withFixture(t, tmp => {
    assertPass(check(tmp));
    const file = path.join(tmp, reference, 'assets/OFL.txt');
    const bytes = fs.readFileSync(file);
    bytes[0] ^= 1;
    fs.writeFileSync(file, bytes);
    assertFail(check(tmp), /OFL repository integrity or license text drift/);
    assertFail(check(tmp, '--write'), /OFL repository integrity or license text drift/);
  });
});

test('QA001-06 missing license and unrelated canonical asset both fail on temporary fixtures', t => {
  for (const asset of ['OFL.txt', 'SpaceGrotesk.woff2']) {
    withFixture(t, tmp => {
      assertPass(check(tmp));
      fs.unlinkSync(path.join(tmp, reference, 'assets', asset));
      assertFail(check(tmp), /ENOENT|manifest source\/hash drift/);
    });
  }
});

test('QA001-07 all unrelated canonical asset, source, token and reference hashes remain unchanged', () => {
  const current = json(`${reference}/manifest.json`).generated;
  const justified = new Set(['assets/OFL.txt', 'assets/font-source.json', 'build-reference.cjs', 'build-reference.test.cjs']);
  assert.deepEqual(Object.keys(current.referenceAssetSha256).sort(), Object.keys(baseline.generated.referenceAssetSha256).sort());
  for (const [file, digest] of Object.entries(baseline.generated.referenceAssetSha256)) {
    if (!justified.has(file)) {
      assert.equal(current.referenceAssetSha256[file], digest, file);
      assert.equal(hash(fs.readFileSync(path.join(__dirname, file))), digest, file);
    }
  }
  for (const key of ['canonicalTokenSha256', 'activeReferenceSha256', 'sourceSha256', 'consumedTokenRoles']) {
    assert.deepEqual(current[key], baseline.generated[key], key);
  }
});

test('QA001-08 fresh Git index checkout and working tree share the byte policy even with autocrlf=true', t => {
  withFixture(t, tmp => {
    const git = args => execFileSync('git', args, { cwd: tmp, stdio: 'pipe' });
    git(['init', '-q']);
    git(['config', 'core.autocrlf', 'true']);
    git(['add', '.']);
    assert.deepEqual(git(['show', `:${reference}/assets/OFL.txt`]), stored());
    assert.match(git(['ls-files', '--eol', `${reference}/assets/OFL.txt`]).toString(), /i\/lf\s+w\/lf\s+attr\/text=auto eol=lf/);
    const fresh = path.join(tmp, 'fresh');
    fs.mkdirSync(fresh);
    git(['checkout-index', '--all', `--prefix=${fresh}${path.sep}`]);
    assert.deepEqual(fs.readFileSync(path.join(fresh, reference, 'assets/OFL.txt')), stored());
    assertPass(check(tmp));
    assertPass(check(fresh));
  });
});
