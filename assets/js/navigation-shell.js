(function () {
  'use strict';

  var storageKey = 'broker_seguros_sidebar_collapsed_v1';

  function isMobileLayout() {
    return window.matchMedia && window.matchMedia('(max-width: 800px)').matches;
  }

  function updateToggle(button, collapsed) {
    if (!button) return;
    var label = collapsed ? 'Expandir navegación lateral' : 'Comprimir navegación lateral';
    button.setAttribute('aria-label', label);
    button.setAttribute('title', label);
    button.setAttribute('aria-pressed', collapsed ? 'true' : 'false');
  }

  function start() {
    var body = document.body;
    var button = document.getElementById('sidebar-collapse-toggle');
    if (!body || !button) return;

    var saved = null;
    try {
      saved = window.localStorage.getItem(storageKey);
    } catch (error) {
      saved = null;
    }

    function apply(collapsed) {
      if (isMobileLayout()) {
        body.classList.remove('sidebar-is-collapsed');
        updateToggle(button, false);
        return;
      }

      body.classList.toggle('sidebar-is-collapsed', collapsed);
      updateToggle(button, collapsed);
    }

    apply(saved === '1');

    button.addEventListener('click', function () {
      if (isMobileLayout()) return;

      var collapsed = !body.classList.contains('sidebar-is-collapsed');
      apply(collapsed);

      try {
        window.localStorage.setItem(storageKey, collapsed ? '1' : '0');
      } catch (error) {
        // La navegación sigue funcionando aunque el navegador bloquee localStorage.
      }
    });

    window.addEventListener('resize', function () {
      var collapsed = false;
      try {
        collapsed = window.localStorage.getItem(storageKey) === '1';
      } catch (error) {
        collapsed = false;
      }
      apply(collapsed);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }
})();
