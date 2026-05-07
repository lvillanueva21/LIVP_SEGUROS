(function () {
  function togglePassword(button) {
    var targetSelector = button.getAttribute('data-target') || '';
    if (targetSelector === '') return;

    var input = document.querySelector(targetSelector);
    if (!input) return;

    var icon = button.querySelector('i');
    var isPassword = input.getAttribute('type') === 'password';

    input.setAttribute('type', isPassword ? 'text' : 'password');
    button.setAttribute('title', isPassword ? 'Ocultar clave' : 'Mostrar u ocultar clave');
    button.setAttribute('aria-label', isPassword ? 'Ocultar clave' : 'Mostrar u ocultar clave');

    if (icon) {
      icon.classList.remove('fa-eye', 'fa-eye-slash');
      icon.classList.add(isPassword ? 'fa-eye-slash' : 'fa-eye');
    }
  }

  document.addEventListener('click', function (event) {
    var btn = event.target.closest('.js-toggle-password');
    if (!btn) return;
    event.preventDefault();
    togglePassword(btn);
  });
})();

