(function () {
  function togglePassword(button) {
    var targetSelector = button.getAttribute('data-target') || button.getAttribute('toggle') || '';
    if (targetSelector === '') return;

    var input = document.querySelector(targetSelector);
    if (!input) return;

    var icon = button.querySelector('i') || button;
    var isPassword = input.getAttribute('type') === 'password';

    input.setAttribute('type', isPassword ? 'text' : 'password');
    button.setAttribute('title', isPassword ? 'Ocultar clave' : 'Mostrar u ocultar clave');
    button.setAttribute('aria-label', isPassword ? 'Ocultar clave' : 'Mostrar u ocultar clave');

    if (icon && icon.classList) {
      icon.classList.remove('fa-eye', 'fa-eye-slash');
      icon.classList.add(isPassword ? 'fa-eye-slash' : 'fa-eye');
    }
  }

  function getToastContainer() {
    var container = document.querySelector('.cliente-toast-container');
    if (container) return container;

    container = document.createElement('div');
    container.className = 'cliente-toast-container';
    document.body.appendChild(container);
    return container;
  }

  function showToast(message, options) {
    var opts = options || {};
    var duration = Number(opts.duration || 3500);
    if (!Number.isFinite(duration) || duration < 1200) {
      duration = 3500;
    }

    var container = getToastContainer();
    var toast = document.createElement('div');
    toast.className = 'cliente-toast';
    toast.textContent = String(message || 'Operación completada.');

    var progress = document.createElement('span');
    progress.className = 'cliente-toast-progress';
    toast.appendChild(progress);

    container.appendChild(toast);

    var start = Date.now();
    var timer = window.setInterval(function () {
      var elapsed = Date.now() - start;
      var remain = Math.max(0, 1 - elapsed / duration);
      progress.style.transform = 'scaleX(' + remain + ')';
      if (remain <= 0) {
        window.clearInterval(timer);
      }
    }, 40);

    window.setTimeout(function () {
      window.clearInterval(timer);
      if (toast.parentNode) {
        toast.parentNode.removeChild(toast);
      }
    }, duration + 60);
  }

  document.addEventListener('click', function (event) {
    var btn = event.target.closest('.js-toggle-password, .toggle-password');
    if (!btn) return;
    event.preventDefault();
    togglePassword(btn);
  });

  window.ClienteNotify = {
    show: showToast
  };
})();