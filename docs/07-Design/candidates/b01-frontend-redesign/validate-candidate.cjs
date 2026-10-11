/* Additional candidate checks: composition, sections, font, actual reduced CSS, honest salary absence. */
const { chromium } = require(process.env.CVORTEX_PLAYWRIGHT_MODULE || 'playwright');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const base = 'http://127.0.0.1:8769';
const output = path.join(__dirname,'validation');
const checks = [], captures = [], errors = [], external = [];
const check = (name,value) => { assert.ok(value,name); checks.push(name); };
(async () => {
 const browser = await chromium.launch({headless:true,executablePath:process.env.CVORTEX_CHROMIUM_PATH});
 try {
  const context = await browser.newContext({locale:'ru-RU',timezoneId:'Europe/Moscow',reducedMotion:'reduce'});
  await context.route('**/*',async route => { if(new URL(route.request().url()).origin!==base){external.push(route.request().url());await route.abort();}else await route.continue(); });
  const page=await context.newPage(); page.on('pageerror',e=>errors.push(e.message)); page.on('console',m=>{if(m.type()==='error')errors.push(m.text());});
  const overflow=async name=>check(`${name}: no horizontal overflow`,await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
  const capture=async name=>{await page.evaluate(()=>scrollTo(0,0));await page.screenshot({path:path.join(output,`${name}.png`),fullPage:true});captures.push({file:`${name}.png`,url:page.url().replace(base,''),viewport:page.viewportSize()});};
  for(const [width,height] of [[1440,900],[768,1024],[390,844],[320,640]]){
   await page.setViewportSize({width,height});
   await page.goto(`${base}/?scenario=normal&opportunity=northstar&level=work#materials`); await page.waitForLoadState('networkidle'); await page.evaluate(()=>document.fonts.ready);
   check(`${width}: local Space Grotesk loaded`,await page.evaluate(()=>[...document.fonts].some(f=>f.family==='Space Grotesk'&&f.status==='loaded')));
   check(`${width}: distinct content approval`,await page.locator('#approve').isDisabled()&&!await page.locator('#accept').isDisabled());
   if(width===1440){
    check('desktop: global nav is a vertical rail',await page.locator('.global-nav').evaluate(e=>getComputedStyle(e).display==='grid'));
    check('desktop: action belongs to comparison column',await page.locator('#accept').evaluate(e=>e.getBoundingClientRect().left===document.querySelector('.diff').getBoundingClientRect().left));
    check('desktop: primary action follows proposed text',await page.locator('#accept').evaluate(e=>e.getBoundingClientRect().top>document.getElementById('proposal-copy').getBoundingClientRect().bottom));
   }
   for(const section of ['overview','requirements','materials','employer','history']){
    if(width<768)await page.locator('#section-select').selectOption(section);else await page.locator(`[data-section="${section}"]`).first().click();
    check(`${width}-${section}: section visible and same opportunity`,await page.locator(`#${section}`).isVisible()&&await page.locator('#company-name').textContent()==='Northstar Labs');
    await overflow(`${width}-${section}`); await capture(`${width}-section-${section}`);
   }
   if(width<768){await page.locator('.global-menu > summary').focus();await page.keyboard.press('Enter');check(`${width}: global menu keyboard opens`,await page.locator('.global-nav').isVisible());check(`${width}: menu summary focus visible`,await page.locator('.global-menu > summary').evaluate(e=>getComputedStyle(e).outlineStyle==='solid'));await page.keyboard.press('Enter');}
   await page.locator('#account-trigger').click();
   check(`${width}: reduced motion disables actual dialog animation`,await page.locator('#account-dialog').evaluate(e=>getComputedStyle(e).animationName==='none'));
   check(`${width}: reduced motion disables actual button transition`,await page.locator('#close-account').evaluate(e=>getComputedStyle(e).transitionDuration==='0s'));
   await page.keyboard.press('Escape');await page.waitForFunction(()=>!document.getElementById('account-dialog').open);
  }
  await page.setViewportSize({width:390,height:844}); await page.goto(`${base}/?scenario=normal&opportunity=northstar&level=work&salary=missing#overview`);await page.waitForLoadState('networkidle');
  check('missing salary: honest label and no invented period',await page.locator('#vacancy-salary').textContent()==='Не указана'&&!(await page.locator('.salary-basis').textContent()).includes('до налогов'));
  await page.locator('#scenario-select').selectOption('pending');check('missing salary survives local state navigation',await page.locator('#vacancy-salary').textContent()==='Не указана');await capture('390-salary-missing');
  await page.locator('#section-select').selectOption('overview');check('pending overview never claims current confirmed support',!(await page.locator('#summary-evidence').textContent()).startsWith('Подтверждено'));
  // Additional 720 CSS px reflow. This does not claim native browser zoom.
  await page.setViewportSize({width:720,height:900});await page.goto(`${base}/?scenario=normal&opportunity=northstar&level=work#materials`);await page.waitForLoadState('networkidle');await overflow('720-reflow');await capture('720-reflow');
  await page.emulateMedia({reducedMotion:'no-preference'});await page.locator('#account-trigger').click();check('normal motion: dialog uses live spring presentation',await page.locator('#account-dialog').evaluate(e=>e.style.willChange==='transform, opacity'));await page.keyboard.press('Escape');
  check('candidate: zero browser errors',!errors.length);check('candidate: zero external requests',!external.length);
  fs.writeFileSync(path.join(output,'candidate-results.json'),JSON.stringify({status:'PASS',checkedAt:new Date().toISOString(),checks,captures,errors,external,limitations:['Native browser 200% zoom not performed','Manual screen reader and named human review pending']},null,2)+'\n');
  console.log(`Candidate additions: PASS; ${checks.length} checks; ${captures.length} captures`);
 }catch(e){fs.writeFileSync(path.join(output,'candidate-results.json'),JSON.stringify({status:'FAIL',failure:e.message,checks,errors,external},null,2)+'\n');throw e;}finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
