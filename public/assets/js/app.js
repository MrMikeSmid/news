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
  if (backdrop) {
    backdrop.addEventListener('click', closeMenu);
  }

  document.querySelectorAll('input[type=file]').forEach(function (input) {
    input.addEventListener('change', function () {
      var chip = input.closest('.field, .composer-attach')?.querySelector('.file-chip[data-for="' + input.name + '"]');
      if (chip) {
        chip.textContent = input.files && input.files[0] ? input.files[0].name : '';
      }
    });
  });

  document.querySelectorAll('[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      if (!window.confirm(form.getAttribute('data-confirm'))) {
        event.preventDefault();
      }
    });
  });

  var editor = document.getElementById('content-editor');
  var hiddenInput = document.getElementById('content-input');
  if (editor && hiddenInput) {
    editor.innerHTML = hiddenInput.value;

    document.querySelectorAll('.editor-toolbar [data-cmd]').forEach(function (button) {
      button.addEventListener('click', function (event) {
        event.preventDefault();
        var cmd = button.getAttribute('data-cmd');
        if (cmd === 'createLink') {
          var url = window.prompt('Link naar welke URL?', 'https://');
          if (!url) return;
          document.execCommand('createLink', false, url);
        } else {
          document.execCommand(cmd, false, null);
        }
        editor.focus();
      });
    });

    var syncContent = function () { hiddenInput.value = editor.innerHTML; };
    editor.addEventListener('input', syncContent);
    var form = editor.closest('form');
    if (form) form.addEventListener('submit', syncContent);
  }
})();
