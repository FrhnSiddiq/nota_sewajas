import { chromium } from './qa-tools/node_modules/playwright/index.mjs';
import fs from 'node:fs';
process.env.PW_TEST_SCREENSHOT_NO_FONTS_READY = '1';
const mode = process.argv[2] || 'smoke';
const base = process.env.QA_BASE || 'http://localhost:8000';
fs.mkdirSync('tmp/qa-results', {recursive:true});
const browser = await chromium.launch({headless:true, executablePath:'C:/Users/LuciusYoni/AppData/Local/ms-playwright/chromium-1243/chrome-win64/chrome.exe'});
const results = [];
const accounts = [ ['super_admin','superadmin@jasrental.id',process.env.QA_SUPER_PASSWORD], ['admin_toko','admin.solo@jasrental.id',process.env.QA_STAFF_PASSWORD], ['sales','budi.santoso@jasrental.id',process.env.QA_STAFF_PASSWORD] ];
try {
for (const [role,email,password] of accounts.filter(a=>!process.env.QA_ROLES || process.env.QA_ROLES.split(',').includes(a[0]))) {
 const context = await browser.newContext({viewport:{width:1440,height:1000},acceptDownloads:true});
 // Artifact writes trigger dev-server full reloads. Keep application traffic intact.
 await context.routeWebSocket(/:5173\//, ws=>{
   const server=ws.connectToServer();
   server.onMessage(message=>{try{if(JSON.parse(String(message)).type==='full-reload')return;}catch{}ws.send(message);});
 });
 const page = await context.newPage();
 let events=[];
 page.on('pageerror',e=>events.push({kind:'exception',message:e.message}));
 page.on('console',m=>{if(['error','warning'].includes(m.type()))events.push({kind:m.type(),message:m.text().slice(0,400)})});
 page.on('response',r=>{if(r.status()>=400)events.push({kind:'http',status:r.status(),url:r.url()})});
 page.on('requestfailed',r=>events.push({kind:'network',url:r.url(),error:r.failure()?.errorText}));
 page.on('dialog',d=>d.dismiss());
 async function visit(path) {
   events=[];
   let response;
   try { response=await page.goto(base+path,{waitUntil:'domcontentloaded',timeout:30000}); }
   catch(e) { console.log(JSON.stringify({role,path,navigationRetry:e.message.split('\n')[0]})); response=await page.goto(base+path,{waitUntil:'domcontentloaded',timeout:60000}); }
   await page.waitForTimeout(400);
   const body=await page.locator('body').innerText();
   const row={role,path,status:response.status(),url:page.url(),title:await page.title(),events:[...events],text:body.slice(0,16000)};
   results.push(row); fs.writeFileSync(`tmp/qa-results/${mode}.json`,JSON.stringify(results,null,2)); console.log(JSON.stringify({role,path,status:row.status,exceptions:events.filter(e=>e.kind==='exception'),errors:events.filter(e=>e.kind==='http')}));
   return row;
 }
 await visit('/login');
 await page.locator('input[name=email]').fill(email);
 await page.locator('input[name=password]').fill(password);
 await Promise.all([page.waitForURL('**/dashboard',{timeout:60000,waitUntil:'domcontentloaded'}),page.locator('button[type=submit]').click({noWaitAfter:true})]);
 console.log(JSON.stringify({role,login:page.url()}));
 results.push({role,path:'LOGIN',status:200,events:[...events],text:await page.locator('body').innerText()});
 if(['smoke','discover'].includes(mode))await page.screenshot({path:`tmp/qa-results/${role}-dashboard.png`,fullPage:true,timeout:10000}).catch(e=>console.log('Screenshot: '+e.message.split('\n')[0]));
 if(mode==='smoke'){
   await visit('/rentals/activity-log');
   await visit('/notifications');
 } else if(mode==='discover') {
   const paths=['/dashboard','/profile','/customers','/customers/create','/products','/rentals','/rentals/create','/rentals/scan','/rentals/activity-log','/notifications','/points','/search?q=QA_NOT_FOUND_20261001','/categories','/categories/create','/branches','/branches/create','/users','/users/create','/products/create','/broadcasts','/broadcast-campaigns','/broadcast-campaigns/create','/reports/revenue','/reports/transactions','/reports/returns','/reports/stock','/reports/customers','/reports/payments','/reports/activity-log','/transactions','/settings'];
   for (const path of paths) await visit(path);
 } else {
   const {run}=await import(`./qa-${mode}.mjs`);
   await run({page,context,role,visit,results,base,events:()=>events});
 }
 await context.close();
 fs.writeFileSync(`tmp/qa-results/${mode}.json`,JSON.stringify(results,null,2));
}
} finally {fs.writeFileSync(`tmp/qa-results/${mode}.json`,JSON.stringify(results,null,2));await browser.close();}
