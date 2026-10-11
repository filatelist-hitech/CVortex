// Exercises the existing Career workflows with ephemeral synthetic API fixtures only.
const { chromium } = require(process.env.CVORTEX_PLAYWRIGHT_MODULE || 'playwright');
const fs = require('node:fs'), path = require('node:path'), assert = require('node:assert/strict');
const checks=[], requests=[], errors=[], captures=[];
const verify=(name,ok)=>{assert.ok(ok,name);checks.push(name);};
(async()=>{
 const browser=await chromium.launch({headless:true,executablePath:process.env.CVORTEX_CHROMIUM_PATH});
 const context=await browser.newContext({locale:'ru-RU',timezoneId:'Europe/Moscow',reducedMotion:'reduce'});
 const facts=[], claims=[]; let failExtraction=false, failLogout=false, role='user';
 await context.route('**/*',async route=>{
  const req=route.request(),url=new URL(req.url());
  assert.equal(url.origin,'http://127.0.0.1:8080');
  if(!url.pathname.startsWith('/api/'))return route.continue();
  const endpoint=url.pathname;requests.push({path:endpoint,method:req.method()});let body={},status=200;
  if(endpoint==='/api/v1/me')body={data:{id:'synthetic',email:'synthetic@example.test',role,status:'ACTIVE'}};
  else if(endpoint==='/api/v1/career')body={data:{facts,claims,sources:[]}};
  else if(endpoint==='/api/v1/vacancies')body={data:[]};
  else if(endpoint==='/api/v1/career/facts/manual'){
   const values=req.postDataJSON();verify('manual remains POST',req.method()==='POST');verify('manual payload unchanged',values.fact_type==='skill'&&values.assertion==='Синтетический ручной факт');
   facts.push({id:'manual',fact_type:'skill',assertion_original:values.assertion,assertion_approved:values.assertion,source_excerpt:values.assertion,provenance_type:'user_manual',status:'CONFIRMED'});body={data:facts.at(-1)};
  }else if(endpoint==='/api/v1/career/extractions'){
   verify('extraction remains POST with source_text',req.method()==='POST'&&req.postDataJSON().source_text==='Синтетический источник');
   if(failExtraction){status=503;body={message:'SQLSTATE PRIVATE SECRET',error:{code:'PROVIDER_ERROR',retryable:false}};}
   else {for(const id of ['confirm','edit','reject'])facts.push({id,fact_type:'skill',assertion_original:'Синтетический факт '+id,assertion_approved:null,source_excerpt:'<img src=x onerror="window.fixtureXss=true">',provenance_type:'paste_extraction',status:'PENDING'});body={data:{id:'source'}};}
  }else if(/\/career\/facts\/[^/]+\/review$/.test(endpoint)){
   const id=endpoint.split('/').at(-2),payload=req.postDataJSON(),fact=facts.find(f=>f.id===id);
   verify(id+': review remains PATCH',req.method()==='PATCH');
   verify(id+': review payload unchanged',payload.action===({confirm:'confirm',edit:'edit_confirm',reject:'reject'})[id]);
   fact.status=payload.action==='reject'?'REJECTED':'CONFIRMED';fact.assertion_approved=payload.assertion??fact.assertion_original;
   if(payload.action==='edit_confirm')verify('edited assertion preserved',payload.assertion==='Синтетическая подтверждённая правка');
   body={data:fact};
  }else if(endpoint==='/api/v1/auth/logout'){verify('failed logout remains POST',req.method()==='POST');assert.ok(failLogout);status=503;body={error:{code:'INTERNAL_ERROR'}};}
  else if(endpoint==='/api/v1/diagnostics/incidents')body={data:{data:[],current_page:1,last_page:1,total:0}};
  else throw new Error('Unexpected API '+endpoint);
  await route.fulfill({status,contentType:'application/json',body:JSON.stringify(body)});
 });
 const page=await context.newPage();page.setDefaultTimeout(10000);
 page.on('pageerror',e=>errors.push(e.message));
 const capture=async name=>{await page.evaluate(()=>window.scrollTo(0,0));await page.screenshot({path:path.join(__dirname,name+'.png'),fullPage:!name.endsWith('-profile')});captures.push(name+'.png');};
 for(const width of [1440,768,390,320]){
  await page.setViewportSize({width,height:900});await page.goto('http://127.0.0.1:8080/');await page.waitForLoadState('networkidle');
  const profile=page.getByRole('button',{name:/Профиль/});await profile.focus();await page.keyboard.press('Enter');
  verify(width+': native modal opens',await page.locator('dialog').evaluate(el=>el.matches(':modal')));
  await capture(width+'-profile');
  await page.keyboard.press('Escape');verify(width+': profile Escape closes',await page.locator('dialog').evaluate(el=>!el.open));verify(width+': profile focus returns',await profile.evaluate(el=>document.activeElement===el));
  await profile.click();await page.getByRole('dialog').getByRole('button',{name:'Закрыть',exact:true}).click();verify(width+': profile close returns focus',await profile.evaluate(el=>document.activeElement===el));
 }
 await page.setViewportSize({width:390,height:844});
 await page.getByRole('button',{name:'Добавить подтверждённый факт'}).click();verify('required manual fields prevent requests',!requests.some(r=>r.path.endsWith('/manual')));
 await page.getByLabel('Тип факта',{exact:true}).fill('skill');await page.getByLabel('Подтверждённое утверждение').fill('Синтетический ручной факт');
 await page.getByRole('button',{name:'Добавить подтверждённый факт'}).click();await page.locator('.confirmed-card .assertion').getByText('Синтетический ручной факт',{exact:true}).waitFor();verify('manual result rendered as confirmed',true);
 await page.getByLabel('Текст об опыте или резюме').fill('Синтетический источник');await page.getByRole('button',{name:'Извлечь факты для проверки'}).click();await page.locator('.pending-card').first().waitFor();
 verify('extraction creates pending candidates',await page.locator('.pending-card').count()===3);verify('untrusted evidence inert',await page.locator('.pending-card blockquote img').count()===0&&await page.evaluate(()=>window.fixtureXss===undefined));
 await capture('390-pending');
 const card=id=>page.locator('.pending-card').filter({has:page.getByText('Синтетический факт '+id,{exact:true})});
 await card('confirm').getByRole('button',{name:'Подтвердить',exact:true}).click();await page.locator('.confirmed-card .assertion').getByText('Синтетический факт confirm',{exact:true}).waitFor();verify('confirm result rendered',true);
 await card('edit').getByLabel('Изменить перед подтверждением').fill('Синтетическая подтверждённая правка');await card('edit').getByRole('button',{name:'Изменить и подтвердить'}).click();await page.locator('.confirmed-card .assertion').getByText('Синтетическая подтверждённая правка',{exact:true}).waitFor();verify('edit-confirm result rendered',true);
 await card('reject').getByRole('button',{name:'Отклонить'}).click();await page.getByText('Нет фактов на проверке. Вставьте текст выше или добавьте факт вручную.').waitFor();verify('reject removed pending card',await page.locator('.pending-card').count()===0);
 await capture('390-confirmed');
 failExtraction=true;await page.getByLabel('Текст об опыте или резюме').fill('Синтетический источник');await page.getByRole('button',{name:'Извлечь факты для проверки'}).click();await page.getByRole('alert').waitFor();verify('safe error hides backend message',!(await page.getByRole('alert').innerText()).includes('PRIVATE SECRET'));await page.waitForFunction(()=>[...document.querySelectorAll('button')].some(el=>el.textContent==='Добавить подтверждённый факт'&&!el.disabled));verify('manual remains available after error',await page.getByRole('button',{name:'Добавить подтверждённый факт'}).isEnabled());
 failLogout=true;await page.getByRole('button',{name:/Профиль/}).click();await page.getByRole('button',{name:'Выйти',exact:true}).click();await page.getByRole('dialog').getByRole('alert').waitFor();verify('logout failure retains authenticated session',await page.locator('.career-header h1').count()===1);await page.waitForFunction(()=>[...document.querySelectorAll('button')].some(el=>el.textContent==='Выйти'&&!el.disabled));verify('logout retry remains available',await page.getByRole('button',{name:'Выйти',exact:true}).isEnabled());
 await page.keyboard.press('Escape');
 role='admin';await page.goto('http://127.0.0.1:8080/');await page.waitForLoadState('networkidle');await page.getByRole('button',{name:/Профиль/}).click();await page.getByRole('link',{name:'Диагностика',exact:true}).click();await page.getByRole('heading',{name:'Error Center'}).waitFor();verify('admin profile link reaches guarded Diagnostics',page.url().endsWith('/diagnostics'));
 verify('no page exceptions',errors.length===0);
 fs.writeFileSync(path.join(__dirname,'career-results.json'),JSON.stringify({status:'PASS',checkedAt:new Date().toISOString(),scope:'Ephemeral synthetic API fixtures; no persisted Career Facts, live AI, credentials or backend mutations',checks,requests,errors,captures},null,2)+'\n');
 console.log('Career browser PASS: '+checks.length+' checks');await browser.close();
})().catch(error=>{console.error(error);fs.writeFileSync(path.join(__dirname,'career-failure.json'),JSON.stringify({status:'FAIL',message:error.message,checks,errors},null,2)+'\n');process.exit(1);});
