(function () {
  document.querySelectorAll('[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      if (!window.confirm(form.getAttribute('data-confirm'))) event.preventDefault();
    });
  });

  document.querySelectorAll('[data-copy]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var target = document.getElementById(btn.getAttribute('data-copy'));
      if (!target) return;
      var text = target.value;
      function done() { var old = btn.textContent; btn.textContent = 'Gekopieerd'; setTimeout(function () { btn.textContent = old; }, 1500); }
      if (navigator.clipboard) { navigator.clipboard.writeText(text).then(done); }
      else { target.select(); document.execCommand('copy'); done(); }
    });
  });

  document.querySelectorAll('form[data-busy]').forEach(function (form) {
    form.addEventListener('submit', function () {
      var btn = form.querySelector('button[type=submit]');
      if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner"></span> ' + (form.getAttribute('data-busy') || 'Bezig…'); }
    });
  });

  if ('serviceWorker' in navigator) {
    try { navigator.serviceWorker.register((window.APP_BASE || '') + '/sw.js'); } catch (e) {}
  }
})();

(function () {
  var presets = {
    icloud: { label: 'iCloud', protocol: 'imap', host: 'imap.mail.me.com', port: 993, security: 'ssl', smtp_host: 'smtp.mail.me.com', smtp_port: 587, smtp_security: 'starttls' }
  };
  document.querySelectorAll('[data-preset]').forEach(function (select) {
    select.addEventListener('change', function () {
      var p = presets[select.value];
      if (!p) return;
      var form = select.closest('form');
      Object.keys(p).forEach(function (name) {
        var field = form.querySelector('[name="' + name + '"]');
        if (field && !(name === 'label' && field.value)) field.value = p[name];
      });
    });
  });
})();
