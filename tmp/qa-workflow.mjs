import fs from 'node:fs';
export async function run({page,role,visit,results,base,events}){
 if(role!=='super_admin' && !process.env.QA_ROLE_FLOW)return;
 const f=JSON.parse(fs.readFileSync('tmp/qa-fixture.json','utf8'));
 if(process.env.QA_ROLE_FLOW)f.rental=f.roleRentals[role];
 const record=async(name,extra={})=>{results.push({role,path:name,text:(await page.locator('body').innerText()).slice(-14000),events:events(),...extra});fs.writeFileSync('tmp/qa-results/workflow.json',JSON.stringify(results,null,2));console.log(JSON.stringify({step:name,...extra}));};
 await visit(`/rentals/${f.rental}`);
 const form=page.locator(`form[action="${base}/rentals/${f.rental}/payment"]`);
 if(await form.count()){
 const paid=await page.locator('[x-data="returnOperationalDashboard()"]') .evaluate(el=>Alpine.$data(el).rental.paid_amount);
 await form.locator('[name=amount]').fill(String(11000-paid));await form.locator('[name=method]').selectOption('cash');
 await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),form.locator('button[type=submit]').click({noWaitAfter:true})]);
 await record('Payment via form');
 }
 if(await page.getByRole('button',{name:/Mulai Sewa/}).count()){
 await page.getByRole('button',{name:/Mulai Sewa/}).click();
 await page.getByRole('button',{name:'Konfirmasi Serah Terima',exact:true}).click();
 // The handover action asks for a second confirmation in SweetAlert.
 await page.waitForTimeout(300);
 if(await page.locator('.swal2-confirm:visible').count())await page.locator('.swal2-confirm:visible').click();
 await page.waitForTimeout(800);
 await record('Handover modal and submit',{data:await page.locator('[x-data="returnOperationalDashboard()"]') .evaluate(el=>JSON.parse(JSON.stringify(Alpine.$data(el).rental)))});
 if(await page.locator('.swal2-confirm:visible').count())await page.locator('.swal2-confirm:visible').click();
 }
 await page.getByRole('button',{name:/Semua Baik/}).click();
 await page.locator('button').filter({hasText:'Konfirmasi Pengembalian'}).click();
 await page.locator('.swal2-confirm:visible').click();
 await page.waitForTimeout(900);await record('Return all items good');
 if(await page.locator('.swal2-confirm:visible').count())await page.locator('.swal2-confirm:visible').click();
 await visit(`/rentals/${f.rental}`);
 await record('Return persisted after reload');
 await page.screenshot({path:'tmp/qa-results/workflow-returned.png',fullPage:true,timeout:10000});
}
