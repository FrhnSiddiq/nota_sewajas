import fs from 'node:fs';
export async function run({page,role,visit,results,base,events}){
 if(role!=='super_admin')return;
 const f=JSON.parse(fs.readFileSync('tmp/qa-fixture.json','utf8'));
 await visit('/broadcasts');await page.locator('[name=title]').fill('QA_E2E classic');await page.locator('[name=message]').fill('QA_E2E temporary notification');
 await page.locator('[name=target][value=branch]').check({force:true});await page.locator('[name=branch_id]').selectOption(String(f.branch));
 await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),page.locator(`form[action="${base}/broadcasts"] button[type=submit]`).click({noWaitAfter:true})]);
 results.push({role,path:'Classic broadcast submit',text:(await page.locator('body').innerText()).slice(0,4500),events:events()});
 fs.writeFileSync('tmp/qa-results/classicbroadcast.json',JSON.stringify(results,null,2));
}
