const {chromium}=require('playwright');const assert=require('node:assert/strict');const path=require('node:path');
(async()=>{const browser=await chromium.launch({headless:true,executablePath:process.env.CHROME_PATH||'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'});try{
const page=await browser.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));let mode='standalone', delay=0;
const post=id=>({id,key:'cta-'+id,heading:'Popup '+id,html:'<p>Video</p><video controls preload="none"><source src="/clip.mp4" type="video/mp4"></video>',columns:1,until:Math.floor(Date.now()/1000)+3600,dismiss:'forever'});
await page.route('https://trigger.test/**',async route=>{
 if(route.request().url().endsWith('/ajax')){const id=Number(new URLSearchParams(route.request().postData()).get('popup_id'));const now=Math.floor(Date.now()/1000);const data={now,next:now+60,popups:[],popup:null,manual:[2,3].includes(id)?post(id):null,tickers:[],template:mode==='elementor'?100:0,divi_template:mode==='divi'?200:0,settings:{delay:0,fade_enabled:0,complianz_delay:0}};if(id===2&&delay)await new Promise(r=>setTimeout(r,delay));return route.fulfill({json:{success:true,data}});}
 if(route.request().url().endsWith('.mp4'))return route.fulfill({status:404,body:''});
 return route.fulfill({contentType:'text/html',body:'<!doctype html><html><body><a id="link" href="#notice-popup-2">Video</a><button class="notice-popup-2"><span>Class</span></button><button id="notice-popup-3">ID</button><button data-notice-popup="2">Data</button><a id="missing" href="#notice-popup-9">Missing</a><a href="#other" id="other">Other</a></body></html>'});
});
async function load(renderer){mode=renderer;delay=0;await page.goto('https://trigger.test/');await page.addStyleTag({path:path.join(__dirname,'../assets/css/frontend.css')});await page.evaluate(mode=>{
 window.ZZZNM={endpoint:'/ajax',close:'Schließen'};localStorage.setItem('cta-2','1');window.mediaStops=0;HTMLMediaElement.prototype.pause=function(){window.mediaStops++;};
 if(mode==='divi'){const source=document.createElement('div');source.hidden=true;source.id='zzznm-divi-template';source.dataset.templateId='200';source.innerHTML='<div class="zzznm-divi-content"><h3>[notice_popup_heading]</h3><div data-zzznm-popup="text"></div></div>';document.body.append(source);}
 if(mode==='elementor'){const handlers={};window.jQuery=()=>({on(name,cb){handlers[name.split('.')[0]]=cb;return this;}});const modal=document.createElement('div');modal.id='elementor-popup-modal-100';modal.style.display='none';modal.innerHTML='<button class="dialog-close-button">Close</button><h3 data-zzznm-popup="heading"></h3><div data-zzznm-popup="text"></div>';document.body.append(modal);modal.querySelector('button').onclick=()=>{setTimeout(()=>{modal.style.display='none';handlers['elementor/popup/hide']?.({},100);},100);};window.elementorProFrontend={modules:{popup:{showPopup(){modal.style.display='block';handlers['elementor/popup/show']?.({},100);}}}};}
},mode);await page.addScriptTag({path:path.join(__dirname,'../assets/js/frontend.js')});await page.waitForTimeout(200);}
const root=()=>mode==='elementor'?'#elementor-popup-modal-100':'dialog[open]';
async function visible(id){await page.waitForFunction(({root,id})=>{const el=document.querySelector(root);return el&&el.getClientRects().length&&el.textContent.includes('Popup '+id);},{root:root(),id});}
async function close(){await page.locator(mode==='elementor'?'.dialog-close-button':'.zzznm-close').click();await page.waitForTimeout(450);}
for(const renderer of ['standalone','divi','elementor']){
 await load(renderer);assert.equal(await page.locator('dialog[open]').count(),0);
 await page.locator('#link').click();await visible(2);assert.equal(await page.locator(root()+' video').count(),1);
 await page.locator(root()+' video').evaluate(v=>v.dataset.original='yes');
 await page.evaluate(()=>document.dispatchEvent(new Event('visibilitychange')));await page.waitForTimeout(200);
 assert.equal(await page.locator(root()+' video').getAttribute('data-original'),'yes','Polling preserves video node');
 await close();assert.ok(await page.evaluate(()=>window.mediaStops)>0,'Closing stops video');
 await page.locator('.notice-popup-2 span').click();await visible(2);await close();
 await page.locator('#notice-popup-3').focus();await page.keyboard.press('Enter');await visible(3);await close();
 await page.locator('[data-notice-popup="2"]').click();await visible(2);await close();
 assert.equal(await page.evaluate(()=>localStorage.getItem('cta-3')),null,'Manual closing does not persist automatic dismissal');
 await page.locator('#missing').click();await page.waitForTimeout(200);assert.equal(await page.locator('#zzznm-trigger-message').textContent(),'Dieses Popup ist derzeit nicht verfügbar.');
}
await load('standalone');delay=250;
await page.locator('#link').click();await page.locator('#notice-popup-3').click();await visible(3);await close();
await page.evaluate(()=>{window.cmplz_get_banner_status=()=> 'not-dismissed';document.dispatchEvent(new Event('cmplz_status_change'));});
await page.locator('#link').click();await page.waitForTimeout(400);assert.equal(await page.locator('dialog[open]').count(),0,'CMP holds manual click');
await page.evaluate(()=>{window.cmplz_get_banner_status=()=> 'dismissed';document.dispatchEvent(new Event('cmplz_status_change'));});await visible(2);await close();
assert.deepEqual(errors,[]);console.log('PASS: CTA links, class, ID, data attribute, keyboard, repeated opening, fresh requests, CMP and video lifecycle across all three renderers.');
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exitCode=1;});
