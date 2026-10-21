import fs from 'node:fs';
export async function run({page,role,visit,results,events}){
 const f=JSON.parse(fs.readFileSync('tmp/qa-fixture.json','utf8'));
 const id=role==='sales'?14:f.rental;
 await visit(`/rentals/${id}/receipt`);
 await page.evaluate(()=>{window.print=()=>{window.qaPrintCalled=true}});
 await page.getByRole('button',{name:'Print',exact:true}).click();
 results.push({role,path:'PRINT',called:await page.evaluate(()=>window.qaPrintCalled)});
 const links=[`/rentals/${id}/receipt/pdf`,`/rentals/${id}/pdf`];
 if(role!=='sales' && !process.env.QA_DOC_SHORT) for(const report of ['revenue','transactions','returns','stock','customers','payments','activity-log']) links.push(`/reports/${report}/export/pdf`);
 links.push('/rentals/activity-log/export/pdf');
 for(const href of links){
  await page.evaluate(href=>{const a=document.createElement('a');a.id='qa-download';a.href=href;a.textContent='QA download';document.body.append(a)},href);
  try{
   const [download]=await Promise.all([page.waitForEvent('download',{timeout:15000}),page.locator('#qa-download').click({noWaitAfter:true})]);
   const path=`tmp/qa-results/${role}-${href.replaceAll('/','-')}.pdf`;await download.saveAs(path);
   const b=fs.readFileSync(path);results.push({role,path:href,filename:download.suggestedFilename(),bytes:b.length,pdf:b.subarray(0,5).toString()==='%PDF-',failure:await download.failure()});
  }catch(e){results.push({role,path:href,error:e.message,events:events()})}
  await page.locator('#qa-download').evaluate(e=>e.remove());
  fs.writeFileSync('tmp/qa-results/documents.json',JSON.stringify(results,null,2));console.log(JSON.stringify(results.at(-1)));
 }
 await visit(`/rentals/${id}/thermal`);
}
