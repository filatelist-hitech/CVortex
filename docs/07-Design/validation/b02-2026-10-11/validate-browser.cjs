/* B02 browser evidence only. Synthetic API fixtures never reach Laravel or persisted data.
   Requires an existing Playwright installation and Chrome executable; installs nothing. */
const { chromium } = require(process.env.CVORTEX_PLAYWRIGHT_MODULE || 'playwright');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const crypto = require('node:crypto');
const base = process.env.CVORTEX_B02_URL || 'http://127.0.0.1:8080';
assert.equal(new URL(base).hostname, '127.0.0.1');
const output = __dirname;
const checks = [], screenshots = [], pageErrors = [], consoleErrors = [], externalRequests = [], apiRequests = [];
const verify = (name, condition) => { assert.ok(condition, name); checks.push(name); };
(async () => {
  const browser = await chromium.launch({ headless: true, executablePath: process.env.CVORTEX_CHROMIUM_PATH });
  const context = await browser.newContext({ locale: 'en-US', timezoneId: 'Europe/Moscow', reducedMotion: 'reduce' });
  let signedIn = true, role = 'user';
  await context.route('**/*', async route => {
    const request = route.request(), url = new URL(request.url());
    if (url.origin !== base) { externalRequests.push(url.origin); return route.abort(); }
    const endpoint = url.pathname;
    if (!endpoint.startsWith('/api/v1/') && endpoint !== '/sanctum/csrf-cookie') return route.continue();
    apiRequests.push({ path: endpoint, method: request.method() });
    let body = {}, status = 200;
    if (endpoint === '/api/v1/me') { body = signedIn ? { data: { id: 'synthetic-user', email: 'synthetic@example.test', role, status: 'ACTIVE' } } : { error: { code: 'AUTH_REQUIRED' } }; status = signedIn ? 200 : 401; }
    else if (endpoint === '/api/v1/career') body = { data: { facts: [], claims: [], sources: [] } };
    else if (endpoint === '/api/v1/vacancies') body = { data: [] };
    else if (endpoint === '/api/v1/auth/logout') { verify('logout remains POST', request.method() === 'POST'); signedIn = false; status = 204; }
    else if (endpoint === '/sanctum/csrf-cookie') status = 204;
    else if (endpoint === '/api/v1/auth/login' || endpoint === '/api/v1/auth/register') {
      verify('credential submission remains POST', request.method() === 'POST');
      const payload = request.postDataJSON();
      if (endpoint.endsWith('/register')) verify('fragment submitted only in registration body', payload.invitation_token === 'synthetic-invite');
      signedIn = true; body = { data: { id: 'synthetic-user', email: 'synthetic@example.test', role, status: 'ACTIVE' } };
    } else if (endpoint === '/api/v1/diagnostics/incidents') body = { data: { data: [], current_page: 1, last_page: 1, total: 0 } };
    else throw new Error(`Unexpected API request: ${request.method()} ${endpoint}`);
    await route.fulfill({ status, contentType: 'application/json', body: status === 204 ? '' : JSON.stringify(body) });
  });
  const page = await context.newPage();
  page.on('pageerror', error => pageErrors.push(error.message));
  page.on('console', msg => { if (msg.type() === 'error') consoleErrors.push(msg.text()); });
  async function load(target = '/') { await page.goto(base + target); await page.waitForLoadState('networkidle'); }
  async function capture(name) { await page.screenshot({ path: path.join(output, name + '.png'), fullPage: true }); screenshots.push(name + '.png'); }
  async function current(name) { await page.waitForFunction(label => [...document.querySelectorAll('[aria-current]')].some(el => el.textContent === label), name); verify(`current destination ${name}`, true); }
  for (const [width, height] of [[320, 740], [390, 844], [768, 1024], [1024, 900], [1440, 1000]]) {
    await page.setViewportSize({ width, height }); await load(); await current('Career');
    const nav = page.getByRole('navigation', { name: 'Global navigation' });
    for (const name of ['Today', 'Opportunities', 'Career', 'Employers', 'Settings']) verify(`${width}: ${name} visible`, await nav.getByText(name, { exact: true }).isVisible());
    for (const name of ['Today', 'Employers', 'Settings']) verify(`${width}: ${name} unavailable`, await nav.getByRole('button', { name, exact: true }).isDisabled());
    verify(`${width}: no page overflow`, await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
    verify(`${width}: controls fit viewport`, await nav.locator('a,button').evaluateAll(nodes => nodes.every(el => { const r = el.getBoundingClientRect(); return r.left >= 0 && r.right <= innerWidth && r.height >= 44; })));
    verify(`${width}: identity readable`, await page.locator('.workspace-identity').isVisible());
    verify(`${width}: no admin utility for normal user`, await page.getByRole('link', { name: 'Diagnostics', exact: true }).count() === 0);
    await capture(`${width}-career`);
    await page.evaluate(() => { document.activeElement?.blur(); });
    await page.keyboard.press('Tab');
    // Explicit focus starts the native navigation sequence independently of previous browser history.
    await page.getByRole('link', { name: 'Skip to workspace' }).focus();
    verify(`${width}: skip focus visible`, await page.getByRole('link', { name: 'Skip to workspace' }).evaluate(el => getComputedStyle(el).outlineStyle !== 'none'));
    await page.keyboard.press('Enter');
    verify(`${width}: skip reaches main`, await page.locator('#workspace-content').evaluate(el => document.activeElement === el));
    await page.getByRole('link', { name: 'Opportunities', exact: true }).focus();
    verify(`${width}: link focus visible`, await page.getByRole('link', { name: 'Opportunities', exact: true }).evaluate(el => getComputedStyle(el).outlineStyle !== 'none'));
    await page.keyboard.press('Tab');
    verify(`${width}: Tab reaches Career`, await page.getByRole('link', { name: 'Career', exact: true }).evaluate(el => document.activeElement === el));
    await page.keyboard.press('Shift+Tab'); await page.keyboard.press('Enter');
    await page.waitForURL('**/#vacancy-title'); await current('Opportunities');
    verify(`${width}: target reached`, await page.locator('#vacancy-title').evaluate(el => { const r = el.getBoundingClientRect(); return r.top >= -1 && r.top < innerHeight; }));
    await capture(`${width}-opportunities`);
    await page.reload(); await page.waitForLoadState('networkidle'); await current('Opportunities');
    await page.goBack(); await current('Career');
    await page.goForward(); await current('Opportunities');
    await page.getByRole('link', { name: 'Career', exact: true }).click(); await page.waitForURL(base + '/'); await current('Career');
  }
  await page.setViewportSize({ width: 1440, height: 1000 });
  role = 'admin'; await load('/diagnostics');
  verify('admin diagnostics current utility', await page.getByRole('link', { name: 'Diagnostics', exact: true }).getAttribute('aria-current') === 'page');
  verify('diagnostics surface accessible', await page.getByRole('heading', { name: 'Error Center' }).isVisible());
  await capture('1440-admin-diagnostics');
  await page.setViewportSize({ width: 390, height: 844 }); await capture('390-admin-diagnostics');
  await page.getByRole('link', { name: 'Opportunities', exact: true }).click(); await page.waitForURL('**/#vacancy-title'); await current('Opportunities');
  await page.goBack(); await page.waitForURL('**/diagnostics'); await page.waitForLoadState('networkidle');
  verify('diagnostics Back retains admin access', await page.getByRole('heading', { name: 'Error Center' }).isVisible());
  role = 'user'; const before = apiRequests.filter(r => r.path.startsWith('/api/v1/diagnostics')).length; await load('/diagnostics');
  verify('non-admin direct route denied', await page.getByRole('heading', { name: 'Access denied' }).isVisible());
  verify('non-admin no diagnostics API read', before === apiRequests.filter(r => r.path.startsWith('/api/v1/diagnostics')).length);
  await capture('390-diagnostics-denied');
  await load(); await page.getByRole('button', { name: 'Sign out', exact: true }).click();
  await page.getByLabel('Email', { exact: true }).waitFor();
  verify('logout removes global navigation', await page.getByRole('navigation', { name: 'Global navigation' }).count() === 0);
  await capture('390-public-sign-in');
  await page.getByLabel('Email', { exact: true }).fill('synthetic@example.test');
  await page.getByLabel('Password', { exact: true }).fill('synthetic test passphrase only');
  await page.locator('form').getByRole('button', { name: 'Sign in', exact: true }).click();
  await page.getByRole('navigation', { name: 'Global navigation' }).waitFor(); verify('login mounts shared shell', true);
  signedIn = false; await load('/register#token=synthetic-invite');
  verify('invitation fragment removed from URL', !page.url().includes('synthetic-invite'));
  verify('registration outside global shell', await page.getByRole('navigation', { name: 'Global navigation' }).count() === 0);
  await page.reload(); await page.waitForLoadState('networkidle');
  verify('refresh cannot recover erased invitation', await page.getByRole('button', { name: 'Create account' }).isDisabled());
  // A new invitation is a fresh document entry, not a new fragment on an already mounted form.
  await load('/'); await load('/register#token=synthetic-invite');
  await page.getByLabel('Email', { exact: true }).fill('synthetic@example.test');
  await page.getByLabel('Password', { exact: true }).fill('synthetic test passphrase only');
  await page.getByLabel('Confirm password', { exact: true }).fill('synthetic test passphrase only');
  await capture('390-public-register');
  await page.getByRole('button', { name: 'Create account' }).click(); await page.getByRole('navigation', { name: 'Global navigation' }).waitFor();
  verify('registration mounts existing authenticated surface', true);
  verify('no unavailable destination requests', apiRequests.every(r => !/\/(today|employers|settings)(\/|$)/.test(r.path)));
  verify('no external requests', externalRequests.length === 0);
  verify('no browser exceptions/hydration errors', pageErrors.length === 0 && !consoleErrors.some(e => /hydration|did not match|react error/i.test(e)));
  const unexpectedConsoleErrors = consoleErrors.filter(e => !/Failed to load resource.*401/.test(e));
  verify('no unexpected browser console errors', unexpectedConsoleErrors.length === 0);
  fs.writeFileSync(path.join(output, 'browser-results.json'), JSON.stringify({ status: 'PASS', checkedAt: new Date().toISOString(), scope: 'Rendered production components; synthetic API fixtures; not live authenticated Laravel or manual AT/zoom evidence', browser: await browser.version(), platform: process.platform, base, viewports: [320,390,768,1024,1440], checks, screenshots, pageErrors, unexpectedConsoleErrors, expectedUnauthenticatedConsoleErrors: consoleErrors.filter(e => /Failed to load resource.*401/.test(e)).length, externalRequests, apiRequests, sourceSha256: Object.fromEntries(['workspace-shell.tsx','access-shell.tsx','career-workspace.tsx','globals.css'].map(f => [f,crypto.createHash('sha256').update(fs.readFileSync(path.resolve(__dirname, '../../../../apps/frontend/src/app',f))).digest('hex')])) }, null, 2) + '\n');
  console.log(`B02 browser PASS: ${checks.length} checks / ${screenshots.length} screenshots`);
  await browser.close();
})().catch(error => { console.error(error); fs.writeFileSync(path.join(output, 'browser-failure.json'), JSON.stringify({ status:'FAIL', message: error.message, checks, screenshots, pageErrors, consoleErrors },null,2)+'\n'); process.exit(1); });
