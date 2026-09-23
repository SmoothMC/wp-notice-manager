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
  let data, lastRequest;
  const fresh = () => {
    const now = Math.floor(Date.now() / 1000);
    return { now, next: now + 60, template: 0,
      settings: { enabled: 1, delay: 0, dismiss: 'content', ticker_mode: 'rotate', interval: 2, speed: 45 },
      popup: { id: 1, key: 'test-one', heading: 'Urlaub', html: '<p>Ab Montag wieder geöffnet.</p>', columns: 2, until: now + 3600 },
      tickers: [{ id: 2, html: '<p>Erste Meldung</p>' }, { id: 3, html: '<p>Zweite Meldung</p>' }] };
  };
  await page.route('https://notices.test/**', async route => {
    if (route.request().url().endsWith('/ajax')) {
      lastRequest = new URLSearchParams(route.request().postData());
      const now = Math.floor(Date.now() / 1000);
      await route.fulfill({ json: { success: true, data: { ...data, now, next: now + 60,
        popup: data.popup && data.popup.until > now ? data.popup : null } } });
    }
    else await route.fulfill({ contentType: 'text/html', body: '<!doctype html><html lang="de"><title>Notice Manager Test</title><body><button id="before">Kontakt</button><section data-zzznm-ticker class="zzznm-ticker" hidden></section><div data-zzznm-popup="heading"></div></body></html>' });
  });
  async function load({ reduced = false, elementor = false, divi = false, adminPreview = false } = {}) {
    await page.emulateMedia({ reducedMotion: reduced ? 'reduce' : 'no-preference' });
    await page.goto('https://notices.test/');
    await page.addStyleTag({ path: path.join(root, 'assets/css/frontend.css') });
    await page.evaluate(({ elementor, adminPreview }) => {
      window.ZZZNM = { pageContext: { front: false, page: true, id: 42 }, endpoint: 'https://notices.test/ajax', close: 'Schließen', pause: 'Pause', play: 'Fortsetzen', next: 'Nächste Meldung' };
      if (adminPreview) {
        Object.assign(window.ZZZNM, { adminPreview: true, previewId: 12, previewNonce: 'test-nonce' });
        const banner = document.createElement('aside'); banner.innerHTML = '<button data-zzznm-preview-reopen>Erneut öffnen</button><span data-zzznm-preview-error></span>'; document.body.append(banner);
        window.cmplz_get_banner_status = () => 'not-dismissed';
        localStorage.setItem('preview-key', '1');
      }
      if (elementor) {
        window.elementorProFrontend = { modules: { popup: { showPopup({ id }) {
          const modal = document.createElement('div'); modal.id = `elementor-popup-modal-${id}`;
          modal.innerHTML = '<div data-zzznm-popup="notice"></div>'; document.body.append(modal);
        } } } };
      }
    }, { elementor, adminPreview });
    if (divi) await page.evaluate(() => { const source = document.createElement('div'); source.id = 'zzznm-divi-template'; source.hidden = true; source.dataset.templateId = '200'; source.innerHTML = '<div class="zzznm-divi-content"><h3 class="et_pb_module_header">Info: [notice_popup_heading]</h3><div data-zzznm-popup="notice"></div></div>'; document.body.append(source); });
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
  data.tickers = [{ id: 2, title: 'NEU', html: '<p>Ab 01.10.2026: Neue Sprechstunde. <a href="/info">Weitere Informationen</a></p>' }];
  await load();
  const loop = await page.evaluate(() => {
    const track = document.querySelector('.zzznm-ticker-track');
    const group = track.firstElementChild;
    const animation = track.getAnimations()[0];
    const distance = group.getBoundingClientRect().width;
    animation.pause(); animation.currentTime = animation.effect.getTiming().duration - 1;
    const viewport = track.parentElement.getBoundingClientRect();
    return { copies: track.children.length, distance, width: viewport.width,
      right: track.getBoundingClientRect().right, viewportRight: viewport.right,
      title: group.querySelector('.zzznm-ticker-title').textContent,
      separators: group.querySelectorAll('.zzznm-ticker-separator').length,
      accessibleLinks: track.querySelectorAll('a:not([tabindex="-1"])').length,
      start: animation.effect.getKeyframes()[0].transform };
  });
  assert.ok(loop.copies >= Math.ceil(loop.width / loop.distance) + 1);
  assert.ok(loop.right >= loop.viewportRight, 'No gap at end of loop');
  assert.equal(loop.title, 'NEU'); assert.equal(loop.separators, 2);
  assert.equal(loop.accessibleLinks, 1, 'Cloned links excluded from keyboard order');
  assert.equal(loop.start, 'translateX(0px)');
  await page.setViewportSize({ width: 1920, height: 800 });
  await page.waitForTimeout(100);
  assert.ok(await page.evaluate(() => document.querySelector('.zzznm-ticker-track').scrollWidth > document.querySelector('.zzznm-ticker-viewport').clientWidth + document.querySelector('.zzznm-ticker-group').getBoundingClientRect().width));
  await page.screenshot({ path: '/tmp/zzznm-ticker-1.0.4.png' });
  data = fresh(); data.popup = null; data.settings.ticker_mode = 'marquee';
  await load({ reduced: true });
  assert.equal(await page.locator('[data-presentation="rotate"]').count(), 1);
  assert.equal(await page.locator('button[aria-pressed]').textContent(), 'Fortsetzen');
  data.settings.ticker_controls = 0; await load({ reduced: true });
  assert.equal(await page.locator('.zzznm-ticker-controls').isVisible(), false);
  assert.equal(await page.locator('.zzznm-ticker-item:not([hidden])').count(), 2);
  await load();
  assert.equal(await page.locator('.zzznm-ticker-controls').isVisible(), false);
  assert.ok(await page.evaluate(() => document.querySelector('.zzznm-ticker-track').getAnimations().length));
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
  data = fresh(); data.popup.key = 'divi'; data.divi_template = 200;
  await load({ divi: true }); await page.locator('dialog[open]').waitFor();
  assert.equal(await page.locator('dialog .zzznm-divi-content h2').textContent(), 'Urlaub');
  assert.equal(await page.locator('dialog h3').textContent(), 'Info: Urlaub');
  assert.ok(await page.locator('dialog').evaluate(el => el.getBoundingClientRect().width <= 640));
  assert.equal(await page.locator('dialog').evaluate(el => getComputedStyle(el).padding), '0px');
  assert.equal(await page.locator('dialog .zzznm-dialog-content').evaluate(el => getComputedStyle(el).backgroundColor), 'rgba(0, 0, 0, 0)');
  await page.setViewportSize({ width: 375, height: 667 });
  assert.equal(await page.locator('dialog').evaluate(el => getComputedStyle(el).padding), '0px');
  await page.keyboard.press('Escape');
  assert.equal(await page.locator('#zzznm-divi-template .zzznm-divi-content').count(), 1);
  data.popup.dismiss = 'always'; await load({ divi: true });
  await page.locator('dialog[open]').waitFor();
  await page.keyboard.press('Escape');
  await load({ divi: true }); await page.locator('dialog[open]').waitFor();
  await page.keyboard.press('Escape');
  data.popup.key = 'button'; data.popup.button_text = 'Weitere Informationen'; data.popup.button_url = 'https://example.org/details';
  await load(); await page.locator('dialog[open]').waitFor();
  assert.equal(await page.locator('dialog .zzznm-button').getAttribute('href'), 'https://example.org/details');
  assert.equal(await page.locator('dialog .zzznm-button').textContent(), 'Weitere Informationen');
  await page.evaluate(() => {
    const native = document.createElement('div');
    native.innerHTML = '<div class="zzznm-popup-button"><a href="#notice-popup-link">Platzhalter</a></div><a id="image-link" href="#notice-popup-link"><img alt="Bild"></a><span data-zzznm-popup="button"></span>';
    document.body.append(native);
  });
  await page.waitForTimeout(100);
  assert.equal(await page.locator('.zzznm-popup-button a').textContent(), 'Weitere Informationen');
  assert.equal(await page.locator('#image-link').getAttribute('href'), 'https://example.org/details');
  assert.equal(await page.locator('#image-link img').count(), 1);
  assert.equal(await page.locator('[data-zzznm-popup="button"] a').getAttribute('href'), 'https://example.org/details');
  data.popup.button_url = 'javascript:alert(1)'; await load();
  assert.equal(await page.locator('.zzznm-button').count(), 0, 'Unsafe URLs rejected');
  data.popup.button_url = ''; await load();
  assert.equal(await page.locator('.zzznm-button').count(), 0, 'Empty URLs hide button');
  data = fresh(); data.popup.key = 'preview-key'; data.popup.dismiss = 'always';
  await load({ adminPreview: true }); await page.locator('dialog[open]').waitFor();
  assert.equal(await page.locator('dialog h2').textContent(), 'Urlaub', 'Preview ignores saved dismissal and CMP');
  await page.keyboard.press('Escape');
  assert.equal(await page.locator('dialog').count(), 0);
  await page.locator('[data-zzznm-preview-reopen]').click();
  await page.locator('dialog[open]').waitFor();
  assert.equal(await page.evaluate(() => localStorage.getItem('preview-key')), '1', 'Preview preserves visitor storage');
  data = fresh(); data.popup.key = 'fade-test'; data.settings.fade_enabled = 1; data.settings.fade_duration = 1200;
  await load(); await page.locator('dialog[open]').waitFor();
  assert.equal(JSON.parse(lastRequest.get('page_context')).id, 42, 'Page context is sent to server');
  assert.equal(await page.locator('dialog .zzznm-close-icon').textContent(), '×', 'User close-icon change retained');
  assert.equal(await page.locator('dialog').evaluate(el => el.getAnimations()[0].effect.getTiming().duration), 1200);
  await load({ reduced: true }); await page.locator('dialog[open]').waitFor();
  assert.equal(await page.locator('dialog').evaluate(el => el.getAnimations().length), 0, 'Reduced motion disables fade');
  data.settings.fade_enabled = 0; await load(); await page.locator('dialog[open]').waitFor();
  assert.equal(await page.locator('dialog').evaluate(el => el.getAnimations().length), 0, 'Fade off');
  data.settings.fade_enabled = 1; data.settings.fade_duration = 0; await load(); await page.locator('dialog[open]').waitFor();
  assert.equal(await page.locator('dialog').evaluate(el => el.getAnimations().length), 0, 'Zero duration');
  data.settings.fade_duration = 1200; data.template = 100;
  await load({ elementor: true });
  assert.equal(await page.locator('#elementor-popup-modal-100').evaluate(el => el.getAnimations()[0].effect.getTiming().duration), 1200, 'Elementor fade');
  assert.deepEqual(errors, []);
  await browser.close();
  console.log('PASS: standalone, Escape, persistent dismissal, changed content, rotation, marquee, reduced motion, empty ticker, expiry, Elementor adapter, fallback, mobile layout.');
})().catch(error => { console.error(error); process.exit(1); });
