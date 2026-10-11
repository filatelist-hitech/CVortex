const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
const {createRequire}=require('node:module');
const frontendRequire=createRequire(path.resolve(__dirname,'../../../../apps/frontend/package.json'));
const {chromium}=process.env.CVORTEX_PLAYWRIGHT_MODULE?require(process.env.CVORTEX_PLAYWRIGHT_MODULE):frontendRequire('playwright');
const checks=[],requests=[],errors=[],consoleErrors=[],external=[];
const verify=(name,condition)=>{assert.ok(condition,name);checks.push(name);};
(async()=>{
 const browser=await chromium.launch({headless:true,...(process.env.CVORTEX_CHROMIUM_PATH?{executablePath:process.env.CVORTEX_CHROMIUM_PATH}:{})});
 const context=await browser.newContext({locale:'ru-RU',timezoneId:'Europe/Moscow',deviceScaleFactor:1,reducedMotion:'reduce'});
 let signedIn=true,role='user';
 const vacancy={id:'live',title:'Синтетический инженер',company:'Синтетическая компания',analysis_status:'COMPLETED',analysis_stale:false,analysis_run_stale:false,requirements:[{id:'req',dimension:'TECH_STACK',importance:'MANDATORY',label:'API',source_excerpt:'API',confidence:1}],snapshot:{id:'snap',version:1,raw_text:'<img src=x onerror="window.fixtureXss=true"> внешний источник',source_url:null,imported_at:'2026-10-11'},analysis:{recommendation:'APPLY',key_reasons:['Синтетический результат'],material_gaps:[],uncertainties:[],stale:false,dimensions:[]}};
 const draft={id:'draft',kind:'RESUME_RECOMMENDATION',section:'Опыт',before:'Синтетический исходный текст',content:'Синтетический подтверждённый текст',reason:'Синтетическое обоснование',risk:'Не добавляйте неподтверждённый опыт',status:'DRAFT',revision_number:1,validation_result:'PASS',claim_usages:[],revisions:[],approvals:[]};
 const prep={id:'prep',vacancy_id:'live',status:'DRAFT',stale:false,items:[draft]};
 await context.route('**/*',async route=>{
  const req=route.request(),url=new URL(req.url());if(url.origin!=='http://127.0.0.1:8080'){external.push(url.origin);return route.abort();}
  const endpoint=url.pathname;if(!endpoint.startsWith('/api/')&&endpoint!=='/sanctum/csrf-cookie')return route.continue();
  requests.push({path:endpoint,method:req.method()});let data={},status=200;
  if(endpoint==='/api/v1/me'){status=signedIn?200:401;data=signedIn?{id:'synthetic',email:'synthetic@example.test',role,status:'ACTIVE'}:null;}
  else if(endpoint==='/api/v1/vacancies')data=[vacancy];
  else if(endpoint==='/api/v1/vacancies/live')data=vacancy;
  else if(endpoint==='/api/v1/vacancies/live/preparation'){verify('preparation uses existing POST',req.method()==='POST');data=prep;}
  else if(endpoint==='/api/v1/applications/preparations/prep')data=prep;
  else if(endpoint==='/api/v1/applications/draft-items/draft'){
   verify('draft update remains PATCH',req.method()==='PATCH');const body=req.postDataJSON();
   if(body.action==='edit'){verify('edited content preserved',body.content==='Синтетическая правка');draft.content=body.content;draft.revision_number++;draft.status='DRAFT';}
   else if(body.action==='accept'){verify('accept is separate from approval',draft.status==='DRAFT');draft.status='ACCEPTED';}else if(body.action==='reject')draft.status='REJECTED';else throw Error('Unexpected draft action');data=prep;
  }else if(endpoint==='/api/v1/applications/draft-items/draft/approve'){verify('approval remains explicit POST',req.method()==='POST'&&draft.status==='ACCEPTED');draft.status='APPROVED';data=prep;}
  else if(endpoint==='/api/v1/vacancies/live/chat')data={id:'chat',status:'ACTIVE',model:null,connection_id:null,messages:[]};
  else if(endpoint==='/api/v1/vacancies/live/analysis-drafts')data=[];
  else if(endpoint==='/api/v1/chatgpt/connections')data=[];
  else if(endpoint==='/api/v1/career')data={facts:[],claims:[],sources:[]};
  else if(endpoint==='/api/v1/diagnostics/incidents')data={data:[],current_page:1,last_page:1,total:0};
  else if(endpoint==='/api/v1/auth/logout'){verify('logout remains POST',req.method()==='POST');signedIn=false;status=204;}
  else if(endpoint==='/sanctum/csrf-cookie')status=204;
  else if(endpoint==='/api/v1/auth/login'||endpoint==='/api/v1/auth/register'){
   verify('credentials use POST',req.method()==='POST');if(endpoint.endsWith('/register'))verify('invitation remains body-only',req.postDataJSON().invitation_token==='synthetic-invite');signedIn=true;data={id:'synthetic',email:'synthetic@example.test',role,status:'ACTIVE'};
  }else throw Error('Unexpected endpoint '+endpoint);
  await route.fulfill({status,contentType:'application/json',body:status===204?'':JSON.stringify({data})});
 });
 const page=await context.newPage();page.setDefaultTimeout(10000);page.on('pageerror',e=>errors.push(e.message));page.on('console',m=>{if(m.type()==='error')consoleErrors.push(m.text());});
 const load=async url=>{await page.goto('http://127.0.0.1:8080'+url);await page.waitForLoadState('networkidle');};
 const demo='/?demo=b01&opportunity=northstar&level=work#materials';
 for(const [width,height]of[[1440,900],[768,1024],[390,844],[320,640]]){
  await page.setViewportSize({width,height});await load(demo);await page.locator('[data-demo=true]').waitFor();
  verify(width+': no overflow',await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
  verify(width+': reduced motion respected',await page.locator('#accept').evaluate(e=>getComputedStyle(e).transitionDuration==='0s'));
  const before=requests.length;if(width===768){await page.locator('#open-list').click();}
  if(width<768){await page.locator('#back-level').click();await page.waitForFunction(()=>document.activeElement?.getAttribute('data-opportunity')==='northstar');verify(width+': mobile list returns focus to selected vacancy',true);}await page.locator('#opportunity-filter').fill('cedar');
  verify(width+': search preserves selection',await page.locator('#workspace-title').innerText()==='Старший инженер по тестированию');
  verify(width+': search filters Northstar',await page.locator('[data-opportunity=northstar]').isHidden());
  await page.locator('[data-opportunity=cedar]').click();verify(width+': Cedar blocks approval',await page.locator('#approve').isDisabled()&&await page.locator('#accept').isDisabled());
  await page.locator('#materials [data-open-evidence]').click();verify(width+': evidence opens',await page.locator('#inspector').isVisible());if(width===768){await page.locator('#close-inspector').focus();await page.keyboard.press('Shift+Tab');verify('evidence traps backwards focus',await page.locator('#pin-inspector').evaluate(e=>document.activeElement===e));await page.keyboard.press('Tab');verify('evidence traps forward focus',await page.locator('#close-inspector').evaluate(e=>document.activeElement===e));}await page.keyboard.press('Escape');verify(width+': Escape closes evidence',await page.locator('#inspector').isHidden());await page.waitForFunction(()=>document.activeElement?.matches('#materials [data-open-evidence]'));verify(width+': evidence focus returns',await page.locator('#materials [data-open-evidence]').evaluate(e=>document.activeElement===e));
  if(width===768)await page.locator('#open-list').click();if(width<768)await page.locator('#back-level').click();
  await page.locator('#opportunity-filter').fill('');await page.locator('[data-opportunity=northstar]').click();
  await page.locator('#edit').click();await page.locator('#proposal-edit').fill('Неподтверждённый опыт');
  await page.locator('#scenario-select').selectOption('pending');verify(width+': unsaved scenario guard',await page.locator('#scenario-select').inputValue()==='normal');
  await page.locator('#save-edit').click();verify(width+': unsupported edit blocked',await page.locator('#action-status').getAttribute('role')==='alert'&&await page.locator('#accept').isDisabled());
  await page.locator('#cancel-edit').click();await page.locator('#accept').click();verify(width+': acceptance leaves exact approval pending',await page.locator('#approve').isEnabled()&&(await page.locator('#review-state').innerText()).includes('текст не одобрен'));await page.locator('#approve').click();verify(width+': approval never submits',await page.locator('#review-state').innerText()==='Текст одобрен, отклик не отправлен');
  verify(width+': demo interactions have no API calls',requests.length===before);
  const account=page.locator('#account-trigger');await account.click();verify(width+': account is native modal',await page.locator('#account-dialog').evaluate(e=>e.matches(':modal')));verify(width+': no fake role switch',await page.locator('#demo-session').count()===0);
  await page.keyboard.press('Escape');verify(width+': account Escape and focus return',await account.evaluate(e=>document.activeElement===e));
  if(width<768){await page.locator('.global-menu > summary').click();await page.locator('.global-nav a').focus();await page.keyboard.press('Escape');verify(width+': navigation Escape returns focus',await page.locator('.global-menu > summary').evaluate(e=>document.activeElement===e));}
  if(width===768){await page.locator('#open-list').click();await page.locator('#close-list').focus();await page.keyboard.press('Shift+Tab');verify('tablet list traps backwards focus',await page.locator('[data-opportunity=cedar]').evaluate(e=>document.activeElement===e));await page.keyboard.press('Escape');await page.waitForFunction(()=>document.activeElement?.id==='open-list');verify('tablet list Escape restores focus',await page.locator('#open-list').evaluate(e=>document.activeElement===e));}
 }
 await page.setViewportSize({width:1440,height:900});await load(demo);await page.locator('[data-demo=true]').waitFor();
 await page.locator('#edit').click();await page.locator('#proposal-edit').fill('Писал регрессионные тесты API на Python и pytest.');await page.locator('#save-edit').click();
 verify('supported edit changes exact text without approving',await page.locator('#proposal-copy').innerText()==='Писал регрессионные тесты API на Python и pytest.'&&await page.locator('#approve').isDisabled());
 await page.locator('#reject').click();verify('rejection blocks acceptance and approval',await page.locator('#accept').isDisabled()&&await page.locator('#approve').isDisabled()&&await page.locator('#review-state').innerText()==='Правка отклонена');
 await page.setViewportSize({width:390,height:844});await load('/?demo=b01&opportunity=northstar&level=work#materials');
 await page.locator('#materials [data-open-evidence]').click();await page.goBack();verify('mobile Back restores workspace from evidence',await page.locator('#inspector').isHidden());await page.goForward();verify('mobile Forward restores evidence level',await page.locator('#inspector').isVisible()&&await page.locator('.application-shell').getAttribute('data-level')==='context');
 await page.reload();await page.locator('[data-demo=true]').waitFor();verify('mobile refresh restores evidence deep link',await page.locator('#inspector').isVisible());
 await page.setViewportSize({width:1440,height:900});await load('/?demo=b01&opportunity=northstar#materials');await page.locator('[data-demo=true]').waitFor();
 await page.locator('[data-section=overview]').first().click();await page.locator('[data-section=requirements]').first().click();await page.goBack();verify('Back restores overview',await page.locator('#overview').isVisible());await page.goForward();verify('Forward restores requirements',await page.locator('#requirements').isVisible());
 await page.locator('#requirements [data-open-evidence]').click();await page.locator('#pin-inspector').click();await page.locator('[data-section=history]').first().click();verify('pinned evidence remains same vacancy',await page.locator('#inspector').isVisible()&&(await page.locator('#inspector-scope').innerText()).includes('Northstar Labs'));await page.keyboard.press('Escape');
 await load('/?opportunity=live#materials');await page.locator('[data-draft-edit]').waitFor();await page.waitForFunction(()=>document.querySelector('[data-draft-edit]')?.value==='Синтетический подтверждённый текст');
 verify('live salary honestly unavailable',await page.locator('#vacancy-salary').innerText()==='Не указана');verify('live has no synthetic company',await page.getByText('Northstar Labs',{exact:true}).count()===0);
 const edit=page.locator('[data-draft-edit]');verify('live edit has an associated label',await edit.evaluate(e=>e.labels?.[0]?.textContent.includes('Изменить предложенный текст')));await edit.fill('Синтетическая правка');await page.locator('[data-section=requirements]').first().click();verify('live unsaved tab guard',await edit.isVisible()&&await page.getByRole('button',{name:'Принять правку',exact:true}).isDisabled());await page.getByRole('button',{name:'Сохранить и проверить',exact:true}).click();await page.getByRole('button',{name:'Принять правку',exact:true}).waitFor();await page.waitForFunction(()=>[...document.querySelectorAll('button')].some(b=>b.textContent==='Принять правку'&&!b.disabled));await page.getByRole('button',{name:'Принять правку',exact:true}).click();await page.waitForFunction(()=>[...document.querySelectorAll('button')].some(b=>b.textContent==='Одобрить этот текст'&&!b.disabled));await page.getByRole('button',{name:'Одобрить этот текст',exact:true}).click();await page.getByText('Текст одобрен, отклик не отправлен',{exact:true}).waitFor();
 verify('live approved result rendered',true);await page.locator('[data-section=requirements]').first().click();await page.locator('#requirements summary').click();verify('untrusted source remains plain text',await page.locator('#requirements img').count()===0&&await page.evaluate(()=>window.fixtureXss===undefined));
 await page.locator('.global-nav a').click();await page.getByRole('heading',{name:'Факты вашей карьеры'}).waitFor();verify('Career has its own route',new URL(page.url()).pathname==='/career');await page.getByRole('link',{name:'Вакансии',exact:true}).click();await page.locator('.application-shell').waitFor();verify('Career navigation reaches real workspace',new URL(page.url()).pathname==='/');
 const beforeDiagnostics=requests.filter(r=>r.path.includes('/diagnostics')).length;await load('/diagnostics');verify('user denied Diagnostics',await page.getByRole('heading',{name:'Доступ запрещён'}).isVisible());verify('no user Diagnostics request',requests.filter(r=>r.path.includes('/diagnostics')).length===beforeDiagnostics);
 role='admin';await load('/diagnostics');verify('admin Diagnostics available',await page.getByRole('heading',{name:'Error Center'}).isVisible());await load('/?demo=b01');await page.locator('[data-demo=true]').waitFor();await page.locator('#account-trigger').click();verify('admin utility uses real role',await page.getByRole('dialog').getByRole('link',{name:'Диагностика',exact:true}).isVisible());await page.keyboard.press('Escape');
 role='user';await load('/?demo=b01');await page.locator('[data-demo=true]').waitFor();await page.locator('#account-trigger').click();await page.getByRole('button',{name:'Выйти',exact:true}).click();await page.getByLabel('Электронная почта',{exact:true}).waitFor();verify('logout removes authenticated workspace',await page.locator('.application-shell').count()===0);
 await page.getByLabel('Электронная почта',{exact:true}).fill('synthetic@example.test');await page.getByLabel('Пароль',{exact:true}).fill('synthetic browser passphrase');await page.locator('form').getByRole('button',{name:'Войти',exact:true}).click();await page.locator('.application-shell').waitFor();verify('login enters Application Workspace',true);
 signedIn=false;await load('/register#token=synthetic-invite');verify('registration erases secret fragment',!page.url().includes('synthetic-invite'));await page.getByLabel('Электронная почта',{exact:true}).fill('synthetic@example.test');await page.getByLabel('Пароль',{exact:true}).fill('synthetic browser passphrase');await page.getByLabel('Подтвердите пароль',{exact:true}).fill('synthetic browser passphrase');await page.getByRole('button',{name:'Создать аккаунт',exact:true}).click();await page.locator('.application-shell').waitFor();verify('registration retains real auth flow',true);
 verify('no submission or employer API',!requests.some(r=>/submit|send|employer/.test(r.path)));verify('no external requests',external.length===0);verify('no page exceptions',errors.length===0);
 // Expected 401s are HTTP diagnostics, not hydration/component exceptions.
 const unexpectedConsole=consoleErrors.filter(s=>!s.includes('401 (Unauthorized)'));verify('no unexpected console/hydration errors',unexpectedConsole.length===0);
 fs.writeFileSync(path.join(__dirname,'workspace-results.json'),JSON.stringify({status:'PASS',browser:await browser.version(),scope:'Ephemeral synthetic API fixtures only; no real authenticated Laravel/AI/DB writes',checks,requests,errors,external,consoleErrors,unexpectedConsole},null,2)+'\n');console.log('Workspace browser PASS:',checks.length,'checks');await browser.close();
})().catch(e=>{console.error(e);fs.writeFileSync(path.join(__dirname,'workspace-failure.json'),JSON.stringify({status:'FAIL',message:e.message,checks,errors,external,consoleErrors},null,2)+'\n');process.exit(1)});
