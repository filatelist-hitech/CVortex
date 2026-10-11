'use strict';
const $ = (s, root=document) => root.querySelector(s);
const $$ = (s, root=document) => [...root.querySelectorAll(s)];
window.announce = text => { $('#status-live').textContent=text; };
window.openDialog = (title, content) => {
  const dialog=$('#context-dialog'); $('#dialog-title').textContent=title;
  $('#dialog-content').replaceChildren();
  // content is exclusively static prototype markup; never external or user text.
  $('#dialog-content').innerHTML=content;
  dialog.showModal();
};
window.showEvidence = (factIds=['F-12','F-18']) => {
  const facts=FIXTURES.facts.filter(f=>factIds.includes(f.id));
  openDialog('Content → Claim → Career Fact', `<p class="demo">Synthetic provenance. All records are fictional.</p><ol><li><strong>Resume version v3 · proposed content</strong><p>${FIXTURES.recommendation.after}</p></li><li><strong>Claim CL-08 · PASS in fixture</strong><p>API regression and transaction testing are supported. This is not a probability score.</p></li>${facts.map(f=>`<li><span class="badge ${f.status==='CONFIRMED'?'good':'warn'}">${f.id} · ${f.status}</span><p>${f.statement}</p><p class="source">${f.source}</p></li>`).join('')}</ol><p class="muted compact">Approval stays separate from claim validation. Nothing is sent to an employer.</p>`);
};
window.showMemory = company => {
  if(company==='Cedar Systems'){
    openDialog('Employer Memory · contradiction', '<span class="badge block">USER_RESOLUTION_REQUIRED</span><p>Confirmed demo preference: remote only. New draft: available for hybrid work.</p><div class="source">Demo user note · 3 October 2026 · employer association explicitly confirmed in the fixture.</div><p>Keep the existing preference or explicitly review a change. An AI draft cannot overwrite it.</p>');
  }else{
    openDialog(`Employer Memory · ${company}`, company==='Northstar Labs'?'<p class="demo">Synthetic employer association is explicitly confirmed.</p><ul class="timeline"><li><time>2 Oct 2026</time><p>Recruiter prioritizes API testing. This is employer context, not a candidate fact.</p></li><li><time>3 Oct 2026</time><p>User confirmed remote only and 260k ₽ gross / month.</p></li></ul><p>Use these commitments when reviewing the next draft. Source dates and confirmation remain visible.</p>':'<p>No confirmed employer history in this synthetic fixture. Imported company text alone is insufficient to establish an employer association.</p>');
  }
};
$$('[data-evidence]').forEach(b=>b.addEventListener('click',()=>showEvidence()));
$$('[data-memory]').forEach(b=>b.addEventListener('click',()=>showMemory(b.dataset.memory)));
$('#dialog-close').addEventListener('click',()=>$('#context-dialog').close());
$('#view-state').addEventListener('change',e=>{
  const mode=e.target.value, normal=mode==='normal';
  $('#prototype-content').hidden=!normal; $('#state-screen').hidden=normal;
  const content={loading:['Analyzing saved vacancy…','The source is preserved. Review actions stay unavailable until the new result arrives.'],empty:['No opportunities in this view','Save a vacancy or return to a view with active opportunities.'],error:['Analysis unavailable','The provider did not return a result. Your saved source and drafts are preserved. Retry is a local simulation.']}[mode];
  if(content){$('#state-title').textContent=content[0];$('#state-copy').textContent=content[1];$('#state-recover').textContent=mode==='error'?'Retry simulation':'Return to demo data';$('#state-title').focus();}
  announce(normal?'Demo data restored.':content[0]);
});
$('#state-recover').addEventListener('click',()=>{$('#view-state').value='normal';$('#view-state').dispatchEvent(new Event('change'));$('#main-title').focus();});
$('#reset-demo').addEventListener('click',()=>location.reload());
function commands(){
  openDialog('Find context', '<label>Company or action<input id="command-query" autocomplete="off" placeholder="Search synthetic context"></label><div id="command-results" class="stack"></div>');
  const render=()=>{
    const q=$('#command-query').value.toLowerCase();
    const results=FIXTURES.applications.filter(a=>`${a.company} ${a.role} ${a.next}`.toLowerCase().includes(q));
    const root=$('#command-results');root.replaceChildren();
    if(!results.length){const p=document.createElement('p');p.textContent='No matching fixture. Try “Northstar” or “interview”.';root.append(p);}
    results.forEach(a=>{const b=document.createElement('button');b.style.width='100%';b.style.textAlign='left';b.textContent=`${a.company} · ${a.next}`;b.addEventListener('click',()=>{ $('#context-dialog').close(); if(window.selectApplication)selectApplication(a.id);else openDialog(a.company,`<p>${a.role}</p><p>${a.stage} · ${a.next}</p><p>${a.reason}</p>`); });root.append(b);});
  };$('#command-query').addEventListener('input',render);render();$('#command-query').focus();
}
$('#find-context').addEventListener('click',commands);
document.addEventListener('keydown',e=>{if((e.metaKey||e.ctrlKey)&&e.key.toLowerCase()==='k'){e.preventDefault();if($('#context-dialog').open)$('#context-dialog').close();else commands();}});
