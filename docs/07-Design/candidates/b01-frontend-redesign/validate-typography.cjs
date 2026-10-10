/* Candidate-only DOM inventory, typography regressions and comparable captures. */
const { chromium } = require(process.env.CVORTEX_PLAYWRIGHT_MODULE || 'playwright');
const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');
const assert = require('node:assert/strict');
const before = process.argv.includes('--before');
const base = process.env.CVORTEX_B01_URL || 'http://127.0.0.1:8769';
if (new URL(base).hostname !== '127.0.0.1') throw new Error('Loopback preview required');
const output = path.join(__dirname, 'validation/typography', before ? 'before' : 'after');
fs.mkdirSync(output, { recursive: true });
const checks = [], captures = [], inventory = [], errors = [], external = [], contrast = [];
let roles = {};
const check = (name, value) => { assert.ok(value, name); checks.push(name); };
const sourceHashes = () => Object.fromEntries(['index.html', 'style.css', 'app.js', 'motion.js', 'tokens.css'].map(file => [file, crypto.createHash('sha256').update(fs.readFileSync(path.join(process.env.CVORTEX_SOURCE_ROOT || __dirname, file))).digest('hex')]));
(async () => {
  const browser = await chromium.launch({ headless: true, executablePath: process.env.CVORTEX_CHROMIUM_PATH });
  try {
    const context = await browser.newContext({ locale: 'ru-RU', timezoneId: 'Europe/Moscow', reducedMotion: 'reduce' });
    await context.route('**/*', async route => {
      if (new URL(route.request().url()).origin !== base) { external.push(route.request().url()); await route.abort(); }
      else await route.continue();
    });
    const page = await context.newPage();
    page.on('pageerror', e => errors.push(e.message));
    page.on('console', m => { if (m.type() === 'error') errors.push(m.text()); });
    page.on('response', r => { if (r.status() >= 400) errors.push(`${r.status()} ${r.url()}`); });
    const load = async (section = 'materials', scenario = 'normal', opportunity = 'northstar', level = 'work') => {
      const target = `${base}/?scenario=${scenario}&opportunity=${opportunity}&level=${level}#${section}`;
      const sameDocument = page.url().split('#')[0] === target.split('#')[0];
      await page.goto(target); if (sameDocument) await page.reload();
      await page.waitForLoadState('networkidle'); await page.evaluate(() => document.fonts.ready);
    };
    const audit = async name => {
      inventory.push({ name, viewport: page.viewportSize(), url: page.url().replace(base, ''), elements: await page.evaluate(() => {
        const items = [...document.querySelectorAll('h1,h2,h3,h4,h5,h6,strong,label,button,input,textarea,select,summary,small,p,a,[title],[role="status"],[role="alert"],[class*="type-"],.badge,.global-nav > span,.reference-meta span,.salary-basis')];
        return items.filter(e => e.getClientRects().length && !e.closest('[hidden],[inert]')).map(e => {
          const s = getComputedStyle(e);
          return { tag: e.tagName.toLowerCase(), id: e.id, classes: e.className, text: e.textContent.trim(), title: e.title, placeholder: e.getAttribute('placeholder'), accessibleLabel: e.getAttribute('aria-label'), font: s.fontFamily, size: s.fontSize, weight: s.fontWeight, leading: s.lineHeight, tracking: s.letterSpacing, color: s.color };
        });
      }) });
      if (!before) await headingCheck(name);
    };
    const headingCheck = async name => {
      const headings = await page.evaluate(() => [...document.querySelectorAll('h1,h2,h3,h4,h5,h6')].filter(e => e.getClientRects().length && !e.closest('[hidden],[inert]')).map(e => ({ level: Number(e.localName[1]), text: e.textContent, role: e.className })));
      check(`${name}: one visible meaningful H1`, headings.filter(h => h.level === 1 && h.text.trim()).length === 1);
      check(`${name}: one H1 in document semantics`, await page.locator('h1').count() === 1);
      check(`${name}: heading levels never skip`, headings[0].level === 1 && headings.every((h, i) => i === 0 || h.level <= headings[i - 1].level + 1));
      check(`${name}: heading visuals use explicit semantic roles`, headings.every(h => /\btype-(page|section|subsection|card|label)\b/.test(h.role)));
    };
    const layoutCheck = async name => {
      check(`${name}: no page overflow`, await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
      check(`${name}: important text is fully available`, await page.evaluate(() => [...document.querySelectorAll('#workspace-title,#company-name,#vacancy-salary,#vacancy-salary span,.salary-basis,#review-state,#domain-status,#proposal-copy,.list-role,.company-row,.reason-grid p')].filter(e => e.getClientRects().length).every(e => {
        const s = getComputedStyle(e); return s.textOverflow !== 'ellipsis' && !['hidden', 'clip'].includes(s.overflowX) && s.webkitLineClamp === 'none' && e.scrollWidth <= e.getBoundingClientRect().width + 1;
      })));
      const renderedContrast = await page.evaluate(() => {
        const rgb = value => value.match(/[\d.]+/g).map(Number);
        const lum = c => c.slice(0, 3).map(v => { v /= 255; return v <= .04045 ? v / 12.92 : ((v + .055) / 1.055) ** 2.4; }).reduce((sum, v, i) => sum + v * [.2126, .7152, .0722][i], 0);
        const background = el => { while (el) { const c = rgb(getComputedStyle(el).backgroundColor); if (c.length === 3 || c[3] === 1) return c; el = el.parentElement; } throw new Error('Opaque background required'); };
        const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT), rows = [];
        while (walker.nextNode()) {
          const node = walker.currentNode, el = node.parentElement;
          if (!node.textContent.trim() || el.closest('script,style,[hidden],[inert],:disabled,option') || !el.getClientRects().length) continue;
          const range = document.createRange(); range.selectNodeContents(node); if (!range.getBoundingClientRect().width) continue;
          const s = getComputedStyle(el), a = lum(rgb(s.color)), b = lum(background(el));
          rows.push({ text: node.textContent.trim().slice(0, 80), ratio: (Math.max(a, b) + .05) / (Math.min(a, b) + .05), size: parseFloat(s.fontSize) });
        }
        return { minimum: Math.min(...rows.map(r => r.ratio)), count: rows.length, failures: rows.filter(r => r.ratio < 4.5 || r.size < 12) };
      });
      contrast.push({ name, ...renderedContrast });
      check(`${name}: rendered text meets contrast and size floor`, !renderedContrast.failures.length);
    };
    const capture = async name => {
      await page.evaluate(() => { document.activeElement.blur(); scrollTo(0, 0); });
      await page.screenshot({ path: path.join(output, `${name}.png`), fullPage: true });
      captures.push({ file: `${name}.png`, url: page.url().replace(base, ''), viewport: page.viewportSize() });
    };
    for (const [width, height] of [[1440, 900], [390, 844]]) {
      await page.setViewportSize({ width, height });
      for (const section of ['overview', 'materials']) {
        await load(section); await audit(`${width}-${section}`); await capture(`${width}-${section}`);
      }
      await page.locator('#reject').click(); await audit(`${width}-rejected`); await capture(`${width}-rejected`);
    }
    await page.setViewportSize({ width: 1440, height: 900 });
    for (const section of ['requirements', 'employer', 'history']) { await load(section); await audit(`section-${section}`); }
    for (const scenario of ['loading', 'empty', 'failed', 'stale', 'blocked', 'pending', 'approval']) { await load('materials', scenario); await audit(`state-${scenario}`); }
    await load('materials', 'normal', 'cedar'); await audit('cedar-conflict');
    await load(); await page.locator('#edit').click(); await audit('editing');
    await page.locator('#proposal-edit').fill('Неподтверждённый опыт.'); await page.locator('#save-edit').click(); await audit('unsupported-edit');
    await load(); await page.locator('#accept').click(); await audit('accepted'); await page.locator('#approve').click(); await audit('approved');
    await page.locator('#materials [data-open-evidence]').click(); await audit('evidence'); await page.keyboard.press('Escape');
    await page.locator('#account-trigger').click();
    for (const role of ['user', 'admin', 'guest']) { await page.locator('#demo-session').selectOption(role); await audit(`account-${role}`); }
    await page.locator('#demo-session').selectOption('admin'); await page.locator('#diagnostics-action').click(); await audit('account-diagnostics');
    await page.locator('#demo-session').selectOption('user'); await page.locator('#profile-action').click(); await audit('account-profile');
    await page.locator('#settings-action').click(); await audit('account-settings');
    await page.keyboard.press('Escape');
    await page.setViewportSize({ width: 320, height: 640 }); await load('materials', 'normal', 'northstar', 'list'); await audit('mobile-list');
    await page.locator('#opportunity-filter').fill('Нет такой вакансии'); await audit('empty-search');
    await page.goto(`${base}/?scenario=normal&opportunity=northstar&level=work&salary=missing#overview`); await page.waitForLoadState('networkidle'); await audit('missing-salary');
    if (!before) {
      for (const [width, height] of [[1440, 900], [768, 1024], [390, 844], [320, 640]]) {
        await page.setViewportSize({ width, height });
        for (const section of ['overview', 'requirements', 'materials', 'employer', 'history']) {
          await load(section); await headingCheck(`${width}-${section}`); await layoutCheck(`${width}-${section}`);
          check(`${width}-${section}: body text remains readable`, await page.locator(`#${section} p`).evaluateAll(es => es.filter(e => e.getClientRects().length).every(e => parseFloat(getComputedStyle(e).fontSize) >= 14)));
        }
        for (const state of ['normal', 'loading', 'empty', 'failed', 'stale', 'blocked', 'pending', 'approval']) {
          await load('materials', state); await headingCheck(`${width}-${state}`); await layoutCheck(`${width}-${state}`);
          check(`${width}-${state}: exact approval state retained`, await page.locator('#approve').isDisabled() === (state !== 'approval'));
          if (['stale', 'blocked', 'pending'].includes(state)) {
            check(`${width}-${state}: acceptance remains blocked`, await page.locator('#accept').isDisabled());
            check(`${width}-${state}: review level distinguishes warning from block`, await page.locator('#review-state').getAttribute('data-level') === (state === 'blocked' ? 'error' : 'warning'));
          }
          if (['loading', 'empty', 'failed'].includes(state)) check(`${width}-${state}: missing review stays unavailable`, !await page.locator('#review-card').isVisible());
        }
        await load();
        check(`${width}: salary compact form is mathematically exact`, await page.locator('#vacancy-salary').evaluate(e => {
          const text = e.textContent.trim(), match = text.match(/^(\d+)–(\d+) тыс\. ₽$/);
          return Boolean(match && Number(match[1]) * 1000 === Number(e.dataset.min) && Number(match[2]) * 1000 === Number(e.dataset.max) && Number(e.dataset.min) === 240000 && Number(e.dataset.max) === 290000);
        }));
        check(`${width}: salary retains full accessible amounts and basis`, await page.locator('#vacancy-salary').evaluate(e => e.getAttribute('aria-label').replaceAll('\u00a0', ' ') === '240 000–290 000 ₽ в месяц, до налогов'));
        check(`${width}: salary digits never split`, await page.locator('.type-number').evaluate(e => getComputedStyle(e).whiteSpace === 'nowrap' && e.getClientRects().length === 1));
        await page.locator('#reject').click();
        check(`${width}: rejection is neutral and explicit`, await page.locator('#review-state').getAttribute('data-level') === 'neutral' && await page.locator('#review-state').textContent() === 'Правка отклонена');
        check(`${width}: rejection keeps approval blocked and old text`, await page.locator('#approve').isDisabled() && await page.locator('#accept').isDisabled() && await page.locator('.diff > div:first-child p').textContent() === 'Тестировал серверные сервисы.');
        check(`${width}: rejection announced as status`, await page.locator('#action-status').getAttribute('role') === 'status');
        await load(); await page.locator('#accept').click();
        check(`${width}: accepted text is still unapproved`, await page.locator('#review-state').getAttribute('data-level') === 'warning' && !await page.locator('#approve').isDisabled() && (await page.locator('#review-state').textContent()).includes('текст не одобрен'));
        await page.locator('#approve').click();
        check(`${width}: explicit approval is success, never submission`, await page.locator('#review-state').getAttribute('data-level') === 'success' && (await page.locator('#action-status').textContent()).includes('Отклик не отправлен'));
        await load('materials', 'normal', 'cedar');
        check(`${width}: Cedar salary and conflict are retained`, await page.locator('#vacancy-salary').textContent() === '250–300 тыс. ₽' && await page.locator('#accept').isDisabled() && await page.locator('#approve').isDisabled());
        await load();
        await page.evaluate(() => {
          document.getElementById('workspace-title').textContent = 'Старший инженер по автоматизации тестирования распределённых серверных систем и инфраструктуры';
          document.getElementById('company-name').textContent = 'Лаборатория распределённых систем Northstar International Research and Development';
          document.querySelector('.list-role').textContent = document.getElementById('workspace-title').textContent;
          const row = document.querySelector('.company-row'); row.lastChild.textContent = document.getElementById('company-name').textContent;
        });
        await layoutCheck(`${width}-long-names`);
        check(`${width}: long title wraps naturally at word boundaries`, await page.locator('#workspace-title').evaluate(e => {
          const node = e.firstChild, text = node.textContent, pattern = /\S+/g; let match;
          while ((match = pattern.exec(text))) { const range = document.createRange(); range.setStart(node, match.index); range.setEnd(node, match.index + match[0].length); if (range.getClientRects().length !== 1) return false; }
          return getComputedStyle(e).hyphens === 'none';
        }));
        await capture(`${width}-long-names`);
        if (width < 768) {
          await page.locator('#back-level').click(); await headingCheck(`${width}-list`); await layoutCheck(`${width}-long-list`); await capture(`${width}-long-list`);
          await page.locator('[data-opportunity="northstar"]').click(); await page.locator('#materials [data-open-evidence]').click();
          await headingCheck(`${width}-evidence`); await layoutCheck(`${width}-evidence`); await capture(`${width}-evidence`);
        }
      }
      for (const width of [360, 480, 600, 720, 1024, 1180, 1280]) {
        await page.setViewportSize({ width, height: 900 }); await load(); await headingCheck(`${width}-intermediate`); await layoutCheck(`${width}-intermediate`);
        if (width >= 1280) check(`${width}: actions stay close to comparison`, await page.locator('.actions').evaluate(e => e.getBoundingClientRect().top - document.querySelector('.diff').getBoundingClientRect().bottom <= 25));
      }
      await page.setViewportSize({ width: 1440, height: 900 }); await load();
      roles = await page.evaluate(() => Object.fromEntries(['page', 'section', 'subsection', 'card', 'nav', 'label', 'body', 'secondary', 'support', 'caption', 'status', 'number'].map(role => {
        const el = [...document.querySelectorAll(role === 'status' ? '.type-status,.badge' : `.type-${role}`)].find(e => e.getClientRects().length);
        const s = getComputedStyle(el); return [role, { font: s.fontFamily, size: s.fontSize, weight: s.fontWeight, leading: s.lineHeight, tracking: s.letterSpacing, color: s.color }];
      })));
      check('twelve visible typography roles recorded', Object.keys(roles).length === 12);
      check('one family for Russian and Latin working text', Object.entries(roles).filter(([role]) => role !== 'number').every(([, style]) => style.font === 'Arial, sans-serif'));
      check('body is 16px and titles have distinct hierarchy', roles.body.size === '16px' && roles.page.size === '24px' && roles.section.size === '20px' && roles.card.size === '16px');
      check('small text uses normal tracking', roles.caption.tracking === 'normal' || roles.caption.tracking === '0px');
      await page.locator('#edit').click(); await page.locator('#proposal-edit').fill('Неподтверждённый опыт.'); await page.locator('#save-edit').click();
      check('unsupported text still fails closed with an alert', await page.locator('#action-status').getAttribute('role') === 'alert' && await page.locator('#accept').isDisabled() && await page.locator('#approve').isDisabled());
      await page.locator('#cancel-edit').click(); await page.locator('#reject').click();
      check('rejection after editing restores neutral status semantics', await page.locator('#action-status').getAttribute('role') === 'status' && await page.locator('#review-state').getAttribute('data-level') === 'neutral');
    }
    check('no browser errors', !errors.length); check('no external requests', !external.length);
    fs.writeFileSync(path.join(output, 'inventory.json'), JSON.stringify(inventory, null, 2) + '\n');
    fs.writeFileSync(path.join(output, 'results.json'), JSON.stringify({ status: 'PASS', phase: before ? 'before-inventory' : 'after', checkedAt: new Date().toISOString(), browser: browser.version(), locale: 'ru-RU', timezone: 'Europe/Moscow', reducedMotion: 'reduce', sourceHashes: sourceHashes(), roles, checks, captures, contrast, errors, external }, null, 2) + '\n');
    console.log(`Typography ${before ? 'before' : 'after'}: PASS; ${checks.length} checks; ${inventory.length} DOM contexts; ${captures.length} captures`);
  } catch (e) {
    fs.writeFileSync(path.join(output, 'results.json'), JSON.stringify({ status: 'FAIL', failure: e.message, checks, captures, errors, external }, null, 2) + '\n'); throw e;
  } finally { await browser.close(); }
})().catch(e => { console.error(e); process.exitCode = 1; });
