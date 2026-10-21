import fs from 'node:fs';
export async function run({page,role,visit,results,base,events}){
 if(role!=='super_admin')return;
 const f=JSON.parse(fs.readFileSync('tmp/qa-fixture.json','utf8'));
 const save=()=>fs.writeFileSync('tmp/qa-fixture.json',JSON.stringify(f,null,2));
 const record=async(path)=>{results.push({role,path,text:await page.locator('body').innerText(),events:events()});fs.writeFileSync('tmp/qa-results/broadcast.json',JSON.stringify(results,null,2));console.log(path)};
 const submit=async()=>{await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),page.locator('main form button[type=submit]').last().click({noWaitAfter:true})]);await page.waitForTimeout(300)};
 if(!f.qaUser2){
  await visit('/users/create');
  for(const [name,value] of Object.entries({name:'QA_E2E_20261001 Excluded',email:'qa.e2e.excluded@example.test',password:'QA_test_261001!',password_confirmation:'QA_test_261001!'}))await page.locator(`[name=${name}]`).fill(value);
  await page.locator('[name=role]').selectOption('sales');await page.locator(`[name=branch_id][value="${f.branch}"]`).check();await submit();
  await visit('/users?search=qa.e2e.excluded');f.qaUser2=Number(await page.locator('a[href*="/users/"][href$="/edit"]').first().getAttribute('href').then(x=>x.match(/users\/(\d+)/)[1]));save();
 }
 for(const scheduled of [false,true]){
  await visit('/broadcast-campaigns/create');
  await page.locator('[name=name]').fill('QA_E2E_20261001 '+(scheduled?'Scheduled':'Selected')+(process.env.QA_RETEST?' Retest':''));
  await page.locator('[name=channel][value=in_app]').check();await page.locator('[name=target_type]').selectOption('branch');
  await page.locator('[name="target_filters[branch_id]"]').selectOption(String(f.branch));
  await page.locator('#userFilter [name="recipient_ids[]"]').selectOption(String(f.qaUser));
  await page.locator('[name=custom_message]').fill('QA_E2E test message for temporary QA accounts only');
  if(scheduled)await page.locator('[name=scheduled_at]').fill('2026-10-04T12:00');
  await submit();await record('Campaign submit');const id=Number(page.url().match(/broadcast-campaigns\/(\d+)/)?.[1]);if(!id)throw Error('Campaign failed');f.campaigns=[...(f.campaigns||[]),id];save();await record('Campaign '+id);
 }
}
