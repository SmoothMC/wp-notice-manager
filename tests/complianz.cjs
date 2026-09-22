// Browser integration contract tests using simulated Complianz and Elementor APIs.
const { chromium } = require('playwright');
const path = require('node:path');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '..');
(async () => {
  const browser = await chromium.launch({ headless: true, executablePath: process.env.CHROME_PATH || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' });
  try {
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', e => errors.push(e.message));
    let data, sequence = 0, requests = 0;
    await page.route('https://notices.test/**', async route => {
      if (route.request().url().endsWith('/ajax')) {
        requests++;
        const now = Math.floor(Date.now() / 1000);
        await route.fulfill({ json: { success: true, data: { ...data, now, next: now + 60,
          popup: data.popup && data.popup.until > now ? data.popup : null } } });
      } else await route.fulfill({ contentType: 'text/html', body: '<html><body><section data-zzznm-ticker hidden></section></body></html>' });
    });
    async function load({ mode = 'show', elementor = false, template = 0, delay = 0, complianzDelay = 300 } = {}) {
      data = { settings: { enabled: 1, delay, complianz_delay: complianzDelay, dismiss: 'content', ticker_mode: 'rotate', interval: 2, speed: 45 },
        template, popup: { id: ++sequence, key: `cmp-${sequence}`, heading: 'Hinweis', html: '<p>Text</p>', columns: 1, until: Math.floor(Date.now() / 1000) + 3600 },
        tickers: [{ id: 1, html: '<p>Immer sichtbar</p>' }, { id: 2, html: '<p>Weitere Meldung</p>' }] };
      await page.goto('https://notices.test/');
      await page.addStyleTag({ path: path.join(root, 'assets/css/frontend.css') });
      await page.addStyleTag({ content: '.cmplz-cookiebanner { position:fixed; bottom:0; background:white; padding:20px; } .cmplz-dismissed { display:none; }' });
      await page.evaluate(({ mode, elementor }) => {
        window.ZZZNM = { endpoint: 'https://notices.test/ajax', complianzExpected: true, close: 'Schließen', pause: 'Pause', play: 'Fortsetzen', next: 'Weiter' };
        window.complianz = { consenttype: 'optin' };
        window.cmpStatus = mode === 'dismissed' ? 'dismissed' : '';
        if (mode !== 'delayed') window.cmplz_get_banner_status = () => window.cmpStatus;
        if (mode === 'disabled') window.complianz.disable_cookiebanner = true;
        if (mode === 'region') window.complianz.consenttype = 'other';
        if (mode === 'show' || mode === 'dismissed') {
          const banner = document.createElement('div');
          banner.className = `cmplz-cookiebanner cmplz-${mode === 'show' ? 'show' : 'dismissed'}`;
          banner.textContent = 'Cookie-Einstellungen'; document.body.append(banner);
        }
        window.events = {};
        window.jQuery = () => ({ on(name, callback) { window.events[name.split('.')[0]] = callback; return this; } });
        window.opens = 0;
        if (elementor) window.elementorProFrontend = { modules: { popup: { showPopup({ id }) {
          window.opens++;
          const modal = document.createElement('div'); modal.id = `elementor-popup-modal-${id}`;
          modal.innerHTML = '<button class="dialog-close-button">Schließen</button><div data-zzznm-popup="notice"></div>';
          modal.querySelector('button').onclick = () => { modal.remove(); window.events['elementor/popup/hide']?.({}, id); };
          document.body.append(modal); window.events['elementor/popup/show']?.({}, id);
        } } } };
      }, { mode, elementor });
      await page.addScriptTag({ path: path.join(root, 'assets/js/frontend.js') });
      await page.waitForTimeout(150);
    }
    const noPopup = async () => assert.equal(await page.locator('dialog, [id^="elementor-popup-modal-"]').count(), 0);
    async function dismiss({ event = true } = {}) {
      await page.evaluate(event => {
        window.cmpStatus = 'dismissed';
        document.querySelector('.cmplz-cookiebanner')?.setAttribute('class', 'cmplz-cookiebanner cmplz-dismissed');
        if (event) {
          for (let i = 0; i < 3; i++) document.dispatchEvent(new Event('cmplz_status_change'));
          document.dispatchEvent(new CustomEvent('cmplz_banner_status', { detail: 'dismissed' }));
        }
      }, event);
    }
    await load(); await noPopup();
    assert.equal(await page.locator('[data-zzznm-ticker]').isVisible(), true, 'Ticker is not blocked');
    await page.locator('[aria-label="Weiter"]').click();
    assert.equal(await page.locator('.zzznm-ticker-item:not([hidden])').textContent(), 'Weitere Meldung');
    await page.evaluate(() => document.dispatchEvent(new Event('cmplz_enable_category')));
    await page.waitForTimeout(400); await noPopup();
    await dismiss(); await page.waitForTimeout(100); await noPopup();
    await page.locator('dialog[open]').waitFor();
    assert.equal(await page.locator('dialog').count(), 1, 'Repeated events open only one popup');
    // Reopened preferences take precedence even if the getter still says dismissed.
    await page.evaluate(() => { document.querySelector('.cmplz-cookiebanner').className = 'cmplz-cookiebanner cmplz-show'; });
    await page.waitForTimeout(100); await noPopup();
    assert.equal(await page.evaluate(key => localStorage.getItem(key), data.popup.key), null, 'Suspension is not saved as dismissal');
    await dismiss({ event: false }); await page.locator('dialog[open]').waitFor();
    await page.keyboard.press('Escape');
    await page.evaluate(() => { document.querySelector('.cmplz-cookiebanner').className = 'cmplz-cookiebanner cmplz-show'; });
    await page.waitForTimeout(50); await dismiss(); await page.waitForTimeout(500); await noPopup();
    await load({ mode: 'dismissed' }); await page.locator('dialog[open]').waitFor();
    await page.evaluate(() => {
      const container = document.createElement('div');
      container.className = 'cmplz-soft-cookiewall cmplz-dismissed';
      container.style.cssText = 'display:block;position:fixed;inset:0;pointer-events:none';
      document.body.append(container);
    });
    await page.waitForTimeout(100);
    assert.equal(await page.locator('dialog[open]').count(), 1, 'Inactive cookie-wall container does not block');
    await load({ mode: 'delayed' }); await page.waitForTimeout(5200); await noPopup();
    await page.evaluate(() => { window.cmplz_get_banner_status = () => window.cmpStatus; });
    await page.waitForTimeout(300); await noPopup();
    await dismiss({ event: false }); await page.locator('dialog[open]').waitFor();
    // Denial releases the popup too; no marketing-consent check exists.
    await load(); await page.evaluate(() => { window.cmplz_has_consent = () => false; });
    await dismiss(); await page.locator('dialog[open]').waitFor();
    await load(); data.popup.until = Math.floor(Date.now() / 1000) - 1;
    const before = requests; await dismiss(); await page.waitForTimeout(600); await noPopup();
    assert.ok(requests > before, 'Release reloads current schedule');
    await load(); data.popup = { ...data.popup, id: 999, key: 'new-schedule', heading: 'Neuer Hinweis' };
    await dismiss(); await page.locator('dialog[open]').waitFor();
    assert.equal(await page.locator('dialog h2').textContent(), 'Neuer Hinweis');
    await load({ elementor: true, template: 100 });
    assert.equal(await page.evaluate(() => window.opens), 0, 'Elementor never opens behind banner');
    await dismiss(); await page.locator('#elementor-popup-modal-100').waitFor();
    assert.equal(await page.evaluate(() => window.opens), 1);
    await page.evaluate(() => { document.querySelector('.cmplz-cookiebanner').className = 'cmplz-cookiebanner cmplz-show'; });
    await page.waitForTimeout(100); await noPopup();
    assert.equal(await page.evaluate(key => localStorage.getItem(key), data.popup.key), null);
    await dismiss(); await page.locator('#elementor-popup-modal-100').waitFor();
    await page.locator('.dialog-close-button').click();
    assert.equal(await page.evaluate(key => localStorage.getItem(key), data.popup.key), '1');
    await load({ template: 100 }); await page.waitForTimeout(600); await noPopup();
    await dismiss(); await page.locator('dialog[open]').waitFor({ timeout: 8000 });
    await load({ mode: 'dismissed', delay: 500 });
    await page.evaluate(() => { document.querySelector('.cmplz-cookiebanner').className = 'cmplz-cookiebanner cmplz-show'; });
    await page.waitForTimeout(700); await noPopup();
    await load({ mode: 'disabled' }); await page.locator('dialog[open]').waitFor();
    await load({ complianzDelay: 1200 });
    await dismiss(); await page.waitForTimeout(600); await noPopup();
    await page.locator('dialog[open]').waitFor();
    await load({ complianzDelay: 0 }); await noPopup();
    await dismiss(); await page.locator('dialog[open]').waitFor();
    await load({ mode: 'region' }); await page.locator('dialog[open]').waitFor();
    await load({ mode: 'delayed' });
    await page.evaluate(() => {
      window.complianz.do_not_track_enabled = true;
      Object.defineProperty(navigator, 'doNotTrack', { configurable: true, value: '1' });
      document.dispatchEvent(new Event('cmplz_cookie_banner_data'));
    });
    await page.locator('dialog[open]').waitFor();
    assert.deepEqual(errors, []);
    console.log('PASS: Complianz blocks only popups; release/cooldown, acceptance/denial, repeated events, reopening, dismissal persistence, delayed API, eventless fallback, expiry/replacement, Elementor, standalone fallback, delayed opening race, disabled banner and other region.');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exit(1); });
