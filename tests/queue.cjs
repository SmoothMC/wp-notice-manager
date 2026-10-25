// Browser integration doubles for queue sequencing; run with Node + Playwright.
const {chromium} = require('playwright');
const assert = require('node:assert/strict');
const path = require('node:path');
(async () => {
  const browser = await chromium.launch({headless:true, executablePath:process.env.CHROME_PATH || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'});
  try {
    const page = await browser.newPage();
    const errors = []; page.on('pageerror', e => errors.push(e.message));
    let entries, mode, fail = false;
    const post = id => ({id, key:`queue-${id}`, heading:`Popup ${id}`, html:`<p>Inhalt ${id}</p>`, columns:1,
      until:Math.floor(Date.now()/1000)+3600, dismiss:'always', button_text:`Button ${id}`, button_url:`/info-${id}`});
    await page.route('https://queue.test/**', async route => {
      if (!route.request().url().endsWith('/ajax')) return route.fulfill({contentType:'text/html', body:'<!doctype html><html><body></body></html>'});
      if (fail) return route.fulfill({status:503, body:'Unavailable'});
      const now = Math.floor(Date.now()/1000);
      await route.fulfill({json:{success:true,data:{popups:entries,popup:entries[0] || null,tickers:[],now,next:now+60,
        template:mode === 'elementor' ? 100 : 0, divi_template:mode === 'divi' ? 200 : 0,
        settings:{delay:0,complianz_delay:0,fade_enabled:0,ticker_mode:'rotate',interval:6,speed:45}}}});
    });
    async function load(renderer='standalone') {
      mode=renderer; fail=false; entries=[post(1),post(2),post(3)];
      await page.goto('https://queue.test/');
      await page.addStyleTag({path:path.join(__dirname,'../assets/css/frontend.css')});
      await page.evaluate(renderer => {
        localStorage.clear(); window.ZZZNM={endpoint:'/ajax',close:'Schließen'};
        if (renderer === 'divi') {
          const source=document.createElement('div'); source.id='zzznm-divi-template';source.hidden=true;source.dataset.templateId='200';
          source.innerHTML='<div class="zzznm-divi-content"><h3>[notice_popup_heading]</h3><div data-zzznm-popup="text"></div><div class="zzznm-popup-button"><a href="#notice-popup-link">Platzhalter</a></div></div>';
          document.body.append(source);
        }
        if (renderer === 'elementor') {
          const handlers={}; window.jQuery=()=>({on(name, callback){handlers[name.split('.')[0]]=callback;return this;}});
          const modal=document.createElement('div');modal.id='elementor-popup-modal-100';modal.style.display='none';
          modal.innerHTML='<button class="dialog-close-button">Close</button><h3 data-zzznm-popup="heading"></h3><div data-zzznm-popup="text"></div><div data-zzznm-popup="button"></div>';
          document.body.append(modal);
          let closing=false;
          modal.querySelector('button').onclick=()=>{
            if(closing) return; closing=true;
            setTimeout(()=>{modal.style.display='none';closing=false;handlers['elementor/popup/hide']?.({},100);},700);
          };
          window.elementorProFrontend={modules:{popup:{showPopup(){
            if(closing) throw new Error('Template reopened while closing');
            modal.style.display='block';handlers['elementor/popup/show']?.({},100);
          }}}};
        }
      },renderer);
      await page.addScriptTag({path:path.join(__dirname,'../assets/js/frontend.js')});
    }
    const heading=()=>page.locator(mode==='elementor'?'#elementor-popup-modal-100 h3':mode==='divi'?'dialog h3':'dialog h2');
    async function expect(id) {
      await page.waitForFunction(({id,mode})=>{
        const h=document.querySelector(mode==='elementor'?'#elementor-popup-modal-100 h3':mode==='divi'?'dialog h3':'dialog h2');
        return h?.textContent===`Popup ${id}` && !!h.getClientRects().length;
      },{id,mode});
      assert.ok(await page.locator('dialog[open]').count()<=1);
    }
    async function dismiss() {await page.locator(mode==='elementor'?'.dialog-close-button':'.zzznm-close').click();}
    async function refresh() {await page.evaluate(()=>document.dispatchEvent(new Event('visibilitychange')));await page.waitForTimeout(200);}
    for(const renderer of ['standalone','divi','elementor']) {
      await load(renderer);await expect(1);
      entries=[post(4),...entries];await refresh();await expect(1); // No preemption.
      await dismiss();await expect(4);
      if(renderer==='divi') {
        assert.equal(await page.locator('dialog .zzznm-popup-button a').getAttribute('href'),'/info-4');
        assert.equal(await page.locator('dialog .zzznm-popup-button a').textContent(),'Button 4');
      }
      entries=entries.filter(p=>p.id!==2); // Withdraw the next popup before closing.
      await dismiss();await expect(3);
      await dismiss();await page.waitForTimeout(1300);await refresh();
      assert.equal(await page.locator('dialog[open]').count(),0);
      if(renderer==='elementor') assert.equal(await page.locator('#elementor-popup-modal-100').isVisible(),false);
    }
    await load();await expect(1);entries[1].dismiss='content';
    await page.evaluate(()=>localStorage.setItem('queue-2','1'));
    await dismiss();await expect(3); // Persisted dismissal is skipped.
    await load();await expect(1);fail=true;await dismiss();await page.waitForTimeout(500);
    assert.equal(await page.locator('dialog').count(),0,'Network failure must not advance stale queue');
    await load('divi');await expect(1);
    await page.evaluate(()=>{window.cmplz_get_banner_status=()=> 'not-dismissed';document.dispatchEvent(new Event('cmplz_status_change'));});
    assert.equal(await page.locator('dialog').count(),0);
    await page.evaluate(()=>{window.cmplz_get_banner_status=()=> 'dismissed';document.dispatchEvent(new Event('cmplz_status_change'));});
    await expect(1);await dismiss();await expect(2); // CMP suspension did not consume popup.
    await load('elementor');await expect(1);
    await page.evaluate(()=>{window.cmplz_get_banner_status=()=> 'not-dismissed';document.dispatchEvent(new Event('cmplz_status_change'));});
    await page.evaluate(()=>{window.cmplz_get_banner_status=()=> 'dismissed';document.dispatchEvent(new Event('cmplz_status_change'));});
    await page.waitForTimeout(1300);await expect(1);await dismiss();await expect(2);
    await load();await expect(1);
    entries[1].until=Math.floor(Date.now()/1000)-1;await dismiss();await expect(3);
    assert.deepEqual(errors,[]);
    console.log('PASS: popup queues across Standalone, Divi, Elementor; stable current popup, fresh eligibility, dismissal, links, CMP suspension, late hide, expiry and network failures.');
  } finally {await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
