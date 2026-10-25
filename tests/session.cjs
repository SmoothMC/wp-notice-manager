const {chromium}=require('playwright');
const assert=require('node:assert/strict');const path=require('node:path');
(async()=>{const browser=await chromium.launch({headless:true,executablePath:process.env.CHROME_PATH||'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'});try{
const context=await browser.newContext();const errors=[];
await context.route('https://session.test/**',async route=>{
 const now=Math.floor(Date.now()/1000);
 if(route.request().url().endsWith('/ajax'))return route.fulfill({json:{success:true,data:{now,next:now+60,template:0,tickers:[],settings:{delay:0,fade_enabled:0},popups:[{id:1,key:'session-one',heading:'Sitzung',html:'<p>Hinweis</p>',columns:1,dismiss:'session',until:now+3600}]}}});
 return route.fulfill({contentType:'text/html',body:'<!doctype html><html><body><button>Seite</button></body></html>'});
});
async function load(page,url='https://session.test/'){await page.goto(url);await page.evaluate(()=>{window.ZZZNM={endpoint:'/ajax',close:'Schließen'};localStorage.setItem('session-one','1');});await page.addScriptTag({path:path.join(__dirname,'../assets/js/frontend.js')});await page.waitForTimeout(400);}
const page=await context.newPage();page.on('pageerror',e=>errors.push(e.message));await load(page);
await page.locator('dialog[open]').waitFor(); // Persistent local record must not suppress session mode.
await page.locator('.zzznm-close').click();await page.waitForTimeout(400);
assert.equal(await page.evaluate(()=>sessionStorage.getItem('session-one')),'1');
assert.deepEqual(await context.cookies(),[],'No cookies written');
await load(page);assert.equal(await page.locator('dialog').count(),0,'Reload stays dismissed');
await load(page,'https://session.test/another-page');assert.equal(await page.locator('dialog').count(),0,'Same-tab navigation stays dismissed');
const other=await context.newPage();await load(other);await other.locator('dialog[open]').waitFor();
await page.close();const reopened=await context.newPage();await load(reopened);await reopened.locator('dialog[open]').waitFor();
const blocked=await context.newPage();await blocked.addInitScript(()=>{Object.defineProperty(window,'sessionStorage',{get(){throw new Error('Blocked');}});});await load(blocked);await blocked.locator('dialog[open]').waitFor();await blocked.locator('.zzznm-close').click();await blocked.waitForTimeout(500);assert.equal(await blocked.locator('dialog').count(),0,'Blocked storage still avoids a loop');
assert.deepEqual(await context.cookies(),[]);assert.deepEqual(errors,[]);console.log('PASS: no cookies; same-tab reload/navigation, independent tabs, reopened tabs, localStorage isolation and blocked sessionStorage.');
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exitCode=1;});
