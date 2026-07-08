(function () {
  'use strict';

  var tabs = document.querySelectorAll('[data-mail-tab]');
  var panels = document.querySelectorAll('[data-mail-panel]');

  function activateTab(tabName) {
    tabs.forEach(function (tab) {
      tab.classList.toggle('is-active', tab.getAttribute('data-mail-tab') === tabName);
    });
    panels.forEach(function (panel) {
      panel.classList.toggle('is-active', panel.getAttribute('data-mail-panel') === tabName);
    });
  }

  tabs.forEach(function (tab) {
    tab.addEventListener('click', function () {
      activateTab(tab.getAttribute('data-mail-tab'));
    });
  });

  document.querySelectorAll('[data-toggle-password]').forEach(function (button) {
    button.addEventListener('click', function () {
      var target = document.querySelector(button.getAttribute('data-toggle-password'));
      if (!target) {
        return;
      }
      target.type = target.type === 'password' ? 'text' : 'password';
    });
  });

  document.querySelectorAll('[data-clean-spaces="1"]').forEach(function (input) {
    function cleanSpaces() {
      var start = input.selectionStart;
      var before = input.value;
      input.value = input.value.replace(/\s+/g, '');
      if (document.activeElement === input && start !== null && before !== input.value) {
        input.setSelectionRange(input.value.length, input.value.length);
      }
    }

    input.addEventListener('input', cleanSpaces);
    input.addEventListener('paste', function () {
      window.setTimeout(cleanSpaces, 0);
    });
    input.form && input.form.addEventListener('submit', cleanSpaces);
  });
}());
