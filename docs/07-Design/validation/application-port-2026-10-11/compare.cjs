// Reproducible synthetic-only browser/pixel evidence; thresholds fixed in task before implementation.
const fs = require('node:fs');
const path = require('node:path');
const {createRequire} = require('node:module');
const frontendRequire = createRequire(path.resolve(__dirname, '../../../../apps/frontend/package.json'));
const runtime = process.env.CVORTEX_NODE_MODULES;
const loadRuntimeModule = name => runtime ? require(path.join(runtime, name)) : frontendRequire(name);
const {chromium} = loadRuntimeModule('playwright');
const {PNG} = loadRuntimeModule('pngjs');
const output = __dirname;
const thresholds = Object.freeze({channel:16,coreRatio:0.005,fullRatio:0.02,geometry:1});
const selectors = ['.site-header','.global-menu','.opportunity-list','.workspace','.vacancy-summary','.salary-card','#vacancy-salary','.section-nav','.review-card','.review-body','.diff','.reason-grid','.actions','.approval','#accept','#approve','.inspector'];
const props = ['fontFamily','fontSize','fontWeight','lineHeight','letterSpacing','color','backgroundColor','padding','gap','borderRadius'];
const scenarios = ['normal','loading','empty','failed','stale','blocked','pending','approval'];
const cases = [
 ...scenarios.map(scenario => ({name:scenario,scenario})),
 ...['overview','requirements','employer','history'].map(section=>({name:section,section})),
 {name:'cedar',opportunity:'cedar'},
 {name:'accepted',action:async p=>p.locator('#accept').click()},
 {name:'approved',action:async p=>{await p.locator('#accept').click();await p.locator('#approve').click();}},
 {name:'rejected',action:async p=>p.locator('#reject').click()},
 {name:'editing',action:async p=>p.locator('#edit').click()},
 {name:'evidence',action:async p=>p.locator('#materials [data-open-evidence]').click()},
 {name:'pending-evidence',scenario:'pending',action:async p=>p.locator('#materials [data-open-evidence]').click()},
 {name:'cedar-evidence',opportunity:'cedar',action:async p=>p.locator('#materials [data-open-evidence]').click()},
 {name:'list',level:'list'},
 {name:'missing-salary',salary:'missing'},
 {name:'navigation',action:async(p,width)=>{if(width<768)await p.locator('.global-menu > summary').click();}},
 {name:'account',intentionalAccount:true,action:async p=>p.locator('#account-trigger').click()},
 {name:'tablet-list',tabletOnly:true,action:async p=>p.locator('#open-list').click()}
];
function diff(a,b,bounds,excluded) {
 const x0=bounds?Math.round(bounds.x):0,y0=bounds?Math.round(bounds.y):0;
 const width=bounds?Math.max(1,Math.min(Math.round(bounds.width),a.width-x0,b.width-x0)):Math.max(a.width,b.width),height=bounds?Math.max(1,Math.min(Math.round(bounds.height),a.height-y0,b.height-y0)):Math.max(a.height,b.height);
 const png=new PNG({width,height});let changed=0,total=0;
 for(let y=0;y<height;y++)for(let x=0;x<width;x++){
   const ax=x+x0,ay=y+y0,ia=(ay*a.width+ax)*4,ib=(ay*b.width+ax)*4,i=(y*width+x)*4;
   const outside=ax>=a.width||ax>=b.width||ay>=a.height||ay>=b.height;
   const mismatch=outside||[0,1,2].some(c=>Math.abs(a.data[ia+c]-b.data[ib+c])>thresholds.channel);
   const omitted=excluded&&ax>=excluded.x&&ax<excluded.x+excluded.width&&ay>=excluded.y&&ay<excluded.y+excluded.height;
   if(!omitted)total++;
   if(mismatch){if(!omitted)changed++;png.data[i]=255;png.data[i+1]=40;png.data[i+2]=100;}
   else {const grey=Math.round((a.data[ia]+a.data[ia+1]+a.data[ia+2])/3);png.data[i]=png.data[i+1]=png.data[i+2]=grey;}
   png.data[i+3]=255;
 }
 return {changed,total,ratio:total?changed/total:0,png};
}
(async()=>{
 const browser=await chromium.launch({headless:true,...(process.env.CVORTEX_CHROMIUM_PATH ? {executablePath:process.env.CVORTEX_CHROMIUM_PATH} : {})});
 const context=await browser.newContext({locale:'ru-RU',timezoneId:'Europe/Moscow',reducedMotion:'reduce',deviceScaleFactor:1});
 const errors=[],requests=[],external=[];
 await context.route('**/*',async route=>{const u=new URL(route.request().url());if(!['http://127.0.0.1:8080','http://127.0.0.1:8768'].includes(u.origin)){external.push(u.origin);return route.abort();}if(u.pathname.startsWith('/api/')){requests.push(u.pathname);if(u.pathname!='/api/v1/me'){errors.push('Unexpected demo API: '+u.pathname);return route.abort();}return route.fulfill({json:{data:{id:'synthetic-user',email:'synthetic@example.test',role:'user',status:'ACTIVE'}}});}return route.continue();});
 const pages=[await context.newPage(),await context.newPage()];pages.forEach(p=>p.on('pageerror',e=>errors.push(e.message)));
 const results=[];
 for(const [width,height] of [[1440,900],[768,1024],[390,844],[320,640]]){
  for(const c of cases){if(c.tabletOnly&&width!==768)continue;let captures=[];
   for(let i=0;i<2;i++){
    const p=pages[i],name=i?'production':'canonical';await p.setViewportSize({width,height});
    const q=new URLSearchParams({capture:c.name,opportunity:c.opportunity||'northstar',scenario:c.scenario||'normal',level:c.level||'work'});if(i)q.set('demo','b01');if(c.salary)q.set('salary',c.salary);
    await p.goto(`http://127.0.0.1:${i?8080:8768}/?${q}#${c.section||'materials'}`);await p.waitForLoadState('networkidle');if(i)await p.locator('.application-shell[data-demo="true"]').waitFor();await p.evaluate(()=>document.fonts.ready);if(c.action)await c.action(p,width);
    // Same hover/focus/scroll conditions. React queues focus in rAF; settle it
    // before restoring scroll, rather than racing focus against capture.
    await p.mouse.move(0,0);
    await p.evaluate(()=>new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve))));
    await p.evaluate(()=>window.scrollTo(0,0));
    await p.evaluate(()=>new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve))));
    const metrics=await p.evaluate(({selectors,props})=>{
      const account=document.querySelector('#account-dialog');
      const ar=account?.open?account.getBoundingClientRect():null;
      const accountMetrics=ar?{rect:{x:ar.x,y:ar.y,width:ar.width,height:ar.height},styles:Object.fromEntries(props.map(prop=>[prop,getComputedStyle(account)[prop]]))}:null;
      const layout=document.querySelector('.layout').getBoundingClientRect();
      return {account:accountMetrics,overflow:document.documentElement.scrollWidth>innerWidth,layout:{x:layout.x,y:layout.y,width:layout.width,height:layout.height},elements:selectors.map(selector=>{const el=document.querySelector(selector);if(!el||!el.getClientRects().length)return {selector,visible:false};const r=el.getBoundingClientRect(),css=getComputedStyle(el);return {selector,visible:true,rect:{x:r.x,y:r.y,width:r.width,height:r.height},styles:Object.fromEntries(props.map(prop=>[prop,css[prop]]))};})};
    },{selectors,props});
    const file=`${width}-${c.name}-${name}.png`;
    // Capture the actual viewport. Full-page capture synthesizes a taller viewport
    // during rasterization and invokes canonical resize handlers, changing state.
    const bytes=await p.screenshot({path:path.join(output,file),fullPage:false});
    const tiles=[];
    // Cover below-the-fold content in real viewport-sized scroll captures, without
    // resizing, stitching, hiding, or masking any content. Modal backgrounds are
    // covered by their matching normal-state captures; only their viewport is usable.
    const modal=await p.evaluate(()=>Boolean(document.querySelector('dialog[open], [aria-modal=true]')));
    if(!modal){
     const extent=await p.evaluate(()=>document.documentElement.scrollHeight);
     for(let requested=height;requested<extent;requested+=height){
      await p.evaluate(y=>window.scrollTo(0,y),requested);
      await p.evaluate(()=>new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve))));
      const scrollY=await p.evaluate(()=>scrollY);
      const tileFile=`${width}-${c.name}-${name}-scroll-${requested}.png`;
      const tileBytes=await p.screenshot({path:path.join(output,tileFile),fullPage:false});
      tiles.push({file:tileFile,scrollY,png:PNG.sync.read(tileBytes)});
     }
    }
    captures.push({name,file,metrics,png:PNG.sync.read(bytes),tiles});
   }
   const [a,b]=captures,full=diff(a.png,b.png),core=diff(a.png,b.png,a.metrics.layout);
   fs.writeFileSync(path.join(output,`${width}-${c.name}-difference.png`),PNG.sync.write(full.png));
   // Real authentication content differs from demo role controls. Compare the entire
   // remaining page; preserve raw captures/diff and raw ratios, without screenshot masks.
   // 56px bounds the canonical overlay shadow (16px offset + 40px blur).
   let exclusion=null,accountDifferences=[];
   if(c.intentionalAccount){
    const x=a.metrics.account,y=b.metrics.account;
    if(!x||!y)accountDifferences.push('missing account dialog');
    else {
     for(const k of (width<768?['x','width']:['x','y','width']))if(Math.abs(x.rect[k]-y.rect[k])>thresholds.geometry)accountDifferences.push(k);
     if(width<768&&Math.abs((x.rect.y+x.rect.height)-(y.rect.y+y.rect.height))>thresholds.geometry)accountDifferences.push('bottom anchor');
     for(const k of props)if(x.styles[k]!==y.styles[k])accountDifferences.push(k);
     const left=Math.max(0,Math.floor(Math.min(x.rect.x,y.rect.x)-56)),top=Math.max(0,Math.floor(Math.min(x.rect.y,y.rect.y)-56));
     const right=Math.min(a.png.width,Math.ceil(Math.max(x.rect.x+x.rect.width,y.rect.x+y.rect.width)+56));
     const bottom=Math.min(a.png.height,Math.ceil(Math.max(x.rect.y+x.rect.height,y.rect.y+y.rect.height)+56));
     exclusion={x:left,y:top,width:right-left,height:bottom-top,reason:'Real identity/logout versus demo role switch, including canonical shadow; dialog height is content-dependent'};
    }
   }
   const comparableFull=exclusion?diff(a.png,b.png,null,exclusion):full,comparableCore=exclusion?diff(a.png,b.png,a.metrics.layout,exclusion):core;
   const geometry=[],styles=[];
   for(let j=0;j<a.metrics.elements.length;j++){const x=a.metrics.elements[j],y=b.metrics.elements[j];if(x.visible!==y.visible)geometry.push({selector:x.selector,visibility:[x.visible,y.visible]});if(!x.visible||!y.visible)continue;for(const k of ['x','y','width','height'])if(Math.abs(x.rect[k]-y.rect[k])>thresholds.geometry)geometry.push({selector:x.selector,property:k,canonical:x.rect[k],production:y.rect[k]});for(const k of props)if(x.styles[k]!==y.styles[k])styles.push({selector:x.selector,property:k,canonical:x.styles[k],production:y.styles[k]});}
   const scrollComparisons=[];
   if(a.tiles.length!==b.tiles.length)geometry.push({selector:'document',property:'scroll capture count',canonical:a.tiles.length,production:b.tiles.length});
   for(let tile=0;tile<Math.min(a.tiles.length,b.tiles.length);tile++){
    const x=a.tiles[tile],y=b.tiles[tile],f=diff(x.png,y.png);
    const top=Math.max(0,a.metrics.layout.y-x.scrollY),bottom=Math.min(height,a.metrics.layout.y+a.metrics.layout.height-x.scrollY);
    const bounds=bottom>top?{x:a.metrics.layout.x,y:top,width:a.metrics.layout.width,height:bottom-top}:null;
    const coreTile=bounds?diff(x.png,y.png,bounds):null;
    const difference=`${width}-${c.name}-difference-scroll-${tile+1}.png`;fs.writeFileSync(path.join(output,difference),PNG.sync.write(f.png));
    scrollComparisons.push({canonical:x.file,production:y.file,difference,scrollY:[x.scrollY,y.scrollY],fullRatio:f.ratio,coreRatio:coreTile?.ratio??null,pass:Math.abs(x.scrollY-y.scrollY)<=thresholds.geometry&&f.ratio<=thresholds.fullRatio&&(!coreTile||coreTile.ratio<=thresholds.coreRatio)});
   }
   const pass=scrollComparisons.every(t=>t.pass)&&!geometry.length&&!styles.length&&!accountDifferences.length&&!a.metrics.overflow&&!b.metrics.overflow&&comparableCore.ratio<=thresholds.coreRatio&&comparableFull.ratio<=thresholds.fullRatio;
   results.push({width,height,state:c.name,pass,intentionalAccount:!!c.intentionalAccount,images:{canonical:a.file,production:b.file,difference:`${width}-${c.name}-difference.png`},full:{ratio:full.ratio,changed:full.changed,total:full.total},core:{ratio:core.ratio,changed:core.changed,total:core.total},geometry,styles,scrollComparisons,accountDifferences,exclusion,comparableFull:{ratio:comparableFull.ratio,changed:comparableFull.changed,total:comparableFull.total},comparableCore:{ratio:comparableCore.ratio,changed:comparableCore.changed,total:comparableCore.total},metrics:{canonical:a.metrics,production:b.metrics}});
   console.log(width,c.name,pass?'PASS':'FAIL','full',full.ratio.toFixed(5),'core',core.ratio.toFixed(5),'geometry',geometry.length,'styles',styles.length);
  }
 }
 const report={capturedAt:new Date().toISOString(),browser:await browser.version(),os:process.platform+' '+process.arch,viewportDPR:1,zoom:'100%',locale:'ru-RU',timezone:'Europe/Moscow',motion:'reduce',screenshotScope:'actual viewport plus complete scroll tiles without resize or stitching; each tile independently gated; modal backgrounds covered in normal states',thresholds,masks:[],errors,external,requests,results,pass:results.every(r=>r.pass)&&!errors.length&&!external.length};
 fs.writeFileSync(path.join(output,'comparison-report.json'),JSON.stringify(report,null,2)+'\n');await browser.close();if(!report.pass)process.exitCode=1;
})().catch(e=>{console.error(e);process.exit(1)});
