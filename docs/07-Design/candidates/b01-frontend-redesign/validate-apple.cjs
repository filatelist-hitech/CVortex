/* Behavioral regressions introduced by the Apple refinement; real browser assertions. */
const {chromium}=require(process.env.CVORTEX_PLAYWRIGHT_MODULE || 'playwright');
const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
const base='http://127.0.0.1:8769',out=path.join(__dirname,'validation');
const checks=[],captures=[],errors=[],external=[],fonts={};
const check=(name,value)=>{assert.ok(value,name);checks.push(name);};
(async()=>{
 const browser=await chromium.launch({headless:true,executablePath:process.env.CVORTEX_CHROMIUM_PATH});
 try{
  const context=await browser.newContext({locale:'ru-RU',timezoneId:'Europe/Moscow',reducedMotion:'no-preference'});
  await context.route('**/*',async route=>{if(new URL(route.request().url()).origin!==base){external.push(route.request().url());await route.abort();}else await route.continue();});
  const page=await context.newPage();page.on('pageerror',e=>errors.push(e.message));page.on('console',m=>{if(m.type()==='error')errors.push(m.text());});page.on('response',r=>{if(r.status()>=400)errors.push(`${r.status()} ${r.url()}`);});
  const load=async(width,height)=>{await page.setViewportSize({width,height});await page.goto(`${base}/?scenario=normal&opportunity=northstar&level=work#materials`);await page.waitForLoadState('networkidle');await page.evaluate(()=>document.fonts.ready);};
  const settled=async()=>page.waitForFunction(()=>!document.getElementById('account-dialog').style.willChange);
  const closed=async()=>page.waitForFunction(()=>!document.getElementById('account-dialog').open&&document.activeElement.id==='account-trigger'&&document.body.dataset.modalOpen==='false');
  const capture=async(name,fullPage=false)=>{if(fullPage)await page.evaluate(()=>scrollTo(0,0));await page.screenshot({path:path.join(out,`${name}.png`),fullPage});captures.push({file:`${name}.png`,viewport:page.viewportSize(),url:page.url().replace(base,'')});};
  for(const [w,h] of [[1440,900],[768,1024],[390,844],[320,640]]){
   await load(w,h);
   check(`${w}: canonical heading tracking derived as rem`,await page.locator('h1').evaluate(e=>getComputedStyle(e).letterSpacing==='-0.32px'));
   check(`${w}: consistent Russian reading font`,await page.locator('#proposal-copy').evaluate(e=>getComputedStyle(e).fontFamily==='Arial, sans-serif'));
   check(`${w}: all visible buttons have 44px touch height`,await page.locator('button').evaluateAll(items=>items.filter(e=>e.getClientRects().length).every(e=>e.getBoundingClientRect().height>=44)));
   const accept=page.locator('#accept');await accept.scrollIntoViewIfNeeded();const r=await accept.boundingBox();await page.mouse.move(r.x+r.width/2,r.y+r.height/2);await page.mouse.down();
   check(`${w}: pointer-down feedback precedes commit`,await accept.evaluate(e=>getComputedStyle(e).transform!=='none')&&await page.locator('#approve').isDisabled());await page.mouse.move(0,0);await page.mouse.up();
   check(`${w}: release outside does not accept`,await page.locator('#approve').isDisabled());
   await page.locator('#account-trigger').click();
   check(`${w}: modal locks background scroll immediately`,await page.locator('body').evaluate(e=>getComputedStyle(e).overflow==='hidden'));
   await settled();
   check(`${w}: menu visible inside viewport`,await page.locator('#account-dialog').evaluate(e=>{const r=e.getBoundingClientRect();return r.top>=0&&r.bottom<=innerHeight&&r.left>=0&&r.right<=innerWidth;}));
   if(w>=768)check(`${w}: menu anchored to account trigger`,await page.locator('#account-dialog').evaluate(e=>Math.abs(e.getBoundingClientRect().top-document.getElementById('account-trigger').getBoundingClientRect().bottom-8)<1));
   else check(`${w}: sheet anchored at lower edge`,await page.locator('#account-dialog').evaluate(e=>innerHeight-e.getBoundingClientRect().bottom===16));
   // Exit reversal retains its presentation value in the same browser task.
   const reversal=await page.evaluate(async()=>{
    const dialog=document.getElementById('account-dialog');document.getElementById('close-account').click();
    await new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve)));
    const before=dialog.style.transform;document.getElementById('demo-session').dispatchEvent(new PointerEvent('pointerdown',{bubbles:true}));
    const after=dialog.style.transform;return {before,after,open:dialog.open,closing:dialog.dataset.closing};
   });
   check(`${w}: closing motion redirects without presentation jump`,reversal.open&&!reversal.closing&&reversal.before===reversal.after&&reversal.before.includes('translate3d'));
   await settled();check(`${w}: redirected exit keeps focus inside dialog`,await page.locator('#account-dialog').evaluate(e=>e.contains(document.activeElement)));
   await page.locator('#demo-session').selectOption('admin');await capture(`apple-${w}-menu`);
   await page.locator('#demo-session').focus();await page.keyboard.press('Tab');check(`${w}: motion preserves forward focus trap`,await page.locator('#close-account').evaluate(e=>e===document.activeElement));
   await page.keyboard.press('Shift+Tab');check(`${w}: motion preserves reverse focus trap`,await page.locator('#demo-session').evaluate(e=>e===document.activeElement));
   await page.keyboard.press('Escape');await closed();check(`${w}: Escape restores focus and releases scroll`,await page.locator('body').evaluate(e=>getComputedStyle(e).overflow!=='hidden'));
   await page.locator('#account-trigger').click();await page.emulateMedia({reducedMotion:'reduce'});
   await page.waitForFunction(()=>!document.getElementById('account-dialog').style.transform&&!document.getElementById('account-dialog').style.willChange,{},{timeout:1000});
   check(`${w}: reducing motion in flight settles without transform`,await page.locator('#account-dialog').evaluate(e=>!e.style.transform&&!e.style.willChange));
   await page.keyboard.press('Escape');await closed();check(`${w}: reduced motion closes immediately`,!await page.locator('#account-dialog').isVisible());
   if(w<768){
    await page.locator('#accept').scrollIntoViewIfNeeded();
    check(`${w}: mobile wayfinding stays visible after deep scroll`,await page.locator('.compact-navigation').evaluate(e=>{const r=e.getBoundingClientRect();return r.top===0&&r.bottom<innerHeight;}));
    await page.locator('.global-menu > summary').click();check(`${w}: mobile navigation discoverable while reading`,await page.locator('.global-nav').isVisible());await page.keyboard.press('Escape');check(`${w}: Escape closes mobile menu and restores summary focus`,await page.locator('.global-menu').evaluate(e=>!e.open&&document.activeElement===e.querySelector('summary')));
   }
   await page.emulateMedia({reducedMotion:'no-preference'});
   await page.evaluate(()=>{document.activeElement.blur();scrollTo(0,0);});await capture(`apple-${w}-workspace`,true);
  }
  await load(1440,900);
  const client=await context.newCDPSession(page);await client.send('DOM.enable');await client.send('CSS.enable');const documentNode=await client.send('DOM.getDocument');
  for(const [name,selector] of Object.entries({russian:'#workspace-title',comparison:'#proposal-copy',brand:'.brand strong',salary:'#vacancy-salary span:first-child',salaryUnit:'#vacancy-salary .salary-unit',currency:'#vacancy-salary .salary-currency',company:'#company-name'})){
   const {nodeId}=await client.send('DOM.querySelector',{nodeId:documentNode.root.nodeId,selector});fonts[name]=await client.send('CSS.getPlatformFontsForNode',{nodeId});
  }
  check('actual Cyrillic rendering is Arial',fonts.russian.fonts.some(f=>f.familyName==='Arial'&&!f.isCustomFont));
  check('mixed Latin/Cyrillic comparison stays one Arial family',fonts.comparison.fonts.every(f=>f.familyName==='Arial'&&!f.isCustomFont));
  check('actual brand remains local Space Grotesk',fonts.brand.fonts.some(f=>f.familyName.startsWith('Space Grotesk')&&f.isCustomFont));
  check('actual salary retains Space Grotesk digits',fonts.salary.fonts.some(f=>f.familyName.startsWith('Space Grotesk')&&f.isCustomFont));
  check('company context uses the same working Arial family',fonts.company.fonts.every(f=>f.familyName==='Arial'&&!f.isCustomFont));
  check('ruble currency uses the permitted Space Grotesk glyph, not a math fallback',fonts.currency.fonts.length===1&&fonts.currency.fonts[0].familyName.startsWith('Space Grotesk')&&fonts.currency.fonts[0].isCustomFont);
  check('Russian salary unit remains Arial',fonts.salaryUnit.fonts.length===1&&fonts.salaryUnit.fonts[0].familyName==='Arial'&&!fonts.salaryUnit.fonts[0].isCustomFont);
  await client.send('Emulation.setEmulatedMedia',{features:[{name:'prefers-contrast',value:'more'},{name:'prefers-reduced-transparency',value:'reduce'}]});
  check('increased contrast and reduced transparency recognized',await page.evaluate(()=>matchMedia('(prefers-contrast: more)').matches&&matchMedia('(prefers-reduced-transparency: reduce)').matches));
  await page.locator('#account-trigger').click();await settled();
  check('reduced transparency stays opaque with no backdrop blur',await page.locator('#account-dialog').evaluate(e=>getComputedStyle(e).backgroundColor==='rgb(11, 18, 36)'&&getComputedStyle(e).backdropFilter==='none'));
  await page.keyboard.press('Escape');await closed();
  await page.locator('#accept').focus();check('increased contrast focus ring',await page.locator('#accept').evaluate(e=>getComputedStyle(e).outlineWidth==='3px'));
  await capture('apple-high-contrast',true);await client.send('Emulation.setEmulatedMedia',{features:[]});
  await page.locator('#account-trigger').click();await settled();await page.mouse.click(0,0);await closed();check('backdrop dismissal restores focus',await page.locator('#account-trigger').evaluate(e=>e===document.activeElement));
  await page.locator('#account-trigger').click();await settled();await page.locator('#profile-action').click();await page.keyboard.press('Escape');await closed();await page.locator('#account-trigger').click();await settled();check('account feedback resets on next opening',await page.locator('#account-feedback').textContent()==='Выберите пункт меню или посмотрите другой режим доступа.');await page.keyboard.press('Escape');await closed();
  await page.locator('#accept').click();check('acceptance marks pending approval, not confirmed content',await page.locator('#review-card').getAttribute('data-review-state')==='accepted'&&!await page.locator('#approve').isDisabled());
  await page.locator('#approve').click();check('approved style follows explicit approval only',await page.locator('#review-card').getAttribute('data-review-state')==='approved'&&(await page.locator('#action-status').textContent()).includes('Отклик не отправлен'));
  await capture('apple-explicit-approval',true);
  check('no browser errors',!errors.length);check('no external requests',!external.length);
  fs.writeFileSync(path.join(out,'font-results.json'),JSON.stringify({checkedAt:new Date().toISOString(),method:'CDP CSS.getPlatformFontsForNode; actual glyph providers',fonts},null,2)+'\n');
  fs.writeFileSync(path.join(out,'apple-results.json'),JSON.stringify({status:'PASS',checkedAt:new Date().toISOString(),browser:browser.version(),checks,captures,errors,external,limitations:['Native 200% zoom attempted: CUA Chrome browser unavailable; native Chrome app capture failed with ScreenCaptureKit -3811','Manual screen reader pending','Named human review pending']},null,2)+'\n');
  console.log(`Apple refinement: PASS; ${checks.length} checks; ${captures.length} captures`);
 }catch(e){fs.writeFileSync(path.join(out,'apple-results.json'),JSON.stringify({status:'FAIL',failure:e.message,checks,errors,external},null,2)+'\n');throw e;}finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
