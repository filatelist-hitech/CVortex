// Local rendered evidence. All production API requests use synthetic fixtures.
const { chromium } = require(process.env.CVORTEX_PLAYWRIGHT_MODULE || 'playwright');
const fs = require('node:fs');
const path = require('node:path');
const stage = process.argv[2] || 'before';
const output = path.join(__dirname, stage);
fs.mkdirSync(output, { recursive: true });
(async () => {
  const browser = await chromium.launch({ headless: true, executablePath: process.env.CVORTEX_CHROMIUM_PATH });
  const context = await browser.newContext({ locale: 'ru-RU', timezoneId: 'Europe/Moscow', reducedMotion: 'reduce' });
  const errors = [], requests = [], metrics = [];
  await context.route('**/*', async route => {
    const url = new URL(route.request().url());
    if (!['http://127.0.0.1:8080', 'http://127.0.0.1:8768'].includes(url.origin)) throw new Error('External request: ' + url.origin);
    if (!url.pathname.startsWith('/api/')) return route.continue();
    requests.push(url.pathname);
    let body;
    if (url.pathname === '/api/v1/me') body = { data: { id: 'synthetic-user', email: 'synthetic@example.test', role: 'user', status: 'ACTIVE' } };
    else if (url.pathname === '/api/v1/career') body = { data: { facts: [], claims: [], sources: [] } };
    else if (url.pathname === '/api/v1/vacancies') body = { data: [] };
    else throw new Error('Unexpected API: ' + url.pathname);
    await route.fulfill({ contentType: 'application/json', body: JSON.stringify(body) });
  });
  const page = await context.newPage();
  page.on('pageerror', error => errors.push(error.message));
  for (const width of [1440, 768, 390, 320]) {
    await page.setViewportSize({ width, height: width === 1440 ? 1000 : width === 768 ? 1024 : 844 });
    for (const [name, url] of [['canonical', 'http://127.0.0.1:8768/'], ['production', 'http://127.0.0.1:8080/']]) {
      if (stage !== 'before' && name === 'canonical') continue;
      await page.goto(url); await page.waitForLoadState('networkidle'); await page.evaluate(() => document.fonts.ready);
      await page.screenshot({ path: path.join(output, `${width}-${name}.png`), fullPage: true });
      metrics.push(await page.evaluate(({width,name}) => {
        const selectors = name === 'canonical' ? ['.site-header','.global-nav','h1','h2','button','textarea'] : ['.workspace-global-header','.workspace-destinations','h1','h2','button','textarea'];
        return { width, name, overflow: document.documentElement.scrollWidth > innerWidth, elements: selectors.map(selector => {
          const el=document.querySelector(selector), css=getComputedStyle(el), rect=el.getBoundingClientRect();
          return {selector, text:el.textContent.slice(0,90), x:rect.x,y:rect.y,width:rect.width,height:rect.height,font:css.font,fontSize:css.fontSize,color:css.color,background:css.backgroundColor,padding:css.padding,borderRadius:css.borderRadius};
        }) };
      }, {width,name}));
      if (width < 768) {
        const menu=page.locator(name === 'canonical' ? '.global-menu' : '.workspace-menu');
        if (await menu.count()) {
          await menu.locator('summary').click();
          await page.screenshot({path:path.join(output,`${width}-${name}-menu.png`),fullPage:true});
        }
      }
    }
  }
  fs.writeFileSync(path.join(output,'metrics.json'),JSON.stringify({stage,browser:await browser.version(),platform:process.platform,errors,requests,metrics},null,2)+'\n');
  await browser.close();
  if(errors.length) throw new Error(errors.join('\n'));
  console.log(stage+' captures complete');
})().catch(error => {console.error(error);process.exit(1);});
