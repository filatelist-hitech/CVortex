// Integrity/derivation check only. Never rewrites the canonical reference or evidence.
(async () => {
const {default:fs}=await import('node:fs'),{default:path}=await import('node:path'),{default:assert}=await import('node:assert/strict'),{default:crypto}=await import('node:crypto');
const root=path.resolve(__dirname,'../../..');
const reference=path.join(root,'docs/07-Design/reference');
const application=path.join(root,'apps/frontend/src/app/application');
const assets=path.join(root,'apps/frontend/public/application-assets');
const source=fs.readFileSync(path.join(reference,'style.css'),'utf8').replace(/\/\*[\s\S]*?\*\//g,'');
const actual=fs.readFileSync(path.join(application,'application.css'),'utf8');
const normalize=text=>text.trim().replace(/\s+/g,' ');
function selector(s){
 if(s.startsWith('@'))return s;
 return s.split(',').map(x=>{x=x.trim();if(x.startsWith('body'))return x.replace('body','.application-shell.application-shell');if(x===':root')return '.application-shell.application-shell';if(x.startsWith('html'))return x.replace('html','html:has(.application-shell)');return '.application-shell.application-shell '+x;}).join(', ');
}
let checks=0;
const rules=[...actual.matchAll(/([^{}]+)\{([^{}]*)\}/g)].map(m=>({selector:normalize(m[1]),body:normalize(m[2])}));
for(const rule of source.matchAll(/([^{}]+)\{([^{}]*)\}/g)){
 const s=normalize(selector(rule[1].trim())),body=normalize(rule[2].replace("url('./assets/SpaceGrotesk.woff2')","url('/application-assets/SpaceGrotesk.woff2')"));
 assert.ok(rules.some(r=>r.selector===s&&r.body===body),'canonical CSS rule differs: '+s);checks++;
}
assert.equal(fs.readFileSync(path.join(application,'tokens.css'),'utf8'),fs.readFileSync(path.join(reference,'tokens.css'),'utf8').replaceAll(':root {','.application-shell {'));checks++;
const hashes={};for(const file of ['SpaceGrotesk.woff2','OFL.txt','career-vortex-v1.png','lucide-LICENSE.txt','font-source.json','sources.json']){
 const original=fs.readFileSync(path.join(reference,'assets',file)),copy=fs.readFileSync(path.join(assets,file));assert.deepEqual(copy,original,file);hashes[file]=crypto.createHash('sha256').update(copy).digest('hex');checks++;
}
const originalSprite=fs.readFileSync(path.join(reference,'index.html'),'utf8').match(/<svg class="icon-sprite"[\s\S]*?<\/svg>/)[0].replaceAll('class=','className=');
assert.ok(fs.readFileSync(path.join(application,'icons.tsx'),'utf8').includes(originalSprite));checks++;
console.log(JSON.stringify({status:'PASS',checks,canonicalRules:checks-8,assets:hashes},null,2));

})().catch(error => { console.error(error); process.exitCode=1; });
