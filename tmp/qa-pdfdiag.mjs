import fs from 'node:fs';
export async function run({page,context,role,base,results}){
 const id=role==='sales'?14:18;
 const paths=[`/rentals/${id}/pdf`,`/rentals/${id}/receipt/pdf`,'/rentals/activity-log/export/pdf'];
 if(role!=='sales')for(const name of ['stock','revenue','transactions','returns','customers','payments','activity-log'])paths.push(`/reports/${name}/export/pdf`);
 for(const path of paths){
  const r=await context.request.get(base+path);const b=await r.body();const row={role,path,status:r.status(),type:r.headers()['content-type'],bytes:b.length,start:b.subarray(0,5).toString()};results.push(row);console.log(JSON.stringify(row));
  if(row.start==='%PDF-')fs.writeFileSync(`tmp/qa-results/${role}-${path.replaceAll('/','-')}.pdf`,b);
  fs.writeFileSync('tmp/qa-results/pdfdiag.json',JSON.stringify(results,null,2));
 }
}
