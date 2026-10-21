import fs from 'node:fs';
export async function run({page,role,visit,results,base,events}) {
 if(role!=='super_admin')return;
 const tag='QA_E2E_20261001';
 const fixture=fs.existsSync('tmp/qa-fixture.json')?JSON.parse(fs.readFileSync('tmp/qa-fixture.json','utf8')):{};
 function save(){fs.writeFileSync('tmp/qa-fixture.json',JSON.stringify(fixture,null,2))}
 if(process.env.QA_NEW_RENTAL && fixture.rental){fixture.rentals=[...new Set([...(fixture.rentals||[]),fixture.rental])];delete fixture.rental;save();}
 async function fill(name,value){await page.locator(`[name="${name}"]`).fill(String(value))}
 async function submit(path){const r=await Promise.all([page.waitForResponse(r=>r.url()===base+path && r.request().method()==='POST'),page.waitForNavigation({waitUntil:'domcontentloaded',timeout:60000}),page.locator(`form[action="${base+path}"] button[type=submit]`).click({noWaitAfter:true})]);await page.waitForTimeout(400); results.push({role,path:'SUBMIT '+path,status:r[0].status(),text:await page.locator('body').innerText(),events:events()});console.log(JSON.stringify({submit:path,status:r[0].status(),url:page.url()}));}
 if(!fixture.category){
 await visit('/categories/create');
 await submit('/categories');
 await fill('name','Jas '+tag); await fill('sort_order',90101);
 await submit('/categories');
 fixture.category=await page.locator('a[href*="/categories/"][href$="/edit"]').last().getAttribute('href').then(x=>Number(x.match(/categories\/(\d+)/)[1]));save();
 }
 if(!fixture.branch){
 await visit('/branches/create');await fill('name',tag+' Branch');await fill('code','QAE2E261001');await fill('address','QA temporary');
 await submit('/branches');
 fixture.branch=await page.locator('tr').filter({hasText:tag+' Branch'}).locator('a[href$="/edit"]').getAttribute('href').then(x=>Number(x.match(/branches\/(\d+)/)[1]));save();
 }
 if(!fixture.product){
 await visit('/products/create'); await fill('name',tag+' Product');
 await page.locator('[name=category_id]').selectOption(String(fixture.category));
 await page.locator('[name=condition]').selectOption('good');
 await fill('rental_price',10000); await page.locator('[name=status]').selectOption('available');
 await page.locator('#select-all-branches').uncheck();
 await page.locator('[name="branch_ids[]"][value="1"]').check();
 await page.locator('input[type=checkbox][x-model="selected"]').filter({}).evaluateAll(els=>{});
 await page.locator('input[type=checkbox][x-model="selected"]').nth(2).check();
 await page.locator('input[name^="sizes["][name$="[stock_total]"]').fill('5');
 await page.locator('input[name^="sizes["][name$="[stock_available]"]').fill('5');
 await submit('/products');
 fixture.product=Number(page.url().match(/products\/(\d+)/)?.[1]); if(!fixture.product)throw new Error('Product creation failed');save();
 }
 if(!fixture.customer){
 await visit('/customers/create');await fill('name',tag+' Customer');await fill('phone','089900261001');await submit('/customers');
 await visit('/customers?search='+tag);fixture.customer=await page.locator('a[href]').evaluateAll(els=>{const a=els.map(e=>e.href).find(h=>/\/customers\/\d+$/.test(h));return Number(a?.match(/customers\/(\d+)/)[1])});save();
 }
 if(!fixture.rental){
 await visit('/rentals/create');
 await page.locator('[x-model="customerSearch"]').fill(tag);
 await page.getByText(tag+' Customer',{exact:true}).click();
 await page.locator('[name="items[0][product_id]"]').selectOption(String(fixture.product));
 await page.locator('[name="items[0][product_size]"]').selectOption('M');
 await fill('duration_days',1);await fill('rental_date','2026-10-01');await fill('notes',tag);
 await page.locator('[name=payment_method]').selectOption('cash');await page.locator('[name=guarantee_type]').selectOption('custom');await fill('guarantee_notes','QA temporary guarantee');
 await submit('/rentals');fixture.rental=Number(page.url().match(/rentals\/(\d+)/)?.[1]);if(!fixture.rental)throw new Error('Rental creation failed');save();
 }
 console.log(JSON.stringify({fixture}));
}
