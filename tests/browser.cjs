// Run with Node and Playwright available through NODE_PATH.
const { chromium } = require('playwright');
const path = require('node:path');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '..');
(async () => {
  const browser = await chromium.launch({ headless: true, executablePath: process.env.CHROME_PATH || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' });
  const errors = [];
  const context = await browser.newContext();
  const page = await context.newPage();
  page.on('pageerror', e => errors.push(e.message));
  let data;
  const fresh = () => {
    const now = Math.floor(Date.now() / 1000);
    return { now, next: now + 60, template: 0,
      settings: { enabled: 1, delay: 0, dismiss: 'content', ticker_mode: 'rotate', interval: 2, speed: 45 },
      popup: { id: 1, key: 'test-one', heading: 'Urlaub', html: '<p>Ab Montag wieder geöffnet.</p>', columns: 2, until: now + 3600 },
      tickers: [{ id: 2, html: '<p>Erste Meldung</p>' }, { id: 3, html: '<p>Zweite Meldung</p>' }] };
  };
  await page.route('https://notices.test/**', async route => {
    if (route.request().url().endsWith('/ajax')) {
      const now = Math.floor(Date.now() / 1000);
      await route.fulfill({ json: { success: true, data: { ...data, now, next: now + 60,
        popup: data.popup && data.popup.until > now ? data.popup : null } } });
    }
    else await route.fulfill({ contentType: 'text/html', body: '<!doctype html><html lang="de"><title>Notice Manager Test</title><body><button id="before">Kontakt</button><section data-zzznm-ticker class="zzznm-ticker" hidden></section><div data-zzznm-popup="heading"></div></body></html>' });
  });
  async function load({ reduced = false, elementor = false } = {}) {
    await page.emulateMedia({ reducedMotion: reduced ? 'reduce' : 'no-preference' });
    await page.goto('https://notices.test/');
    await page.addStyleTag({ path: path.join(root, 'assets/css/frontend.css') });
    await page.evaluate(({ elementor }) => {
      window.ZZZNM = { endpoint: 'https://notices.test/ajax', close: 'Schließen', pause: 'Pause', play: 'Fortsetzen', next: 'Nächste Meldung' };
      if (elementor) {
        window.elementorProFrontend = { modules: { popup: { showPopup({ id }) {
          const modal = document.createElement('div'); modal.id = `elementor-popup-modal-${id}`;
          modal.innerHTML = '<div data-zzznm-popup="notice"></div>'; document.body.append(modal);
        } } } };
      }
    }, { elementor });
    await page.addScriptTag({ path: path.join(root, 'assets/js/frontend.js') });
    await page.waitForTimeout(250);
  }
  data = fresh(); await load();
  await page.locator('dialog[open]').waitFor();
  assert.equal(await page.locator('dialog h2').textContent(), 'Urlaub');
  await page.keyboard.press('Escape');
  assert.equal(await page.locator('dialog').count(), 0);
  assert.equal(await page.evaluate(() => localStorage.getItem('test-one')), '1');
  await page.locator('.zzznm-ticker button[aria-label="Nächste Meldung"]').click();
  assert.equal(await page.locator('.zzznm-ticker-item:not([hidden])').textContent(), 'Zweite Meldung');
  await load(); assert.equal(await page.locator('dialog').count(), 0, 'Dismissal persists');
  data.popup.key = 'test-two'; await load(); await page.locator('dialog[open]').waitFor();
  await page.keyboard.press('Escape');
  data.popup = null; data.settings.ticker_mode = 'marquee'; await load();
  assert.equal(await page.locator('[data-presentation="marquee"]').count(), 1);
  assert.ok(await page.evaluate(() => document.querySelector('.zzznm-ticker-track').getAnimations().length));
  await load({ reduced: true });
  assert.equal(await page.locator('[data-presentation="rotate"]').count(), 1);
  assert.equal(await page.locator('button[aria-pressed]').textContent(), 'Fortsetzen');
  data.tickers = []; await load();
  assert.equal(await page.locator('[data-zzznm-ticker]').isVisible(), false);
  data = fresh(); data.popup.key = 'expires'; data.popup.until = Math.floor(Date.now() / 1000) + 2;
  await load(); await page.locator('dialog[open]').waitFor();
  await page.waitForTimeout(2400);
  assert.equal(await page.locator('dialog').count(), 0, 'Expired popup closes');
  data = fresh(); data.popup.key = 'elementor'; data.template = 100;
  await load({ elementor: true });
  assert.equal(await page.locator('#elementor-popup-modal-100 h2').textContent(), 'Urlaub');
  assert.equal(await page.locator('dialog').count(), 0);
  data.popup.key = 'fallback'; await load();
  await page.locator('dialog[open]').waitFor({ timeout: 8000 });
  await page.setViewportSize({ width: 375, height: 667 });
  assert.ok(await page.evaluate(() => document.querySelector('dialog').getBoundingClientRect().width < innerWidth));
  assert.equal(await page.locator('dialog .zzznm-text').evaluate(el => getComputedStyle(el).columnCount), '1');
  await page.screenshot({ path: '/tmp/zzznm-mobile.png', fullPage: true });
  await page.setViewportSize({ width: 1280, height: 800 });
  await page.screenshot({ path: '/tmp/zzznm-desktop.png', fullPage: true });
  assert.deepEqual(errors, []);
  await browser.close();
  console.log('PASS: standalone, Escape, persistent dismissal, changed content, rotation, marquee, reduced motion, empty ticker, expiry, Elementor adapter, fallback, mobile layout.');
})().catch(error => { console.error(error); process.exit(1); });
