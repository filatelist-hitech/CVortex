const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
const {createRequire}=require('node:module');
const frontendRequire=createRequire(path.resolve(__dirname,'../../../../apps/frontend/package.json'));
const {chromium}=process.env.CVORTEX_PLAYWRIGHT_MODULE?require(process.env.CVORTEX_PLAYWRIGHT_MODULE):frontendRequire('playwright');
const checks=[],errors=[],external=[];
const verify=(name,value)=>{assert.ok(value,name);checks.push(name);};
(async()=>{
 const browser=await chromium.launch({headless:true,...(process.env.CVORTEX_CHROMIUM_PATH?{executablePath:process.env.CVORTEX_CHROMIUM_PATH}:{})});
 const context=await browser.newContext({locale:'ru-RU',timezoneId:'Europe/Moscow',deviceScaleFactor:1,reducedMotion:'no-preference'});
 await context.route('**/*',async route=>{const u=new URL(route.request().url());if(!['http://127.0.0.1:8080','http://127.0.0.1:8768'].includes(u.origin)){external.push(u.origin);return route.abort();}if(u.pathname.startsWith('/api/')){assert.equal(u.pathname,'/api/v1/me');return route.fulfill({json:{data:{id:'synthetic',email:'synthetic@example.test',role:'user',status:'ACTIVE'}}});}return route.continue();});
 const page=await context.newPage();page.on('pageerror',e=>errors.push(e.message));
 for(const [width,height]of[[1440,900],[768,1024],[390,844],[320,640]])for(const port of[8768,8080]){
  const name=`${width}/${port}`;await page.emulateMedia({reducedMotion:'no-preference'});await page.setViewportSize({width,height});
  await page.goto(`http://127.0.0.1:${port}/?opportunity=northstar&level=work${port===8080?'&demo=b01':''}#materials`);await page.waitForLoadState('networkidle');if(port===8080)await page.locator('[data-demo=true]').waitFor();
  await page.locator('#account-trigger').click();await page.waitForFunction(()=>document.querySelector('#account-dialog').style.transform==='');
  await page.locator('#close-account').click();verify(name+': close remains modal while spring exits',await page.locator('#account-dialog').evaluate(e=>e.open&&e.style.willChange==='transform, opacity'));
  // Dispatch new input during the exit without locator auto-waiting for animation stability.
  await page.locator('.account-actions button').first().dispatchEvent('pointerdown');
  await page.waitForFunction(()=>document.querySelector('#account-dialog').style.transform==='');
  verify(name+': pointer redirects exit and retains modal',await page.locator('#account-dialog').evaluate(e=>e.matches(':modal')));
  await page.keyboard.press('Escape');await page.waitForFunction(()=>!document.querySelector('#account-dialog').open);
  verify(name+': Escape restores account focus',await page.locator('#account-trigger').evaluate(e=>document.activeElement===e));
  await page.locator('#account-trigger').click();await page.emulateMedia({reducedMotion:'reduce'});await page.waitForFunction(()=>document.querySelector('#account-dialog').style.transform==='');
  verify(name+': preference change removes compositor motion',await page.locator('#account-dialog').evaluate(e=>e.open&&e.style.opacity===''));
  await page.keyboard.press('Escape');verify(name+': reduced close immediate',await page.locator('#account-dialog').evaluate(e=>!e.open));
  await page.emulateMedia({reducedMotion:'no-preference'});await page.locator('#materials [data-open-evidence]').click();await page.waitForFunction(()=>document.querySelector('#inspector').style.transform==='');await page.keyboard.press('Escape');await page.waitForFunction(()=>document.activeElement?.matches('#materials [data-open-evidence]'));
  verify(name+': evidence spring completes and focus returns',await page.locator('#inspector').isHidden());
 }
 verify('no browser exceptions',!errors.length);verify('no external requests',!external.length);
 fs.writeFileSync(path.join(__dirname,'motion-results.json'),JSON.stringify({status:'PASS',browser:await browser.version(),checks,errors,external,scope:'Synthetic canonical and authenticated React fixtures; dispatched pointer interruption; no human AT acceptance'},null,2)+'\n');console.log('Motion browser PASS:',checks.length,'checks');await browser.close();
})().catch(e=>{console.error(e);fs.writeFileSync(path.join(__dirname,'motion-failure.json'),JSON.stringify({status:'FAIL',message:e.message,checks,errors,external},null,2));process.exit(1)});
