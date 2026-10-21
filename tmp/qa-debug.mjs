import fs from 'node:fs';
export async function run({page,visit,role}) {
 if(role!=='super_admin')return;
 page.on('console',m=>console.log('CONSOLE '+m.type()+' '+m.text().slice(0,200)));
 page.on('framenavigated',f=>{if(f===page.mainFrame())console.log('NAV '+f.url())});
 page.on('websocket',ws=>ws.on('framereceived',e=>console.log('WS '+String(e.payload).slice(0,600))));
 await visit('/categories/create');
 await page.waitForTimeout(2500);
 await page.locator('[name=name]').fill('Jas QA_E2E_20261001');
 await page.locator('[name=sort_order]').fill('90101');
 console.log('FORM '+JSON.stringify(await page.locator('form[action$="/categories"]').evaluate(f=>({valid:f.checkValidity(),data:[...new FormData(f)].filter(([k])=>k!=='_token')}))));
 page.on('request',r=>{if(r.isNavigationRequest())console.log('REQ '+r.method()+' '+r.url())});
 page.on('response',r=>{if(r.request().isNavigationRequest())console.log('RES '+r.status()+' '+r.url())});
 await page.locator('form[action$="/categories"] button[type=submit]').click({noWaitAfter:true});
 await page.waitForTimeout(6000);
 console.log('END '+page.url()+' '+(await page.locator('body').innerText()).slice(-1500));
 console.log('FORMS '+JSON.stringify(await page.locator('form').evaluateAll(fs=>fs.map(f=>({action:f.action,method:f.method,valid:f.checkValidity()})))));
}
