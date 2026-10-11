/* Candidate manifest and CSS derive from the existing B01 transformer. No active mapping changes. */
const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '../../../..');
const { generate, resolver, cssValue } = require('../../reference/build-reference.cjs');
const hash = file => crypto.createHash('sha256').update(fs.readFileSync(file)).digest('hex');
const tokens = path.join(root, 'brand/tokens/cvortex.tokens.json');
const tree = JSON.parse(fs.readFileSync(tokens));
const resolve = resolver(tree);
const extra = ['font.tracking.compact', 'shadow.overlay'].map(name => {
  const token = resolve(name);
  const value = token.type === 'shadow' ? (() => {
    const item = token.value;
    const dimensions = ['offsetX', 'offsetY', 'blur', 'spread'].map(key => cssValue({ type: 'dimension', value: item[key] }));
    return `${dimensions.join(' ')} ${cssValue({ type: 'color', value: item.color })}`;
  })() : cssValue(token);
  return `  --cv-${name.replaceAll('.', '-')}: ${value};`;
}).join('\n');
const css = generate(tree, hash(tokens)) + `/* Additional existing canonical roles consumed by this candidate. */\n:root {\n${extra}\n}\n`;
const files = ['index.html','app.js','motion.js','style.css','validate-candidate.cjs','validate-apple.cjs','validate-typography.cjs','build-reference.cjs','preview-server.cjs','validate-browser.cjs', ...fs.readdirSync(path.join(__dirname,'assets')).map(f => `assets/${f}`)];
const manifestPath = path.join(__dirname, 'manifest.json');
const manifest = JSON.parse(fs.readFileSync(manifestPath));
const generated = {
  canonicalTokenPath: 'brand/tokens/cvortex.tokens.json', canonicalTokenSha256: hash(tokens),
  candidateSourceSha256: Object.fromEntries(files.map(f => [f, hash(path.join(__dirname,f))])),
  activeReferenceManifestSha256: hash(path.join(root,'docs/07-Design/reference/manifest.json')),
  inheritedBehaviorSha256: hash(path.join(root,'docs/07-Design/reference/app.js')),
};
if (process.argv[2] === '--write') {
  fs.writeFileSync(path.join(__dirname,'tokens.css'), css);
  manifest.generated = generated;
  fs.writeFileSync(manifestPath, JSON.stringify(manifest,null,2)+'\n');
} else if (process.argv[2] === '--check') {
  assert.equal(fs.readFileSync(path.join(__dirname,'tokens.css'),'utf8'),css,'Derived token drift');
  assert.deepEqual(manifest.generated,generated,'Candidate source drift');
  assert.equal(manifest.isActiveReference,false);
  assert.equal(manifest.review.status,'awaiting-review');
  const before = JSON.parse(fs.readFileSync(path.join(__dirname,'validation/typography/protected-before.json')));
  for (const [file, sha] of Object.entries(before)) assert.equal(hash(path.join(root,file)),sha,`Protected file changed: ${file}`);
  const html = fs.readFileSync(path.join(__dirname,'index.html'),'utf8');
  const js = fs.readFileSync(path.join(__dirname,'app.js'),'utf8');
  assert.ok(!/https?:\/\/|\bfetch\s*\(|XMLHttpRequest|WebSocket/.test(html+'\n'+js),'Network logic');
  const style = fs.readFileSync(path.join(__dirname,'style.css'),'utf8');
  assert.ok(!/#[\da-fA-F]{3,8}\b|rgba?\(|https?:\/\/|@import/.test(style),'Noncanonical palette or external asset');
  assert.ok(style.includes("url('./assets/SpaceGrotesk.woff2')"),'Pinned local font');
  assert.ok(html.includes('Your career, in context.'),'Canonical slogan');
  for (const role of ['tablet','desktop','wide']) {
    const threshold = require('../../reference/build-reference.cjs').resolver(JSON.parse(fs.readFileSync(tokens)))(`breakpoint.${role}`).value.value;
    assert.ok(style.includes(`${threshold}px`) || style.includes(`${threshold-1}px`),`Breakpoint: ${role}`);
  }
  fs.writeFileSync(path.join(__dirname,'validation/integrity-results.json'),JSON.stringify({status:'PASS',checkedAt:new Date().toISOString(),protectedFiles:Object.keys(before).length,canonicalTokenSha256:hash(tokens),activeReferenceUnchanged:true,productionUnchanged:true,originalResearchUnchanged:true},null,2)+'\n');
} else throw new Error('Use --write or --check');
console.log(`B01 candidate ${process.argv[2]}: PASS`);
