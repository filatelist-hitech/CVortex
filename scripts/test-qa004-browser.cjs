// Targeted synthetic UI regression; fixture provisioning is separate from browser actions.
const fs = require('node:fs');
const assert = require('node:assert/strict');
const { chromium } = require(process.env.QA_PLAYWRIGHT_MODULE);
const base = process.env.QA_BASE_URL || 'http://127.0.0.1:18084';
const output = process.env.QA_OUTPUT_DIR;
const password = process.env.QA_SYNTHETIC_PASSWORD;
const runId = process.env.QA_RUN_ID;
assert(/^[a-z0-9]+$/.test(runId || ''), 'Synthetic run ID required');
assert(output && password, 'Missing private QA environment');
assert(new URL(base).hostname === '127.0.0.1' && new URL(base).port === '18084', 'Isolated QA004 URL required');
const checks = [], requests = [], errors = [], external = [];
let browser, page, context;
const save = () => fs.writeFileSync(`${output}/browser.json`, JSON.stringify({ classification: 'MOCKED_E2E: real UI, Laravel, PostgreSQL, Redis/Horizon; local deterministic HTTP provider', checks, requests, errors, external, manual_accessibility: 'OPEN / NOT TESTED', live_provider: 'NOT VERIFIED' }, null, 2));
async function check(id, action) {
  try { await action(); checks.push({ id, status: 'PASS' }); console.log(`PASS ${id}`); }
  catch (error) { checks.push({ id, status: 'FAIL', message: error.message }); throw error; }
  finally { save(); }
}
async function get(path) { const response = await context.request.get(base + path, { headers: { Origin: base, Referer: base + '/' } }); assert.equal(response.status(), 200); return (await response.json()).data; }
async function poll(read, accept) {
  for (let attempt = 0; attempt < 60; attempt++) { const value = await read(); if (accept(value)) return value; await new Promise(resolve => setTimeout(resolve, 500)); }
  throw new Error('Timed out waiting for server state');
}
async function login(target, email) {
  await target.goto(base + '/career'); await target.waitForLoadState('networkidle');
  await target.getByLabel('Электронная почта').fill(email);
  await target.getByLabel('Пароль', { exact: true }).fill(password);
  await target.locator('form').getByRole('button', { name: 'Войти', exact: true }).click();
  await target.getByRole('heading', { name: 'Факты вашей карьеры' }).waitFor();
}
(async () => {
  try {
    browser = await chromium.launch({ headless: true, executablePath: process.env.QA_CHROMIUM_PATH });
    context = await browser.newContext({ locale: 'ru-RU', timezoneId: 'Europe/Moscow', reducedMotion: 'reduce' });
    page = await context.newPage();
    page.on('pageerror', error => errors.push(error.message));
    page.on('request', request => { if (new URL(request.url()).origin !== base) external.push(new URL(request.url()).origin); });
    page.on('response', response => { const path = new URL(response.url()).pathname; if (path.startsWith('/api/')) requests.push({ path, method: response.request().method(), status: response.status() }); });
    await check('login-owner', () => login(page, `user-a-${runId}@qa004.example.test`));
    await check('manual-confirmed-fact-via-UI', async () => {
      await page.getByLabel('Тип факта').fill('skill');
      await page.getByLabel('Подтверждённое утверждение').fill('Built Laravel APIs.');
      await page.getByRole('button', { name: 'Добавить подтверждённый факт' }).click();
      await poll(() => get('/api/v1/career'), career => career.facts.some(fact => fact.status === 'CONFIRMED'));
    });
    let vacancy;
    await check('baseline-vacancy-via-UI', async () => {
      await page.getByLabel('Текст вакансии', { exact: true }).fill('QA004 Backend Engineer\nCompany: QA004 Synthetic\nLaravel is required.');
      await page.getByRole('button', { name: 'Сохранить и проанализировать' }).click();
      vacancy = (await poll(() => get('/api/v1/vacancies'), list => list.some(item => item.analysis_status === 'COMPLETED')))[0];
    });
    await check('extracted-pending-and-explicit-confirm-via-UI', async () => {
      await page.getByLabel('Текст об опыте или резюме').fill('Built Laravel APIs.');
      await page.getByRole('button', { name: 'Извлечь факты для проверки' }).click();
      await page.getByRole('button', { name: 'Подтвердить', exact: true }).waitFor();
      const before = await get('/api/v1/career');
      assert(before.facts.some(fact => fact.status === 'PENDING' && fact.fact_type === 'experience'));
      await page.getByRole('button', { name: 'Подтвердить', exact: true }).click();
      await page.getByText('Нужен выбор источника', { exact: true }).waitFor();
    });
    const unresolved = await get('/api/v1/career');
    const claim = unresolved.claims.find(item => item.truth_status === 'USER_RESOLUTION_REQUIRED'); assert(claim);
    const factCount = unresolved.facts.length;
    await check('no-auto-resolution-on-page-open', async () => {
      await page.reload(); await page.waitForLoadState('networkidle');
      assert.equal((await get('/api/v1/career')).claims.find(item => item.id === claim.id).truth_status, 'USER_RESOLUTION_REQUIRED');
      assert.equal(requests.filter(item => item.path.endsWith('/resolve')).length, 0);
    });
    const card = page.locator('.career-claim').filter({ hasText: 'Нужен выбор источника' });
    await card.locator('summary').click();
    await check('source-options-and-responsive-smoke', async () => {
      assert.equal(await card.getByRole('radio').count(), 2);
      assert(await card.getByText('Тип: Навык', { exact: true }).isVisible());
      assert(await card.getByText('Тип: Опыт', { exact: true }).isVisible());
      assert(await card.getByText('Источник: Из текста', { exact: true }).isVisible());
      for (const [width, height] of [[320, 640], [390, 844], [768, 1024], [1440, 900]]) {
        await page.setViewportSize({ width, height });
        await card.scrollIntoViewIfNeeded();
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1), false, `Overflow at ${width}`);
        await page.setViewportSize({ width, height: 1200 });
        await card.evaluate(node => node.scrollIntoView({ block: 'center' }));
        await card.screenshot({ path: `${output}/resolution-${width}.png` });
        await page.setViewportSize({ width, height });
      }
    });
    await check('network-error-choice-preserved-and-no-auto-retry', async () => {
      await card.getByRole('radio').first().check();
      await page.route('**/api/v1/career/claims/*/resolve', route => route.abort('failed'));
      await card.getByRole('button', { name: 'Подтвердить источник утверждения' }).click();
      await card.getByRole('alert').waitFor();
      assert(await card.getByRole('radio').first().isChecked());
      assert.equal((await get('/api/v1/career')).claims.find(item => item.id === claim.id).truth_status, 'USER_RESOLUTION_REQUIRED');
      await page.unroute('**/api/v1/career/claims/*/resolve');
    });
    await check('keyboard-only-resolution-correct-PATCH', async () => {
      // Reset page so there is no retained mouse selection; resolve using native keyboard controls only.
      await page.reload(); await page.waitForLoadState('networkidle');
      const target = page.locator('.career-claim').filter({ hasText: 'Нужен выбор источника' });
      const summary = target.locator('summary');
      for (let tabs = 0; tabs < 150 && !await summary.evaluate(node => node === document.activeElement); tabs++) await page.keyboard.press('Tab');
      assert(await summary.evaluate(node => node === document.activeElement), 'Summary reachable by keyboard');
      await page.keyboard.press('Enter'); await page.keyboard.press('Tab');
      assert(await target.getByRole('radio').first().evaluate(node => node === document.activeElement));
      await page.keyboard.press('Space');
      const selected = await target.getByRole('radio').first().inputValue();
      await page.keyboard.press('Tab');
      assert(await target.getByRole('button', { name: 'Подтвердить источник утверждения' }).evaluate(node => node === document.activeElement));
      const response = page.waitForResponse(response => response.url().endsWith(`/claims/${claim.id}/resolve`));
      await page.keyboard.press('Enter');
      const result = await response; assert.equal(result.status(), 200);
      assert.deepEqual(result.request().postDataJSON(), { career_fact_id: selected });
      assert.equal(result.request().method(), 'PATCH');
      const resolved = page.locator('.career-claim').filter({ hasText: 'Подтверждено фактами' });
      await resolved.getByRole('status').filter({ hasText: 'Источник утверждения подтверждён' }).waitFor();
      await poll(async () => resolved.getByRole('status').evaluate(node => node === document.activeElement), Boolean);
      assert.equal(await resolved.getByRole('radio').count(), 0);
    });
    await check('resolution-persists-after-refresh-no-fact-mutation', async () => {
      await page.reload(); await page.waitForLoadState('networkidle');
      const career = await get('/api/v1/career');
      assert.equal(career.claims.find(item => item.id === claim.id).truth_status, 'PASS');
      assert.equal(career.facts.length, factCount);
      assert(career.facts.every(fact => fact.status === 'CONFIRMED'));
      await page.locator('.career-claim').getByText('Подтверждено фактами', { exact: true }).waitFor();
    });
    await check('revalidation-via-existing-vacancy-UI', async () => {
      await page.locator('.vacancy-list button').filter({ hasText: 'QA004 Backend Engineer' }).click();
      await page.getByRole('button', { name: 'Reanalyze current facts' }).click();
      await poll(() => get(`/api/v1/vacancies/${vacancy.id}`), detail => detail.analysis_status === 'COMPLETED' && !detail.analysis.stale);
    });
    let preparation;
    await check('application-generation-after-resolution-via-UI', async () => {
      await page.goto(base + `/?opportunity=${vacancy.id}#materials`); await page.waitForLoadState('networkidle');
      await page.getByRole('button', { name: 'Подготовить рекомендации и черновики' }).click();
      await page.getByRole('button', { name: 'Принять правку' }).first().waitFor();
      // Read-only GET: identify preparation from UI's own POST response captured below.
      const record = requests.findLast(item => item.path.endsWith('/generate') && item.status === 200);
      assert(record, 'Generation was performed through UI');
      for (const button of await page.getByRole('button', { name: 'Одобрить этот текст' }).all()) assert(await button.isDisabled(), 'Approval disabled before acceptance');
    });
    await check('human-acceptance-and-approval-remain-explicit', async () => {
      const response = page.waitForResponse(response => response.request().method() === 'PATCH' && response.url().includes('/draft-items/'));
      await page.getByRole('button', { name: 'Принять правку' }).first().click();
      preparation = (await (await response).json()).data;
      assert(preparation.items.every(item => item.status !== 'APPROVED'));
      const approval = page.waitForResponse(response => response.url().endsWith('/approve'));
      await page.getByRole('button', { name: 'Одобрить этот текст' }).first().click();
      const approved = (await (await approval).json()).data;
      assert(approved.items.some(item => item.status === 'APPROVED'));
      await page.reload(); await page.waitForLoadState('networkidle');
      const saved = await get(`/api/v1/applications/preparations/${preparation.id}`);
      assert(saved.items.some(item => item.status === 'APPROVED'));
    });
    await check('foreign-owner-UI-has-no-private-evidence', async () => {
      const second = await browser.newContext(); const other = await second.newPage();
      await login(other, `user-b-${runId}@qa004.example.test`);
      const response = await second.request.get(base + '/api/v1/career', { headers: { Origin: base } });
      assert.deepEqual((await response.json()).data.facts, []);
      assert.equal(await other.getByText('Built Laravel APIs.', { exact: true }).count(), 0);
      for (const path of [`/api/v1/applications/preparations/${preparation.id}`, `/api/v1/vacancies/${vacancy.id}`]) assert.equal((await second.request.get(base + path, { headers: { Origin: base } })).status(), 404);
      await second.close();
    });
    await check('CSRF-and-cross-owner-resolution-negative-probes', async () => {
      const noToken = await context.request.patch(base + `/api/v1/career/claims/${claim.id}/resolve`, {
        headers: { Origin: base, Accept: 'application/json' }, data: { career_fact_id: unresolved.facts[0].id },
      });
      assert.equal(noToken.status(), 419, 'Session write without CSRF must fail');
      const second = await browser.newContext(); const other = await second.newPage();
      await login(other, `user-b-${runId}@qa004.example.test`);
      const csrf = (await second.cookies()).find(cookie => cookie.name === 'XSRF-TOKEN'); assert(csrf);
      const foreign = await second.request.patch(base + `/api/v1/career/claims/${claim.id}/resolve`, {
        headers: { Origin: base, Accept: 'application/json', 'X-XSRF-TOKEN': decodeURIComponent(csrf.value) }, data: { career_fact_id: unresolved.facts[0].id },
      });
      assert.equal(foreign.status(), 404, 'Foreign Claim stays hidden');
      const approval = await second.request.post(base + `/api/v1/applications/draft-items/${preparation.items[0].id}/approve`, {
        headers: { Origin: base, Accept: 'application/json', 'X-XSRF-TOKEN': decodeURIComponent(csrf.value) },
      });
      assert.equal(approval.status(), 404, 'Foreign approval stays hidden');
      await second.close();
    });
    await check('no-browser-errors-no-external-requests-no-auto-submit', async () => {
      assert.deepEqual(errors, []); assert.deepEqual(external, []);
      assert(!requests.some(item => /submit|send/.test(item.path)));
    });
  } catch (error) { console.error(error.message); process.exitCode = 1; }
  finally { save(); if (browser) await browser.close(); }
})();
