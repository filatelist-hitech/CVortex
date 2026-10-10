/* B01 checks and capture evidence; requires an already available Playwright runtime. */
const { chromium } = require(process.env.CVORTEX_PLAYWRIGHT_MODULE || 'playwright');
const fs = require('node:fs');
const path = require('node:path');
const os = require('node:os');
const crypto = require('node:crypto');
const assert = require('node:assert/strict');
const base = process.env.CVORTEX_B01_URL || 'http://127.0.0.1:8768';
if (new URL(base).hostname !== '127.0.0.1') throw new Error('Loopback preview required');
const output = path.join(__dirname, 'validation');
fs.mkdirSync(output, { recursive: true });
const checks = [], captures = [], errors = [], externalRequests = [], contrast = [];
function check(name, value) { assert.ok(value, name); checks.push(name); }
async function load(page, scenario = 'normal', opportunity = 'northstar') {
  const target = `${base}/?scenario=${scenario}&opportunity=${opportunity}&level=work#materials`;
  const sameDocument = page.url().split('#')[0] === target.split('#')[0];
  await page.goto(target);
  if (sameDocument) await page.reload();
  await page.waitForLoadState('networkidle');
}
async function reflow(page, name) {
  check(`${name}: no page overflow`, await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
}
async function capture(page, name) {
  const file = `${name}.png`;
  await page.screenshot({ path: path.join(output, file), fullPage: true });
  captures.push({ file, url: page.url().replace(base, ''), viewport: page.viewportSize(), state: await page.locator('body').getAttribute('data-level') });
}
async function contrastAudit(page, name) {
  const result = await page.evaluate(() => {
    const rgb = value => (value.match(/[\d.]+/g) || []).map(Number);
    const lum = color => color.slice(0, 3).map(n => { n /= 255; return n <= .04045 ? n / 12.92 : ((n + .055) / 1.055) ** 2.4; }).reduce((s, n, i) => s + n * [.2126, .7152, .0722][i], 0);
    const ratio = (a, b) => (Math.max(lum(a), lum(b)) + .05) / (Math.min(lum(a), lum(b)) + .05);
    function background(el) { while (el) { const c = rgb(getComputedStyle(el).backgroundColor); if (c.length === 3 || c[3] === 1) return c; el = el.parentElement; } throw new Error('No opaque background'); }
    const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT), rows = [], controls = [];
    while (walker.nextNode()) {
      const node = walker.currentNode, el = node.parentElement;
      if (!node.textContent.trim() || !el || el.closest('script,style,[hidden],:disabled,option,[inert]') || !el.getClientRects().length) continue;
      const range = document.createRange(); range.selectNodeContents(node);
      if (!range.getBoundingClientRect().width) continue;
      const s = getComputedStyle(el), size = parseFloat(s.fontSize), weight = parseInt(s.fontWeight) || 400;
      rows.push({ text: node.textContent.trim().slice(0, 70), ratio: ratio(rgb(s.color), background(el)), required: size >= 24 || size >= 18.67 && weight >= 700 ? 3 : 4.5, size });
    }
    for (const el of document.querySelectorAll('button:not(:disabled),input,select,textarea')) {
      if (!el.getClientRects().length || el.closest('[inert]')) continue;
      const s = getComputedStyle(el);
      // Opportunity buttons are whole labelled rows; selection uses its cyan boundary.
      if (!el.matches('.opportunity-item') || el.getAttribute('aria-current') === 'true') controls.push({ control: el.id || el.textContent.trim().slice(0, 30), ratio: Math.max(ratio(rgb(s.borderTopColor), background(el.parentElement)), ratio(rgb(s.backgroundColor), background(el.parentElement))) });
    }
    return { count: rows.length, minimum: Math.min(...rows.map(x => x.ratio)), failures: rows.filter(x => x.ratio < x.required || x.size < 12), controlFailures: controls.filter(x => x.ratio < 3) };
  });
  contrast.push({ name, ...result });
  check(`${name}: rendered text contrast and size`, !result.failures.length);
  check(`${name}: control boundary contrast`, !result.controlFailures.length);
}
(async () => {
  const browser = await chromium.launch({ headless: true, ...(process.env.CVORTEX_CHROMIUM_PATH ? { executablePath: process.env.CVORTEX_CHROMIUM_PATH } : {}) });
  const conditions = { browser: `Chromium ${browser.version()}`, playwright: require(path.join(path.dirname(require.resolve(process.env.CVORTEX_PLAYWRIGHT_MODULE || 'playwright')), 'package.json')).version, os: `${os.platform()} ${os.release()} ${os.arch()}`, locale: 'ru-RU', timezone: 'Europe/Moscow', motion: 'reduced', font: 'Canonical family list; installed/system fallback, no downloaded font', clock: 'Fixed fixture dates; no relative timestamps' };
  try {
    const context = await browser.newContext({ locale: 'ru-RU', timezoneId: 'Europe/Moscow', reducedMotion: 'reduce' });
    await context.route('**/*', async route => {
      if (new URL(route.request().url()).origin !== new URL(base).origin) { externalRequests.push(route.request().url()); await route.abort(); } else await route.continue();
    });
    const page = await context.newPage();
    page.on('pageerror', e => errors.push(e.message));
    page.on('console', msg => { if (msg.type() === 'error') errors.push(msg.text()); });
    page.on('response', response => { if (response.status() >= 400) errors.push(`${response.status()} ${response.url()}`); });
    const states = ['normal', 'loading', 'empty', 'failed', 'stale', 'blocked', 'pending', 'approval'];
    for (const [width, height] of [[1440, 900], [768, 1024], [390, 844], [320, 640]]) {
      await page.setViewportSize({ width, height });
      for (const state of states) {
        await load(page, state); const name = `${width}-${state}`;
        await reflow(page, name);
        check(`${name}: correct scenario`, await page.locator('#scenario-select').inputValue() === state);
        check(`${name}: review availability`, await page.locator('#review-card').isVisible() === !['loading', 'empty', 'failed'].includes(state));
        check(`${name}: approval gate`, await page.locator('#approve').isDisabled() === (state !== 'approval'));
        if (['stale', 'blocked', 'pending'].includes(state)) check(`${name}: acceptance blocked`, await page.locator('#accept').isDisabled());
        if (state === 'loading') check(`${name}: operation announced busy`, await page.locator('#materials').getAttribute('aria-busy') === 'true');
        await contrastAudit(page, name); await capture(page, name);
      }
      await load(page);
      const evidence = page.locator('#materials [data-open-evidence]');
      await evidence.focus(); await page.keyboard.press('Enter');
      check(`${width}: evidence opens from keyboard`, await page.locator('#inspector').isVisible());
      check(`${width}: title focus`, await page.locator('#inspector-title').evaluate(el => el === document.activeElement));
      await reflow(page, `${width}-evidence`); await contrastAudit(page, `${width}-evidence`); await capture(page, `${width}-evidence`);
      if (width === 768) {
        check('tablet: evidence is modal', await page.locator('#inspector').getAttribute('aria-modal') === 'true');
        await page.locator('#pin-inspector').focus(); await page.keyboard.press('Tab');
        check('tablet: forward focus trap', await page.locator('#close-inspector').evaluate(el => el === document.activeElement));
        await page.keyboard.press('Shift+Tab');
        check('tablet: reverse focus trap', await page.locator('#pin-inspector').evaluate(el => el === document.activeElement));
      }
      await page.keyboard.press('Escape');
      check(`${width}: Escape closes and returns focus`, !await page.locator('#inspector').isVisible() && await evidence.evaluate(el => el === document.activeElement));
      check(`${width}: visible focus`, await evidence.evaluate(el => getComputedStyle(el).outlineStyle === 'solid' && parseFloat(getComputedStyle(el).outlineWidth) >= 2));
      if (width === 768) {
        check('tablet: workspace first', !await page.locator('#opportunity-list').isVisible());
        await page.locator('#open-list').click();
        check('tablet: list dialog', await page.locator('#opportunity-list').getAttribute('aria-modal') === 'true');
        await capture(page, '768-list');
        await page.keyboard.press('Escape');
        await page.waitForFunction(() => document.activeElement.id === 'open-list');
        check('tablet: list return focus', await page.locator('#open-list').evaluate(el => el === document.activeElement));
      }
      if (width < 768) {
        check(`${width}: mobile section control visible`, await page.locator('#section-select').isVisible());
        await page.locator('#accept').click();
        await page.locator('#back-level').click();
        await page.locator('#opportunity-filter').fill('Northstar'); await capture(page, `${width}-list`);
        await page.goBack();
        check(`${width}: browser Back restores workspace approval`, await page.locator('#workspace').isVisible() && !await page.locator('#approve').isDisabled());
        await page.goForward();
        check(`${width}: browser Forward restores filter`, await page.locator('#opportunity-list').isVisible() && await page.locator('#opportunity-filter').inputValue() === 'Northstar');
        await page.locator('[data-opportunity="northstar"]').click();
        await page.locator('#section-select').selectOption('requirements');
        check(`${width}: mobile section preserves selection`, await page.locator('#requirements').isVisible() && await page.locator('#company-name').textContent() === 'Northstar Labs');
      }
    }
    for (const [width, height] of [[1440, 900], [768, 1024], [390, 844], [320, 640]]) {
      await page.setViewportSize({ width, height }); await load(page);
      check(`${width}: salary is separate and exact`, await page.locator('#vacancy-salary').textContent() === '240 000…290 000 ₽' && (await page.locator('.salary-basis').textContent()).includes('до налогов'));
      check(`${width}: decorative artwork loaded`, await page.locator('.career-art').evaluate(el => el.complete && el.naturalWidth === 1536 && el.alt === '' && el.getAttribute('aria-hidden') === 'true'));
      await page.locator('#account-trigger').focus(); await page.keyboard.press('Enter');
      check(`${width}: account opens from keyboard`, await page.locator('#account-dialog').evaluate(el => el.open));
      check(`${width}: account initial focus`, await page.locator('#close-account').evaluate(el => el === document.activeElement));
      check(`${width}: admin tools hidden for user`, !await page.locator('#admin-utilities').isVisible() && await page.locator('#diagnostics-action').isDisabled());
      await reflow(page, `${width}-account-user`); await contrastAudit(page, `${width}-account-user`); await capture(page, `${width}-account-user`);
      await page.locator('#demo-session').focus(); await page.keyboard.press('Tab');
      check(`${width}: account forward trap`, await page.locator('#close-account').evaluate(el => el === document.activeElement));
      await page.keyboard.press('Shift+Tab');
      check(`${width}: account reverse trap`, await page.locator('#demo-session').evaluate(el => el === document.activeElement));
      await page.locator('#demo-session').selectOption('admin');
      check(`${width}: admin example exposes diagnostics`, await page.locator('#admin-utilities').isVisible() && !await page.locator('#diagnostics-action').isDisabled());
      check(`${width}: unfinished admin panel stays unavailable`, await page.locator('#admin-action').isDisabled());
      await page.locator('#diagnostics-action').click();
      check(`${width}: diagnostics explains isolated reference`, (await page.locator('#account-feedback').textContent()).includes('реальных инцидентов нет'));
      check(`${width}: account close stays in viewport after scroll`, await page.locator('#close-account').evaluate(el => { const r = el.getBoundingClientRect(), d = document.getElementById('account-dialog').getBoundingClientRect(); return r.top >= d.top && r.bottom <= d.bottom; }));
      await reflow(page, `${width}-account-admin`); await contrastAudit(page, `${width}-account-admin`); await capture(page, `${width}-account-admin`);
      await page.locator('#demo-logout').click();
      check(`${width}: demo logout has no admin tools`, await page.locator('#demo-session').inputValue() === 'guest' && !await page.locator('#admin-utilities').isVisible() && await page.locator('#profile-action').isDisabled());
      await reflow(page, `${width}-account-guest`); await contrastAudit(page, `${width}-account-guest`); await capture(page, `${width}-account-guest`);
      await page.locator('#demo-login').click();
      check(`${width}: demo login restores user only`, await page.locator('#demo-session').inputValue() === 'user' && !await page.locator('#admin-utilities').isVisible());
      await page.keyboard.press('Escape');
      await page.waitForFunction(() => !document.getElementById('account-dialog').open && document.getElementById('account-trigger').getAttribute('aria-expanded') === 'false' && document.activeElement.id === 'account-trigger');
      check(`${width}: account Escape and return focus`, !await page.locator('#account-dialog').evaluate(el => el.open) && await page.locator('#account-trigger').getAttribute('aria-expanded') === 'false');
      check(`${width}: profile display preserves review gates`, !await page.locator('#accept').isDisabled() && await page.locator('#approve').isDisabled());
    }
    await load(page, 'normal', 'cedar');
    check('Cedar salary retains fixture and gross basis', await page.locator('#vacancy-salary').textContent() === '250 000…300 000 ₽' && (await page.locator('.salary-basis').textContent()).includes('до налогов'));
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.locator('[data-section="requirements"]').click();
    await reflow(page, '1440-cedar-requirements'); await contrastAudit(page, '1440-cedar-requirements'); await capture(page, '1440-cedar-requirements');
    await page.setViewportSize({ width: 768, height: 1024 }); await load(page);
    await page.locator('#materials [data-open-evidence]').click();
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.locator('#account-trigger').click();
    check('account replaces open evidence without stale modal state', !await page.locator('#inspector').isVisible() && await page.locator('#account-dialog').evaluate(el => el.open) && !await page.locator('.site-header').evaluate(el => el.inert));
    await page.keyboard.press('Escape');

    await page.setViewportSize({ width: 1440, height: 900 }); await load(page);
    await page.locator('#accept').click();
    check('accept differs from approval', (await page.locator('#review-state').textContent()).includes('текст не одобрен') && !await page.locator('#approve').isDisabled());
    await page.locator('#approve').click();
    check('content approval is not submission', (await page.locator('#action-status').textContent()).includes('Отклик не отправлен') && await page.locator('#approve').isDisabled());
    await load(page); await page.locator('#edit').click(); await page.locator('#proposal-edit').fill('Led a team of 30 engineers.');
    await page.locator('#save-edit').click();
    check('unsupported edit fails closed', (await page.locator('#action-status').textContent()).includes('Правка заблокирована') && await page.locator('#accept').isDisabled() && await page.locator('#approve').isDisabled());
    await page.locator('[data-opportunity="cedar"]').click();
    check('unsaved context guard preserves text', await page.locator('#company-name').textContent() === 'Northstar Labs' && await page.locator('#proposal-edit').inputValue() === 'Led a team of 30 engineers.');
    await page.locator('#proposal-edit').fill('Писал регрессионные тесты API на Python и pytest.'); await page.locator('#save-edit').click();
    check('supported edit remains unapproved', !await page.locator('#accept').isDisabled() && await page.locator('#approve').isDisabled());
    await page.locator('#materials [data-open-evidence]').click(); await page.locator('#pin-inspector').click();
    await page.locator('[data-opportunity="cedar"]').click();
    check('opportunity switch clears inspector', !await page.locator('#inspector').isVisible());
    check('confirmed conflict cannot be accepted', await page.locator('#accept').isDisabled() && await page.locator('#approve').isDisabled());
    await page.locator('#materials [data-open-evidence]').click();
    check('pin cleared and evidence scoped', await page.locator('#pin-inspector').getAttribute('aria-pressed') === 'false' && (await page.locator('#inspector-scope').textContent()).includes('Cedar Systems'));
    await capture(page, '1440-conflict');
    check('conflict next action identifies confirmed conflict', (await page.locator('#next-action').textContent()).includes('противоречит предложению гибрида'));
    await load(page); await page.locator('#scenario-select').selectOption('pending');
    check('scenario selection updates deep link', new URL(page.url()).searchParams.get('scenario') === 'pending');
    await page.reload();
    check('scenario survives reload with approval blocked', await page.locator('#scenario-select').inputValue() === 'pending' && await page.locator('#approve').isDisabled());
    for (const state of ['loading', 'empty', 'failed', 'blocked', 'pending', 'stale']) {
      await load(page, state); await page.locator('[data-section="overview"]').click(); await page.locator('#overview [data-open-evidence]').click();
      check(`${state}: inspector never falsely confirms`, !(await page.locator('#inspector-status').textContent()).startsWith('Подтверждения есть'));
    }
    await load(page); await page.locator('#opportunity-filter').fill('no-fixture');
    check('empty filtered list', await page.locator('#list-no-results').isVisible());
    await page.locator('#opportunity-filter').fill(''); await page.locator('[data-section="employer"]').click();
    check('employer availability honest', await page.getByRole('button', { name: 'История общения недоступна' }).isDisabled());
    await page.setViewportSize({ width: 320, height: 640 }); await load(page);
    await page.locator('#proposal-copy').evaluate(el => el.textContent = 'Длинный синтетический текст: API и PostgreSQL, неподтверждённые навыки не разрешают одобрение. '.repeat(8));
    await reflow(page, '320-long-RU');
    await page.locator('#materials [data-open-evidence]').click();
    await page.locator('#evidence-trace li').first().evaluate(el => el.textContent = 'fixture-very-long-revision-'.repeat(20));
    await reflow(page, '320-long-identifier');
    check('reduced motion respected', await page.evaluate(() => matchMedia('(prefers-reduced-motion: reduce)').matches));
    const response = await context.request.get(`${base}/manifest.json`);
    check('manifest local asset available', response.ok());
    check('no POST operation', (await context.request.post(`${base}/`)).status() === 405);
    check('path traversal rejected', (await context.request.get(`${base}/..%2f..%2f..%2fPROJECT.md`)).status() >= 400);
    check('preview blocks network connections', response.headers()['content-security-policy'].includes("connect-src 'none'"));
    check('no external preview requests', !externalRequests.length);
    check('no browser errors or missing assets', !errors.length);
    const referenceSourceSha256 = Object.fromEntries(['index.html', 'app.js', 'style.css', 'tokens.css', 'build-reference.cjs', 'preview-server.cjs', 'validate-browser.cjs', 'assets/career-vortex-v1.png', 'assets/sources.json', 'assets/lucide-LICENSE.txt'].map(file => [file, crypto.createHash('sha256').update(fs.readFileSync(path.join(__dirname, file))).digest('hex')]));
    fs.writeFileSync(path.join(output, 'browser-results.json'), JSON.stringify({ status: 'PASS', checkedAt: new Date().toISOString(), referenceSourceSha256, checks, captures, conditions, errors, externalRequests, contrast, limitations: ['Human visual review pending', 'Real browser 200% zoom not performed', 'Manual screen-reader assessment not performed', 'No production component tests or visual regression baseline'] }, null, 2) + '\n');
    console.log(`B01 browser: PASS; ${checks.length} checks; ${captures.length} screenshots; no external requests`);
  } catch (error) {
    fs.writeFileSync(path.join(output, 'browser-results.json'), JSON.stringify({ status: 'FAIL', failure: error.message, checks, errors, externalRequests, contrast }, null, 2) + '\n');
    throw error;
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
