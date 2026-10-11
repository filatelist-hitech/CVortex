/* Bounded research validation; no application API, auth, production data or external calls. */
const { chromium } = require(process.env.CVORTEX_PLAYWRIGHT_MODULE || 'playwright');
const fs = require('node:fs');
const path = require('node:path');
const { pathToFileURL } = require('node:url');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '..');
const checks = [], errors = [], externalRequests = [], views = [], contrast = [];
function check(name, condition) { assert.ok(condition, name); checks.push(name); }
const url = c => process.env.CVORTEX_PREVIEW_URL
  ? `${process.env.CVORTEX_PREVIEW_URL}/concept-${c}/index.html`
  : pathToFileURL(path.join(root, `concept-${c}`, 'index.html')).href;
async function load(page, c) {
  await page.goto(url(c)); await page.waitForLoadState('networkidle');
  await page.evaluate(() => document.fonts.ready);
}
async function noOverflow(page, name) {
  const size = await page.evaluate(() => ({width:innerWidth,scroll:document.documentElement.scrollWidth}));
  check(`${name}: no horizontal page overflow`, size.scroll <= size.width + 1);
  return size;
}
async function contrastAudit(page, c) {
  const findings = await page.evaluate(() => {
    function rgb(value){const n=value.match(/[\d.]+/g);return n?n.map(Number):[0,0,0,0];}
    function luminance(v){return v.slice(0,3).map(n=>{n/=255;return n<=.04045?n/12.92:((n+.055)/1.055)**2.4;}).reduce((s,n,i)=>s+n*[.2126,.7152,.0722][i],0);}
    function background(el){while(el){const c=rgb(getComputedStyle(el).backgroundColor);if(c.length===3||c[3]===1)return c;el=el.parentElement;}return [7,11,24];}
    const walker=document.createTreeWalker(document.body,NodeFilter.SHOW_TEXT),rows=[];
    while(walker.nextNode()){
      const n=walker.currentNode,el=n.parentElement;
      if(!n.textContent.trim()||!el||el.closest('script,style,[hidden],button:disabled,option'))continue;
      const range=document.createRange();range.selectNodeContents(n);if(!range.getBoundingClientRect().width)continue;
      const s=getComputedStyle(el),fg=rgb(s.color),bg=background(el),a=luminance(fg),b=luminance(bg);
      const ratio=(Math.max(a,b)+.05)/(Math.min(a,b)+.05),size=parseFloat(s.fontSize),weight=parseInt(s.fontWeight)||400;
      const required=size>=24||(size>=18.67&&weight>=700)?3:4.5;
      rows.push({text:n.textContent.trim().slice(0,75),ratio:Math.round(ratio*100)/100,required,color:s.color,size});
    }
    return {minimum:Math.min(...rows.map(x=>x.ratio)),count:rows.length,failures:rows.filter(x=>x.ratio+.01<x.required)};
  });
  contrast.push({concept:c,...findings});check(`${c}: rendered text contrast`, findings.failures.length===0);
}
async function sharedTests(page,c){
  await page.getByRole('button',{name:/Find context/}).focus();
  check(`${c}: visible keyboard focus ring`,await page.getByRole('button',{name:/Find context/}).evaluate(el=>getComputedStyle(el).outlineStyle!=='none'));
  await page.keyboard.press('Control+k');check(`${c}: keyboard command dialog`,await page.locator('#context-dialog').isVisible());
  await page.locator('#command-query').fill('not-a-company');check(`${c}: command empty state`,await page.locator('#command-results').textContent().then(t=>t.includes('No matching')));
  await page.keyboard.press('Escape');check(`${c}: Escape closes modal`,!(await page.locator('#context-dialog').isVisible()));
  check(`${c}: modal restores trigger focus`,await page.locator('#find-context').evaluate(el=>document.activeElement===el));
  for(const mode of ['loading','empty','error']){
    await page.locator('#view-state').selectOption(mode);
    check(`${c}: ${mode} replaces normal content`,!(await page.locator('#prototype-content').isVisible())&&await page.locator('#state-screen').isVisible());
    check(`${c}: ${mode} has disabled approval`,await page.getByRole('button',{name:'Approve content unavailable'}).isDisabled());
    await page.locator('#state-recover').click();check(`${c}: ${mode} recovery`,await page.locator('#prototype-content').isVisible());
  }
}
async function interactions(page,c){
  if(c==='a'){
    check('A: decision queue default',await page.locator('#pipeline-rows tr').count()===3);
    await page.getByRole('button',{name:'Interviews',exact:true}).click();check('A: interview filter',await page.locator('#pipeline-rows tr').count()===2);
    await page.locator('[data-app="APP-02"]').click();check('A: selected opportunity updates inspector',(await page.locator('#inspector-title').textContent())==='Orbital Works');
    await page.getByRole('button',{name:'All active',exact:true}).click();await page.locator('[data-app="APP-06"]').click();await page.locator('#selected-evidence').click();check('A: unanalyzed record has no selected evidence',(await page.locator('#dialog-content').textContent()).includes('not analyzed'));await page.keyboard.press('Escape');
    await page.locator('#interview-priority').click();check('A: interview brief has exact date',(await page.locator('#dialog-content').textContent()).includes('8 Oct 2026'));await page.keyboard.press('Escape');
  }
  if(c==='b'){
    check('B: approval disabled before acceptance',await page.locator('#approve-content').isDisabled());
    await page.locator('#edit-change').click();await page.locator('#review-edit').fill('Led a QA platform migration and doubled throughput.');await page.locator('#save-edit').click();check('B: unsupported edit blocks acceptance',(await page.locator('#review-result').textContent()).includes('BLOCK')&&await page.locator('#accept-change').isDisabled());
    await page.locator('#review-edit').fill('Built API regression tests with Python and pytest.');await page.locator('#save-edit').click();check('B: supported exact fixture edit stays draft',(await page.locator('#review-badge').textContent())==='DRAFT');
    await page.locator('#accept-change').click();check('B: accept is separate from approval',(await page.locator('#review-badge').textContent())==='ACCEPTED'&&!(await page.locator('#approve-content').isDisabled()));
    await page.locator('#approve-content').click();check('B: explicit content approval without submission',(await page.locator('#approval-help').textContent()).includes('not sent'));
    await page.locator('[data-section="history"]').click();check('B: section switch preserves selected company',(await page.locator('#workspace-company').textContent())==='Northstar Labs');
    await page.locator('#app-search').fill('nobody');check('B: list empty state',await page.locator('#list-empty').isVisible());await page.locator('#app-search').fill('');
    await load(page,c);await page.locator('#reject-change').click();check('B: rejection does not enable approval',(await page.locator('#review-badge').textContent())==='REJECTED'&&await page.locator('#approve-content').isDisabled());
  }
  if(c==='c'){
    await page.locator('[data-skill="database"]').click();check('C: capability changes evidence and connections',(await page.locator('#story-fact').textContent()).includes('F-18')&&(await page.locator('#market-roles').textContent()).includes('Kite Studio'));
    await page.locator('[data-track="lead"]').click();check('C: unconfirmed leadership is not usable',await page.locator('#story-reuse').isDisabled()&&(await page.locator('#market-roles').textContent()).includes('BLOCK'));
    await page.locator('#leave-pending').click();check('C: leave pending retains status',(await page.locator('#pending-state').textContent())==='PENDING');
    await page.locator('#review-fact').click();await page.locator('#confirm-demo-fact').click();check('C: confirmation requires explicit action',(await page.locator('#pending-state').textContent())==='CONFIRMED');
    check('C: confirmation does not create content claim',(await page.locator('#story-claim').textContent())==='Claim not created'&&await page.locator('#story-reuse').isDisabled());
    await load(page,c);await page.locator('#review-fact').click();await page.locator('#reject-demo-fact').click();check('C: rejected source has no generated usage',(await page.locator('#pending-state').textContent())==='REJECTED');
  }
  if(c==='d'){
    check('D: incomplete preparation cannot continue',await page.locator('#stage-next').isDisabled());
    await page.locator('#stage-primary').click();await page.locator('#reject-stage').click();check('D: rejecting proposal leaves gate closed',await page.locator('#stage-next').isDisabled());
    await page.locator('#stage-primary').click();await page.locator('#approve-stage').click();check('D: exact approval opens readiness gate',!(await page.locator('#stage-next').isDisabled()));
    await page.locator('#stage-next').click();check('D: Apply still requires manual action',await page.locator('#stage-next').isDisabled());
    await page.locator('#stage-primary').click();check('D: manual record requires confirmation',await page.locator('#record-applied').isDisabled());
    await page.locator('#manual-checkbox').check();await page.locator('#record-applied').click();check('D: explicit manual record opens next stage',!(await page.locator('#stage-next').isDisabled()));
    await page.locator('#stage-next').click();check('D: employer conflict blocks communication',await page.locator('#stage-next').isDisabled()&&(await page.locator('#stage-evidence').textContent()).includes('USER_RESOLUTION_REQUIRED'));
  }
}
(async()=>{
  const browser=await chromium.launch({headless:true,...(process.env.CVORTEX_CHROMIUM_PATH?{executablePath:process.env.CVORTEX_CHROMIUM_PATH}:{})});
  try{
    for(const c of 'abcd'){
      const page=await browser.newPage({viewport:{width:1440,height:1000},reducedMotion:'reduce'});
      page.on('pageerror',e=>errors.push({concept:c,message:e.message}));
      page.on('request',r=>{if(/^https?:/.test(r.url())&&!r.url().startsWith(process.env.CVORTEX_PREVIEW_URL||'http://127.0.0.1:8767'))externalRequests.push(r.url());});
      await load(page,c);check(`${c}: local brand font loaded`,await page.evaluate(()=>document.fonts.check('16px "Space Grotesk"')));
      views.push({concept:c,viewport:'1440x1000',...await noOverflow(page,c+' desktop')});await contrastAudit(page,c);
      await page.screenshot({path:path.join(root,`concept-${c}`,`concept-${c}-preview.png`),fullPage:true});
      await page.screenshot({path:path.join(root,`concept-${c}`,`concept-${c}-viewport.png`)});
      await sharedTests(page,c);await interactions(page,c);
      await load(page,c);await page.locator('#find-context').click();await page.locator('#command-query').fill('Northstar');check(`${c}: context search has result`,await page.locator('#command-results button').count()===1);await page.keyboard.press('Escape');
      for(const width of [820,720,390,320]){
        await page.setViewportSize({width,height:844});await noOverflow(page,c+' reflow '+width);
        if(width===390){
          await page.screenshot({path:path.join(root,`concept-${c}`,`concept-${c}-mobile.png`),fullPage:true});
          await page.screenshot({path:path.join(root,`concept-${c}`,`concept-${c}-mobile-viewport.png`)});
          if(c==='b'){await page.locator('#mobile-app-select').selectOption('APP-03');check('B: mobile chooses opportunity',(await page.locator('#workspace-company').textContent())==='Cedar Systems');await page.locator('#workspace-memory').click();check('B: mobile employer contradiction visible',(await page.locator('#dialog-content').textContent()).includes('USER_RESOLUTION_REQUIRED'));await page.keyboard.press('Escape');await page.locator('#mobile-app-select').selectOption('APP-01');}
          if(c==='d'){await page.locator('#mobile-stage-select').selectOption('Interview');check('D: mobile selects a task stage',(await page.locator('#task-title').textContent()).includes('specific story'));await page.locator('#mobile-stage-select').selectOption('Prepare');}
          const undersized=await page.evaluate(()=>[...document.querySelectorAll('button,select,input,summary,.concept-links a')].filter(e=>{const r=e.getBoundingClientRect();return r.width&&r.height&&!e.disabled&&(r.width<43.9||r.height<43.9);}).map(e=>({text:e.textContent.trim().slice(0,60),width:e.getBoundingClientRect().width,height:e.getBoundingClientRect().height})));
          check(`${c}: mobile controls at least 44px`,undersized.length===0);
        }
      }
      check(`${c}: no nonessential motion under reduced motion`,await page.evaluate(()=>[...document.querySelectorAll('*')].every(el=>getComputedStyle(el).animationName==='none')));
      await page.close();
    }
    check('All concepts: no browser JavaScript errors',errors.length===0);check('All concepts: no external network requests',externalRequests.length===0);
    const report={date:'2026-10-07',status:'PASS',browser:await browser.version(),method:'Playwright Chromium; screenshots visually inspected separately',checks,views,contrast,errors,externalRequests,limitations:['Synthetic prototype tests, not production validation','No assistive-technology session or real-user usability test','720px reflow is not a measured browser 200% zoom test','Only exact fixture edits validated; no live AI','Desktop PNGs are full-page captures from 1440x1000 viewport']};
    fs.writeFileSync(path.join(__dirname,'browser-results.json'),JSON.stringify(report,null,2)+'\n');console.log(`PASS: ${checks.length} checks; 4 desktop + 4 mobile previews, text contrast, reflow, interactions, no JS errors.`);
  }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
