(function () {
  var toggle = document.getElementById('menu-toggle');
  var sidebar = document.getElementById('sidebar');
  var backdrop = document.getElementById('backdrop');

  function setMenu(open) {
    if (!toggle || !sidebar) return;
    sidebar.classList.toggle('open', open);
    if (backdrop) backdrop.classList.toggle('open', open);
    toggle.setAttribute('aria-expanded', String(open));
  }

  if (toggle && sidebar) {
    toggle.addEventListener('click', function () {
      setMenu(!sidebar.classList.contains('open'));
    });
  }
  if (backdrop) backdrop.addEventListener('click', function () { setMenu(false); });
  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') setMenu(false);
  });
})();
