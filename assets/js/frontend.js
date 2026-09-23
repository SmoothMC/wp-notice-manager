(() => {
  'use strict';
  if (!window.ZZZNM || window.ZZZNM.preview) return;
  const config = window.ZZZNM;
  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
  let state, current = null, dialog = null, timer, showTimer, expiryTimer, refreshing = false;
  let serverOffset = 0;
  const seen = new Set();
  const tickerInstances = new Map();
  let complianzHeld = false, complianzPoll, complianzRelease;
  const bannerSelector = '.cmplz-cookiebanner, .cmplz-soft-cookiewall.cmplz-show';
  function complianzBlocks() {
    const banners = [...document.querySelectorAll(bannerSelector)];
    const detected = config.complianzExpected || typeof window.cmplz_get_banner_status === 'function'
      || window.complianz || banners.length || document.getElementById('cmplz-cookiebanner-js');
    if (!detected) return false;
    // A re-opened preferences banner must take precedence over a saved cookie.
    const visible = banners.some(banner => {
      const style = getComputedStyle(banner);
      if (banner.hidden || style.display === 'none' || style.visibility === 'hidden') return false;
      if (banner.classList.contains('cmplz-show') && !banner.classList.contains('cmplz-hidden')) return true;
      const box = banner.getBoundingClientRect();
      return Number(style.opacity) > 0 && box.width > 0 && box.height > 0
        && box.bottom > 0 && box.right > 0 && box.top < innerHeight && box.left < innerWidth;
    });
    if (visible) return true;
    const cmp = window.complianz;
    // Complianz explicitly suppresses the banner on some pages/regions and
    // for respected DNT/GPC signals. These visitors need no banner interaction.
    if (cmp) {
      if ([true, 1, '1'].includes(cmp.disable_cookiebanner)) return false;
      if (cmp.consenttype && !['optin', 'optout'].includes(cmp.consenttype)) return false;
      if (cmp.do_not_track_enabled && (navigator.doNotTrack === '1' || navigator.globalPrivacyControl === true)) return false;
    }
    if (typeof window.cmplz_get_banner_status !== 'function') return true;
    try {
      if (window.cmplz_get_banner_status() === 'dismissed') return false;
      // The cookie-policy page can temporarily dismiss the banner without
      // storing consent. Restrict this fallback to the actual policy controls.
      if (document.querySelector('#cmplz-manage-consent-container, .cmplz-dropdown-cookiepolicy')
        && banners.some(banner => banner.matches('.cmplz-cookiebanner.cmplz-dismissed'))) return false;
    } catch (_) { /* Wait when the CMP is not initialized yet. */ }
    return true;
  }
  function updateComplianz() {
    if (complianzBlocks()) {
      complianzHeld = true;
      clearTimeout(complianzRelease); complianzRelease = null;
      if (current) closeCurrent(); // Suspension is not a visitor dismissal.
      if (!complianzPoll) complianzPoll = setTimeout(() => {
        complianzPoll = null; updateComplianz();
      }, 250);
      return;
    }
    clearTimeout(complianzPoll); complianzPoll = null;
    if (!complianzHeld || complianzRelease) return;
    complianzRelease = setTimeout(() => {
      complianzRelease = null;
      if (complianzBlocks()) { updateComplianz(); return; }
      complianzHeld = false;
      // Fetch again: the waiting popup might have expired or been replaced.
      refresh();
    }, Math.max(0, Math.min(60000, Number(state?.settings.complianz_delay ?? config.complianzDelay ?? 300))));
  }
  const now = () => (Date.now() + serverOffset) / 1000;
  const storage = {
    has(key) { try { return localStorage.getItem(key) === '1'; } catch (_) { return false; } },
    set(key) { try { localStorage.setItem(key, '1'); } catch (_) { /* Private browsing: session only. */ } }
  };
  function element(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
  }
  function content(post, part = 'notice') {
    const wrap = document.createDocumentFragment();
    const buttonURL = safeButtonURL(post.button_url);
    if (part === 'button_text') { wrap.append(document.createTextNode(post.button_text || '')); return wrap; }
    if (part === 'button') {
      if (post.button_text && buttonURL) {
        const button = element('a', 'zzznm-button', post.button_text);
        button.href = buttonURL; wrap.append(button);
      }
      return wrap;
    }
    if (part !== 'text') wrap.append(element(part === 'heading' ? 'span' : 'h2', 'zzznm-heading', post.heading));
    if (part !== 'heading') {
      const body = element('div', 'zzznm-text praxis-popup-text-columns');
      body.style.setProperty('--zzznm-columns', post.columns);
      body.innerHTML = post.html; // Sanitized with wp_kses_post by the server.
      wrap.append(body);
    }
    if (part === 'notice') wrap.append(content(post, 'button'));
    return wrap;
  }
  function safeButtonURL(value) {
    if (!value || typeof value !== 'string') return '';
    try {
      const url = new URL(value, location.href);
      return ['http:', 'https:', 'mailto:', 'tel:'].includes(url.protocol) ? value : '';
    } catch (_) { return ''; }
  }
  function fillSlots() {
    document.querySelectorAll('[data-zzznm-popup]').forEach(slot => {
      const post = state?.popup;
      const signature = post ? JSON.stringify([post.key, post.heading, post.html, post.columns, post.button_text, post.button_url]) : '';
      if (slot.dataset.zzznmSignature === signature) return;
      slot.dataset.zzznmSignature = signature;
      slot.replaceChildren(...(post && now() < post.until ? [content(post, slot.dataset.zzznmPopup)] : []));
    });
    const post = state?.popup;
    const url = post && now() < post.until ? safeButtonURL(post.button_url) : '';
    document.querySelectorAll('a[href="#notice-popup-link"], a[data-zzznm-link]').forEach(link => {
      link.dataset.zzznmLink = '1';
      const autoText = link.matches('.zzznm-popup-button') || link.closest('.zzznm-popup-button');
      const visible = Boolean(url && (!autoText || post?.button_text));
      if (link.hidden === visible) link.hidden = !visible;
      if (visible) {
        if (link.getAttribute('href') !== url) link.setAttribute('href', url);
        if (autoText && link.textContent !== post.button_text) link.textContent = post.button_text;
      } else if (link.hasAttribute('href')) link.removeAttribute('href');
    });
  }
  function remember(post) {
    seen.add(post.key);
    if ((post.dismiss || 'content') !== 'always') storage.set(post.key);
  }
  function closeCurrent() {
    clearTimeout(showTimer);
    clearTimeout(expiryTimer);
    const old = current;
    current = null;
    if (dialog?.querySelector('.zzznm-divi-content')) {
      document.getElementById('zzznm-divi-template')?.append(dialog.querySelector('.zzznm-divi-content'));
    }
    if (dialog) { dialog.close(); dialog.remove(); dialog = null; }
    if (old?.template) {
      const api = window.elementorProFrontend?.modules?.popup;
      // Close only the template managed by this plugin.
      const modal = document.getElementById(`elementor-popup-modal-${old.template}`);
      const close = modal?.querySelector('.dialog-close-button, .dialog-lightbox-close-button');
      if (close) close.click();
      else if (modal && api?.closePopup) api.closePopup({ id: old.template }, { target: modal });
    }
  }
  function standalone(post) {
    updateComplianz();
    if (complianzHeld || !current || current.key !== post.key || now() >= post.until) return;
    dialog = element('dialog', 'zzznm-dialog');
    dialog.setAttribute('aria-label', post.heading || 'Website-Hinweis');
    const close = element('button', 'zzznm-close', '×');
    close.type = 'button';
    close.setAttribute('aria-label', config.close);
    const inner = element('div', 'zzznm-dialog-content');
    const divi = document.getElementById('zzznm-divi-template');
    const layout = divi && Number(divi.dataset.templateId) === Number(state.divi_template) ? divi.querySelector('.zzznm-divi-content') : null;
    inner.append(layout || content(post));
    if (layout) dialog.classList.add('zzznm-dialog-divi');
    dialog.append(close, inner);
    document.body.append(dialog);
    const dismiss = () => { remember(post); closeCurrent(); };
    close.addEventListener('click', dismiss);
    dialog.addEventListener('cancel', event => { event.preventDefault(); dismiss(); });
    dialog.addEventListener('click', event => {
      if (event.target !== dialog) return;
      const box = dialog.getBoundingClientRect();
      if (event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom) dismiss();
    });
    fillSlots();
    dialog.showModal();
    if (layout) window.dispatchEvent(new Event('resize'));
    close.focus();
  }
  function show(post, template, attempt = 0) {
    updateComplianz();
    if (complianzHeld) return;
    if (!current || current.key !== post.key || now() >= post.until) return;
    const api = window.elementorProFrontend?.modules?.popup;
    if (template && !api?.showPopup && attempt < 20) {
      showTimer = setTimeout(() => show(post, template, attempt + 1), 250);
      return;
    }
    if (template && api?.showPopup) {
      try {
        bindElementor();
        current.template = template;
        api.showPopup({ id: template });
        if (!current) return;
        fillSlots();
        // Some cached pages do not contain the selected template. Fall back once.
        showTimer = setTimeout(() => {
          if (!current || current.key !== post.key) return;
          const modal = document.getElementById(`elementor-popup-modal-${template}`);
          if (!modal || !modal.getClientRects().length) { current.template = 0; standalone(post); }
        }, 1000);
        return;
      } catch (_) { /* Elementor unavailable: use standalone. */ }
    }
    standalone(post);
  }
  function syncPopup() {
    const post = state.popup;
    fillSlots();
    updateComplianz();
    if (complianzHeld) return;
    if (!post || now() >= post.until) { closeCurrent(); return; }
    if (current?.key === post.key) {
      if (dialog && !dialog.classList.contains('zzznm-dialog-divi')) dialog.querySelector('.zzznm-dialog-content').replaceChildren(content(post));
      return;
    }
    closeCurrent();
    if (seen.has(post.key) || ((post.dismiss || 'content') !== 'always' && storage.has(post.key))) return;
    current = { key: post.key, post, template: 0 };
    showTimer = setTimeout(() => show(post, state.template), state.settings.delay);
    const expire = () => {
      if (now() < post.until) {
        expiryTimer = setTimeout(expire, Math.min(2147483647, Math.max(1, (post.until - now()) * 1000)));
        return;
      }
      closeCurrent();
      if (state?.popup?.key === post.key) state.popup = null;
      fillSlots();
      refresh();
    };
    expiryTimer = setTimeout(expire, Math.min(2147483647, Math.max(0, (post.until - now()) * 1000)));
  }
  function bindElementor() {
    if (!window.jQuery || bindElementor.bound) return;
    bindElementor.bound = true;
    window.jQuery(document).on('elementor/popup/show.zzznm', (_event, id) => {
      if (Number(id) !== state?.template) return;
      updateComplianz();
      fillSlots();
      // A late Elementor response must not overlap the standalone fallback.
      if (complianzHeld || dialog || !current || now() >= current.post.until) {
        document.getElementById(`elementor-popup-modal-${id}`)?.querySelector('.dialog-close-button, .dialog-lightbox-close-button')?.click();
      }
    }).on('elementor/popup/hide.zzznm', (_event, id) => {
      if (current?.template && Number(id) === current.template) {
        remember(current.post);
        current = null;
        clearTimeout(showTimer);
        clearTimeout(expiryTimer);
      }
    });
  }
  function buildTicker(root, items, options) {
    const old = tickerInstances.get(root);
    old?.destroy();
    root.replaceChildren();
    root.hidden = items.length === 0;
    if (!items.length) return;
    const viewport = element('div', 'zzznm-ticker-viewport');
    const track = element('div', 'zzznm-ticker-track');
    const nodes = items.map(item => {
      const node = element('div', 'zzznm-ticker-item');
      if (item.title) {
        node.append(element('span', 'zzznm-ticker-title', item.title));
        const separator = element('span', 'zzznm-ticker-separator', '•');
        separator.setAttribute('aria-hidden', 'true');
        node.append(separator);
      }
      const body = element('span', 'zzznm-ticker-body');
      body.innerHTML = item.html;
      node.append(body);
      track.append(node);
      return node;
    });
    viewport.append(track);
    const controls = element('div', 'zzznm-ticker-controls');
    const pause = element('button', '', config.pause);
    pause.type = 'button'; pause.setAttribute('aria-pressed', 'false');
    const next = element('button', '', '→');
    next.type = 'button'; next.setAttribute('aria-label', config.next);
    controls.append(pause);
    root.append(viewport, controls);
    let index = 0, interval, animation, paused = reduced.matches, hover = false, focus = false;
    const isPaused = () => paused || hover || focus || document.hidden;
    const updatePause = () => {
      pause.textContent = paused ? config.play : config.pause;
      pause.setAttribute('aria-pressed', String(paused));
      if (animation) { if (isPaused()) animation.pause(); else animation.play(); }
    };
    const rotate = () => {
      index = (index + 1) % nodes.length;
      nodes.forEach((node, i) => { node.hidden = i !== index; });
    };
    const marquee = options.mode === 'marquee' && !reduced.matches;
    root.dataset.presentation = marquee ? 'marquee' : 'rotate';
    let resize;
    if (marquee) {
      const group = element('div', 'zzznm-ticker-group');
      nodes.forEach(node => {
        group.append(node);
        const separator = element('span', 'zzznm-ticker-separator', '•');
        separator.setAttribute('aria-hidden', 'true');
        group.append(separator);
      });
      track.replaceChildren(group);
      let lastWidth = 0, lastViewport = 0;
      const run = () => {
        const distance = group.getBoundingClientRect().width;
        const width = viewport.clientWidth;
        if (!distance || !width || (distance === lastWidth && width === lastViewport)) return;
        lastWidth = distance; lastViewport = width;
        animation?.cancel();
        while (track.children.length > 1) track.lastElementChild.remove();
        // Enough identical cycles cover even a wide viewport with a single short message.
        const copies = Math.ceil(width / distance) + 1;
        for (let i = 0; i < copies; i++) {
          const copy = group.cloneNode(true);
          copy.setAttribute('aria-hidden', 'true');
          copy.querySelectorAll('[id]').forEach(node => node.removeAttribute('id'));
          copy.querySelectorAll('a, button, input, select, textarea, [tabindex]').forEach(node => node.setAttribute('tabindex', '-1'));
          track.append(copy);
        }
        animation = track.animate([
          { transform: 'translateX(0)' },
          { transform: `translateX(-${distance}px)` }
        ], { duration: distance / options.speed * 1000, iterations: Infinity, easing: 'linear' });
        updatePause();
      };
      resize = new ResizeObserver(run);
      resize.observe(viewport);
      resize.observe(group);
      run();
    } else {
      nodes.forEach((node, i) => { node.hidden = i !== 0; });
      if (nodes.length > 1) {
        controls.append(next);
        next.addEventListener('click', rotate);
        interval = setInterval(() => { if (!isPaused()) rotate(); }, options.interval * 1000);
      } else controls.hidden = true;
    }
    if (!options.controls) {
      controls.hidden = true;
      // Keep every message readable when reduced motion prevents automatic rotation.
      if (reduced.matches) nodes.forEach(node => { node.hidden = false; });
    }
    pause.addEventListener('click', () => { paused = !paused; updatePause(); });
    const enter = () => { hover = true; updatePause(); };
    const leave = () => { hover = false; updatePause(); };
    const focusIn = () => { focus = true; updatePause(); };
    const focusOut = event => { focus = root.contains(event.relatedTarget); updatePause(); };
    root.addEventListener('mouseenter', enter);
    root.addEventListener('mouseleave', leave);
    root.addEventListener('focusin', focusIn);
    root.addEventListener('focusout', focusOut);
    document.addEventListener('visibilitychange', updatePause);
    updatePause();
    tickerInstances.set(root, { destroy() {
      clearInterval(interval); animation?.cancel(); resize?.disconnect();
      root.removeEventListener('mouseenter', enter); root.removeEventListener('mouseleave', leave);
      root.removeEventListener('focusin', focusIn); root.removeEventListener('focusout', focusOut);
      document.removeEventListener('visibilitychange', updatePause);
    } });
  }
  function syncTickers(force = false) {
    for (const [root, instance] of tickerInstances) {
      if (!root.isConnected) { instance.destroy(); tickerInstances.delete(root); }
    }
    document.querySelectorAll('[data-zzznm-ticker]').forEach(root => {
      const options = {
        controls: state.settings.ticker_controls !== 0 && state.settings.ticker_controls !== '0',
        mode: root.dataset.mode || state.settings.ticker_mode,
        interval: Number(root.dataset.interval || state.settings.interval),
        speed: Number(root.dataset.speed || state.settings.speed)
      };
      const signature = JSON.stringify([state.tickers, options]);
      if (force || root.dataset.signature !== signature) {
        root.dataset.signature = signature;
        buildTicker(root, state.tickers, options);
      }
    });
  }
  async function refresh() {
    if (refreshing) return;
    refreshing = true;
    clearTimeout(timer);
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 10000);
    let next = 30000;
    try {
      const response = await fetch(config.endpoint, { method: 'POST', credentials: 'same-origin',
        cache: 'no-store', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=zzznm_state', signal: controller.signal });
      if (!response.ok) throw new Error('Unable to load notices');
      const payload = await response.json();
      if (!payload.success) throw new Error('Invalid notice response');
      state = payload.data;
      serverOffset = state.now * 1000 - Date.now();
      bindElementor(); syncPopup(); syncTickers();
      next = Math.max(1000, Math.min(60000, (state.next - state.now) * 1000));
    } catch (_) {
      // Fail closed: stale notices must not remain visible after a network failure.
      closeCurrent();
      if (state) { state.popup = null; state.tickers = []; fillSlots(); syncTickers(); }
    } finally {
      clearTimeout(timeout); refreshing = false; timer = setTimeout(refresh, next);
    }
  }
  function start() {
    ['cmplz_before_cookiebanner', 'cmplz_cookie_banner_data', 'cmplz_cookie_warning_loaded',
      'cmplz_banner_status', 'cmplz_status_change', 'cmplz_enable_category', 'cmplz_revoke']
      .forEach(name => document.addEventListener(name, updateComplianz));
    updateComplianz();
    refresh();
    const observer = new MutationObserver(records => {
      updateComplianz();
      if (state && records.some(record => record.type === 'childList')) { fillSlots(); syncTickers(); }
    });
    observer.observe(document.documentElement, { childList: true, subtree: true,
      attributes: true, attributeFilter: ['class', 'style', 'hidden', 'aria-hidden'] });
    reduced.addEventListener('change', () => { if (state) syncTickers(true); });
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, { once: true });
  else start();
})();
