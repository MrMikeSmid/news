(function () {
  document.querySelectorAll('form[data-loading]').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      var button = event.submitter;
      if (!button || form.dataset.busy === '1') {
        if (form.dataset.busy === '1') event.preventDefault();
        return;
      }
      form.dataset.busy = '1';
      button.setAttribute('aria-busy', 'true');
      // Bewaar de keuze van de aangeklikte knop (bijvoorbeeld OAuth-toestemming).
      if (button.name) {
        var chosen = document.createElement('input');
        chosen.type = 'hidden'; chosen.name = button.name; chosen.value = button.value;
        form.appendChild(chosen);
        button.removeAttribute('name');
      }
      var spinner = document.createElement('span');
      spinner.className = 'spinner'; spinner.setAttribute('aria-hidden', 'true');
      button.replaceChildren(spinner, document.createTextNode(' Even wachten…'));
      button.disabled = true;
    });
  });
  window.addEventListener('pageshow', function (event) {
    if (event.persisted) window.location.reload();
  });
})();
