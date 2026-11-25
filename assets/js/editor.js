(() => {
  'use strict';
  const trigger = document.getElementById('zzznm-trigger');
  if (!trigger) return;
  const location = document.getElementById('zzznm-location');
  function update() {
    const manual = trigger.value === 'manual';
    document.querySelectorAll('[data-zzznm-auto]').forEach(el => el.hidden = manual);
    document.querySelectorAll('[data-zzznm-manual]').forEach(el => el.hidden = !manual);
    document.querySelectorAll('[data-zzznm-selected]').forEach(el => el.hidden = location.value !== 'selected');
    const editor = window.tinymce?.get('content');
    const value = editor && !editor.isHidden() ? editor.getContent() : document.getElementById('content')?.value || '';
    document.getElementById('zzznm-video-options').hidden = !/(?:\[video(?:\s|\])|<video(?:\s|>))/i.test(value);
  }
  document.addEventListener('input', update);
  document.addEventListener('change', update);
  // TinyMCE and the text editor can be switched or initialized after this script.
  setInterval(update, 500);
  update();
})();
