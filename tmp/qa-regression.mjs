import fs from 'node:fs';
export async function run({page,role,visit,results,base,events}){
 const f=JSON.parse(fs.readFileSync('tmp/qa-fixture.json','utf8'));
 async function req(method,path,data){
  const row=await page.evaluate(async({method,path,data})=>{const r=await fetch(path,{method,headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content},...(data?{body:JSON.stringify(data)}:{})});return {status:r.status,text:(await r.text()).slice(0,5000)}} ,{method,path,data});
  results.push({role,path:method+' '+path,...row});fs.writeFileSync('tmp/qa-results/'+(process.env.QA_EVIDENCE||'regression')+'.json',JSON.stringify(results,null,2));console.log(JSON.stringify({role,method,path,status:row.status}));return row;
 }
 await visit('/products?search=QA_E2E');
 if(role==='sales'){
  for(const [method,path,data] of [['POST','payment',{amount:123,method:'cash'}],['PATCH','handover',{}],['POST','return',{}],['PATCH','cancel-return',{}],['PATCH','update-status',{rental_status:'returned'}],['PATCH','confirm-return-ajax',{}],['POST','mark-refund-given',{amount:1}],['DELETE','',{}],['PATCH','cancel',{}]]) await req(method,`/rentals/${f.rental}`+(path?'/'+path:''),data);
  await visit('/rentals/14');await visit('/rentals/15');await visit('/products');
 }
 if(role==='super_admin' && process.env.QA_DEEP){
  await req('POST',`/rentals/${f.rental}/return`,{});
  await visit(`/products/${f.product}`);
  await req('POST',`/payments/${f.rental}/store`,{amount:1,method:'cash',notes:'QA_E2E payment endpoint'});
  await req('PATCH',`/categories/${f.category}`,{name:'Jas QA_E2E_20261001',slug:'jas-qa-e2e-20261001',sort_order:null,is_active:1});
  await visit('/users?search=Super&role=sales');
 }
 for(const branch of ['1','2','3']) await req('GET','/dashboard/data?branch_id='+branch);
 await req('GET','/api/worker/daily-limit');
 await req('POST','/rentals',{items:'invalid'});
 await req('PATCH',`/rentals/${f.rental}/payments/30`,{amount:123,method:'cash'});
 await visit('/products?search=QA_DOES_NOT_EXIST');
 await visit('/rentals/scan/INV20261001010002');
}
