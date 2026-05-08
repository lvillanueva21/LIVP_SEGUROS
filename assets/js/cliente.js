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

  function initLoginCoverCarousel() {
    var carouselEl = document.getElementById('loginCoverCarousel');
    if (!carouselEl) return;

    var $carousel = window.jQuery ? window.jQuery(carouselEl) : null;
    if ($carousel) {
      $carousel.carousel({ interval: 5000, pause: false });
    }

    var coverImages = carouselEl.querySelectorAll('.js-cover-image');
    if (!coverImages.length) return;

    var startX = 0;
    var pointerDown = false;
    var dragMoved = false;
    var suppressClick = false;
    var dragThreshold = 35;

    carouselEl.addEventListener('pointerdown', function (event) {
      if (event.pointerType === 'mouse' && event.button !== 0) return;

      pointerDown = true;
      dragMoved = false;
      startX = event.clientX;
      carouselEl.classList.add('is-dragging');
    });

    carouselEl.addEventListener('pointermove', function (event) {
      if (!pointerDown) return;
      if (Math.abs(event.clientX - startX) > 6) {
        dragMoved = true;
      }
    });

    function endDrag(event) {
      if (!pointerDown) return;

      var diffX = event.clientX - startX;
      pointerDown = false;
      carouselEl.classList.remove('is-dragging');

      if (Math.abs(diffX) >= dragThreshold && $carousel) {
        if (diffX < 0) {
          $carousel.carousel('next');
        } else {
          $carousel.carousel('prev');
        }
        suppressClick = true;
        setTimeout(function () {
          suppressClick = false;
        }, 0);
      }
    }

    carouselEl.addEventListener('pointerup', endDrag);
    carouselEl.addEventListener('pointercancel', endDrag);

    Array.prototype.forEach.call(coverImages, function (image) {
      image.addEventListener('click', function (event) {
        if (suppressClick || dragMoved) {
          event.preventDefault();
          event.stopPropagation();
        }
      });
    });
  }

  function initCoverImageModal() {
    var coverImages = document.querySelectorAll('.js-cover-image');
    var modalImage = document.getElementById('coverModalImage');
    var modalDownload = document.getElementById('coverModalDownload');

    if (!coverImages.length || !modalImage || !modalDownload) return;

    Array.prototype.forEach.call(coverImages, function (item) {
      item.addEventListener('click', function () {
        var src = this.getAttribute('data-full-src') || this.getAttribute('src');
        var downloadName = this.getAttribute('data-download-name') || 'imagen.webp';

        modalImage.setAttribute('src', src);
        modalDownload.setAttribute('href', src);
        modalDownload.setAttribute('download', downloadName);
      });
    });
  }

  function initTooltips() {
    if (!window.jQuery) return;
    window.jQuery('[data-toggle="tooltip"]').tooltip({ trigger: 'hover focus' });
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

  initLoginCoverCarousel();
  initCoverImageModal();
  initTooltips();

  window.ClienteNotify = {
    show: showToast
  };
})();