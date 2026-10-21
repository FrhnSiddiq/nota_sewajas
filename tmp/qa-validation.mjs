import fs from 'node:fs';
export async function run({page,role,visit,results,base,context}){
 const f=JSON.parse(fs.readFileSync('tmp/qa-fixture.json','utf8'));
 await visit('/customers');
 const requests=[['POST','/customers',{name:'QA invalid',phone:'invalid'}],['POST','/customers',{name:'x'.repeat(256),phone:'08990029999'}],['POST','/rentals',{items:[null]}],['POST','/rentals',{customer_id:999999,rental_date:'bad',duration_days:-1,items:[{product_id:999999,product_size:'BAD',quantity:-1}]}],['PATCH','/profile',{name:'',email:'invalid'}],['PUT','/profile/theme',{theme:'invalid'}],['PUT','/profile/password',{current_password:'qa-wrong',password:'x',password_confirmation:'y'}],['POST','/products',{}],['POST','/categories',{}],['POST','/branches',{}],['POST','/users',{}],['POST','/broadcasts',{}],['POST','/broadcast-campaigns',{}],['POST','/settings',{fine_per_day:-1,rental_duration_days:-1}]];
 for(const [method,path,data] of requests){
  const token=await page.locator('meta[name=csrf-token]').getAttribute('content');
  const r=await context.request.fetch(base+path,{method,headers:{Accept:'application/json','X-CSRF-TOKEN':token},data});
  results.push({role,path:method+' '+path,status:r.status(),text:(await r.text()).slice(0,600)});console.log(JSON.stringify({role,path,status:r.status()}));
 }
 for(const path of ['/customers?search=qa_e2e','/customers?search=QA_DOES_NOT_EXIST','/customers?page=999','/products?search=qa_e2e&branch_id=2','/rentals?search=QA_E2E&status=returned','/rentals?page=999','/rentals/activity-log?search=QA_DOES_NOT_EXIST','/rentals/activity-log?page=2','/rentals/activity-log?page=999','/notifications?type=payment','/notifications?page=999'])await visit(path);
 if(role!=='sales')for(const name of ['revenue','transactions','returns','stock','customers','payments','activity-log']){
  await visit(`/reports/${name}?branch_id=${f.branch}&period=custom&start_date=2026-10-01&end_date=2026-10-02`);
  const r=await context.request.get(`${base}/reports/${name}/export/${name==='activity-log'?'csv':'excel'}?branch_id=${f.branch}&start_date=2026-10-01&end_date=2026-10-02`);
  const b=await r.body();results.push({role,path:`${name} filtered export`,status:r.status(),bytes:b.length,type:r.headers()['content-type']});
 }
 fs.writeFileSync('tmp/qa-results/validation.json',JSON.stringify(results,null,2));
}
