import fs from 'node:fs';
import {run as workflow} from './qa-workflow.mjs';
export async function run(args){
 const {page,role,visit,base,results}=args;
 const f=JSON.parse(fs.readFileSync('tmp/qa-fixture.json','utf8'));
 f.roleRentals??={};
 let customerName='QA_E2E_20261001 Customer';
 if(role==='sales'){
  customerName='QA_E2E_20261001 Sales Customer';
  if(!f.salesCustomer){
   await visit('/customers/create');await page.locator('[name=name]').fill(customerName);await page.locator('[name=phone]').fill('089900261002');
   await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),page.locator(`form[action="${base}/customers"] button[type=submit]`).click({noWaitAfter:true})]);
   await visit('/customers?search=QA_E2E');f.salesCustomer=await page.locator('a[href]').evaluateAll(es=>Number(es.map(e=>e.href).find(h=>/\/customers\/\d+$/.test(h))?.match(/customers\/(\d+)/)[1]));
   fs.writeFileSync('tmp/qa-fixture.json',JSON.stringify(f,null,2));
  }
 }
 if(!f.roleRentals[role]){
  await visit('/rentals/create');
  await page.locator('[x-model="customerSearch"]').fill('QA_E2E_20261001');
  await page.getByText(customerName,{exact:true}).click();
  await page.locator('[name="items[0][product_id]"]').selectOption(String(f.product));
  await page.locator('[name="items[0][product_size]"]').selectOption('M');
  for(const [name,value] of Object.entries({duration_days:'1',rental_date:'2026-10-02',notes:'QA_E2E role '+role,guarantee_notes:'QA guarantee'})) {
   if(name==='guarantee_notes')await page.locator('[name=guarantee_type]').selectOption('custom');
   await page.locator(`[name=${name}]`).fill(value);
  }
  await page.locator('[name=payment_method]').selectOption('cash');
  await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),page.locator(`form[action="${base}/rentals"] button[type=submit]`).click({noWaitAfter:true})]);
  const id=Number(page.url().match(/rentals\/(\d+)/)?.[1]);if(!id)throw Error('Role rental creation failed: '+(await page.locator('body').innerText()).slice(-2000));
  f.roleRentals[role]=id;f.rentals=[...new Set([...(f.rentals||[]),id])];fs.writeFileSync('tmp/qa-fixture.json',JSON.stringify(f,null,2));
 }
 process.env.QA_ROLE_FLOW='1';await workflow(args);
 await visit('/rentals?search=QA_E2E&rental_status=returned');
 await visit('/points');
 await page.locator('form[action$="/logout"]').first().evaluate(form=>form.requestSubmit());
 await page.waitForURL('**/login');
 results.push({role,path:'LOGOUT',url:page.url()});
}
