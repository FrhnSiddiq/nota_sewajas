import fs from 'node:fs';
export async function run({page,role,visit,results,base,events}) {
 const fixture=fs.existsSync('tmp/qa-fixture.json')?JSON.parse(fs.readFileSync('tmp/qa-fixture.json','utf8')):{};
 async function request(path,method='GET',body){
 const data=await page.evaluate(async({path,method,body})=>{const r=await fetch(path,{method,headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content},...(body?{body:JSON.stringify(body)}:{})});const text=await r.text();return {status:r.status,url:r.url,type:r.headers.get('content-type'),disposition:r.headers.get('content-disposition'),bytes:text.length,text:text.slice(0,3000)}},{path,method,body});
 const row={role,path:method+' '+path,...data};results.push(row);fs.writeFileSync(`tmp/qa-results/${process.argv[2]}.json`,JSON.stringify(results,null,2));console.log(JSON.stringify({role,path:row.path,status:row.status,type:row.type,bytes:row.bytes}));return row;
 }
 if(!process.env.QA_API_ONLY){
 await visit('/products');
 await page.locator('#btn-table').click({noWaitAfter:true});
 results.push({role,path:'Product table toggle',pass:await page.locator('#view-table').isVisible(),events:events()});
 await page.locator('#btn-ds-card').click({noWaitAfter:true});
 for(const p of ['/rentals/14','/rentals/15','/rentals/14/edit','/rentals/15/edit','/rentals/14/thermal','/rentals/14/receipt','/rentals/scan/INV20260924010001','/rentals/scan/INV20260925010001','/rentals/scan/NOT_VALID','/customers/999999','/products/11','/products/11/edit','/users/4','/branches/2','/transactions/14','/transactions/14/edit','/broadcast-campaigns/1']) await visit(p);
 await visit('/rentals');
 }
 const getPaths=['/dashboard/data','/notifications/data','/notifications/count','/rentals/14/receipt/qr','/rentals/15/receipt/qr','/rentals/14/pdf','/rentals/14/receipt/pdf','/rentals/15/pdf','/rentals/15/thermal','/rentals/15/receipt','/rentals/15/receipt/pdf','/rentals/14/activity-log/download','/rentals/15/activity-log/download','/rentals/activity-log/export/excel','/rentals/activity-log/export/pdf','/dashboard/activity-log/download','/customers/export','/points?branch_id=2','/reports/revenue?branch_id=3','/dashboard/data?branch_id=3','/api/worker/daily-limit'];
 if(role!=='sales') for(const report of ['revenue','transactions','returns','stock','customers','payments','activity-log']) for(const format of [report==='activity-log'?'csv':'excel','pdf'])getPaths.push(`/reports/${report}/export/${format}?start_date=2026-09-01&end_date=2026-10-01`);
 for(const path of getPaths)await request(path);
 for(const path of ['/customers','/products','/rentals','/branches','/categories','/users','/broadcasts','/broadcast-campaigns','/settings']) await request(path,'POST',{});
 await request('/rentals','POST',{items:'malformed'});
 await request('/rentals/15/payment','POST',{amount:-1,method:'cash'});
 await request('/rentals/15/confirm-return-ajax','PATCH',{});
 if(role==='sales'&&fixture.rental){
 await request(`/rentals/${fixture.rental}/payment`,'POST',{amount:123,method:'cash',notes:'QA_E2E_20261001 unauthorized probe'});
 }
 await request('/profile/theme','PUT',{theme:'invalid'});
 await request('/profile/password','PUT',{current_password:'wrong-qa',password:'short',password_confirmation:'different'});
 await request('/api/worker/opt-out','POST',{});await request('/api/worker/update-status','POST',{});
}
