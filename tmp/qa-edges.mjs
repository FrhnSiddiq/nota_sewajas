import fs from 'node:fs';
export async function run({page,role,visit,results,base,events}){
 if(role!=='super_admin')return;
 const f=JSON.parse(fs.readFileSync('tmp/qa-fixture.json','utf8'));
 const save=()=>fs.writeFileSync('tmp/qa-fixture.json',JSON.stringify(f,null,2));
 const record=async(path,extra={})=>{results.push({role,path,text:(await page.locator('body').innerText()).slice(-12000),events:events(),...extra});fs.writeFileSync('tmp/qa-results/edges.json',JSON.stringify(results,null,2));console.log(JSON.stringify({path,...extra}))};
 async function submit(){await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),page.locator('main form button[type=submit]').last().click({noWaitAfter:true})]);await page.waitForTimeout(250)}
 async function req(method,path,data){const row=await page.evaluate(async({method,path,data})=>{const r=await fetch(path,{method:method==='GET'?'GET':'POST',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content},...(method==='GET'?{}:{body:JSON.stringify({...data,_method:method})})});return {status:r.status,url:r.url,text:(await r.text()).slice(0,2500)}},{method,path,data});await record(method+' '+path,row);return row;}
 for(const kind of ['categories','branches']){
  await visit(`/${kind}/${kind==='categories'?f.category:f.branch}/edit`);
  await page.locator('[name=is_active]').uncheck();
  if(kind==='categories')await page.locator('[name=sort_order]').fill('');
  await submit();await visit(`/${kind}/${kind==='categories'?f.category:f.branch}/edit`);
  await record(kind+' unchecked persisted',{checked:await page.locator('[name=is_active]').isChecked()});
  await page.locator('[name=is_active]').check();await submit();
 }
 const data={customer_id:f.customer,rental_date:'2026-10-02',duration_days:1,items:[{product_id:f.product,product_size:'7XL',quantity:1}],payment_method:'cash',guarantee_type:'custom',guarantee_notes:'QA_E2E validation',notes:'QA_E2E invalid size'};
 if(!process.env.QA_EDGE_SKIP_CREATE){
  for(const [label,body] of [['size',data],['discount',{...data,items:[{product_id:f.product,product_size:'M',quantity:1}],discount:20000,notes:'QA_E2E invalid discount'}]]){
   const r=await req('POST','/rentals',body);const id=Number(r.url.match(/rentals\/(\d+)$/)?.[1]);if(id){f.rentals=[...new Set([...(f.rentals||[]),id])];save()}
  }
 }
 await req('PATCH','/rentals/17/cancel',{});await req('PATCH','/rentals/17/cancel',{});
 await req('POST',`/rentals/${f.rental}/payments/32/refund`,{});
 await req('POST',`/rentals/${f.rental}/payments/32/void`,{});
 await req('POST',`/payments/${f.rental}/store`,{amount:1,method:'invalid'});
 await visit('/users/create');
 if(!f.qaUser){
  for(const [name,value] of Object.entries({name:'QA_E2E_20261001 User',email:'qa.e2e.20261001@example.test',password:'QA_test_261001!',password_confirmation:'QA_test_261001!'}))await page.locator(`[name=${name}]`).fill(value);
  await page.locator('[name=role]').selectOption('sales');
  await page.locator(`[name=branch_id][value="${f.branch}"]`).check();
  await submit();await visit('/users?search=qa.e2e.20261001');
  f.qaUser=Number(await page.locator('a[href*="/users/"][href$="/edit"]').first().getAttribute('href').then(x=>x.match(/users\/(\d+)/)[1]));save();
 }
 await record('QA user fixture',{id:f.qaUser});
}
