(function () {
  var toggle = document.getElementById('menu-toggle');
  var sidebar = document.getElementById('sidebar');
  var backdrop = document.getElementById('backdrop');

  function closeMenu() {
    sidebar && sidebar.classList.remove('open');
    backdrop && backdrop.classList.remove('open');
  }
  if (toggle && sidebar) {
    toggle.addEventListener('click', function () {
      sidebar.classList.toggle('open');
      backdrop && backdrop.classList.toggle('open');
    });
  }
  backdrop && backdrop.addEventListener('click', closeMenu);

  document.querySelectorAll('[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      if (!window.confirm(form.getAttribute('data-confirm'))) event.preventDefault();
    });
  });

  var form = document.getElementById('post-form');
  if (!form) return;

  var master = {
    title: form.querySelector('[name=title]'),
    body: form.querySelector('[name=body]'),
    link: form.querySelector('[name=link]'),
    image: form.querySelector('[name=image]'),
    remove: form.querySelector('#remove-image')
  };
  var hadImage = !!form.querySelector('.post-media');

  function hasImage() {
    if (master.image.files && master.image.files.length) return true;
    return hadImage && !(master.remove && master.remove.checked);
  }

  function compose(card, text) {
    var link = master.link.value.trim();
    text = text.trim();
    if (link && card.dataset.link === 'inline') text = (text + '\n\n' + link).trim();
    return text;
  }

  function length(card, text) {
    var urlLen = parseInt(card.dataset.urlLen, 10);
    if (urlLen) text = text.replace(/https?:\/\/\S+/gi, new Array(urlLen + 1).join('x'));
    return Array.from(text).length;
  }

  function update(card) {
    if (card.hidden) return;
    var field = function (n) { return card.querySelector('[data-field=' + n + ']'); };
    var titleEl = field('title'), bodyEl = field('body'), subEl = field('subreddit');
    var title = (titleEl && titleEl.value.trim()) || master.title.value.trim();
    var body = bodyEl.value.trim() || master.body.value.trim();
    var text = compose(card, body);
    var len = length(card, text);
    var max = parseInt(card.dataset.max, 10);
    var issues = [];

    if (!text && !hasImage()) issues.push('Tekst is verplicht.');
    if (len > max) issues.push('Tekst is ' + (len - max) + ' tekens te lang.');
    if (card.dataset.title === 'required' && !title) issues.push('Titel is verplicht.');
    var tmax = parseInt(card.dataset.titleMax, 10);
    if (tmax && title.length > tmax) issues.push('Titel is te lang (max ' + tmax + ').');
    if (card.dataset.media === 'required' && !hasImage()) issues.push('Een afbeelding is verplicht.');
    if (card.dataset.subreddit === '1' && !/^(\/?r\/)?[A-Za-z0-9_]{2,21}$/.test(subEl.value.trim())) issues.push('Geef een geldige subreddit op.');
    var hmax = parseInt(card.dataset.hashtagMax, 10);
    if (hmax && (text.match(/#\w+/g) || []).length > hmax) issues.push('Maximaal ' + hmax + ' hashtags.');

    var counter = card.querySelector('[data-counter]');
    counter.textContent = len + ' / ' + max;
    counter.classList.toggle('over', len > max);
    counter.classList.toggle('near', len <= max && len > max * 0.9);
    var list = card.querySelector('[data-issues]');
    list.innerHTML = '';
    issues.forEach(function (msg) {
      var li = document.createElement('li');
      li.textContent = msg;
      list.appendChild(li);
    });
    card.dataset.text = text;
  }

  var cards = Array.prototype.slice.call(form.querySelectorAll('.channel-card'));
  function updateAll() { cards.forEach(update); }

  form.querySelectorAll('.channel-toggle input').forEach(function (box) {
    function sync() {
      var card = document.getElementById(box.dataset.target);
      card.hidden = !box.checked;
      box.parentNode.classList.toggle('on', box.checked);
      update(card);
    }
    box.addEventListener('change', sync);
    sync();
  });

  form.addEventListener('input', updateAll);
  form.addEventListener('change', updateAll);

  form.querySelectorAll('[data-copy]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var card = btn.closest('.channel-card');
      update(card);
      if (navigator.clipboard) navigator.clipboard.writeText(card.dataset.text || '');
      btn.lastChild.textContent = ' Gekopieerd';
    });
  });
})();
