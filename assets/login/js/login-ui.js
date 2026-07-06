(function () {
  'use strict';

  function setupDocumentInput() {
    var input = document.getElementById('document');
    if (!input) return;

    input.addEventListener('input', function () {
      var raw = input.value;
      var compact = raw.replace(/\s+/g, '');

      // Los documentos numéricos se mantienen limpios. CE conserva letras y números.
      if (/^\d*$/.test(compact)) {
        input.value = compact;
      } else {
        input.value = compact.toUpperCase();
      }
    });
  }

  function setupPasswordToggles() {
    document.querySelectorAll('.toggle-password').forEach(function (toggler) {
      toggler.addEventListener('click', function () {
        var target = document.querySelector(this.getAttribute('toggle'));
        if (!target) return;

        var hidden = target.getAttribute('type') === 'password';
        target.setAttribute('type', hidden ? 'text' : 'password');
        this.classList.toggle('fa-eye', !hidden);
        this.classList.toggle('fa-eye-slash', hidden);
      });
    });
  }

  function setupCarousel() {
    var carousel = document.getElementById('loginCoverCarousel');
    if (!carousel) return;

    var $carousel = window.jQuery ? window.jQuery(carousel) : null;
    var count = carousel.querySelectorAll('.carousel-item').length;
    if ($carousel && count > 1) {
      $carousel.carousel({ interval: 5000, pause: false });
    }

    var startX = 0;
    var pointerDown = false;
    var dragMoved = false;

    carousel.addEventListener('pointerdown', function (event) {
      if (event.pointerType === 'mouse' && event.button !== 0) return;
      pointerDown = true;
      dragMoved = false;
      startX = event.clientX;
      carousel.classList.add('is-dragging');
    });

    carousel.addEventListener('pointermove', function (event) {
      if (pointerDown && Math.abs(event.clientX - startX) > 6) {
        dragMoved = true;
      }
    });

    function endDrag(event) {
      if (!pointerDown) return;

      var diff = event.clientX - startX;
      pointerDown = false;
      carousel.classList.remove('is-dragging');

      if (Math.abs(diff) >= 35 && $carousel && count > 1) {
        $carousel.carousel(diff < 0 ? 'next' : 'prev');
      }
    }

    carousel.addEventListener('pointerup', endDrag);
    carousel.addEventListener('pointercancel', endDrag);
  }

  function setupDemoOptions() {
    var documentInput = document.getElementById('document');
    var password = document.getElementById('password-field');
    if (!documentInput || !password) return;

    document.querySelectorAll('.demo-access-option').forEach(function (option) {
      option.addEventListener('click', function () {
        documentInput.value = this.getAttribute('data-document') || '';
        password.value = this.getAttribute('data-password') || '';

        if (window.jQuery) {
          window.jQuery('#demoAccessModal').modal('hide');
        }

        documentInput.focus();
      });
    });
  }

  function cleanUnexpectedBackdrop() {
    // El carrusel ya no abre modales. Esto evita que un backdrop huérfano de una
    // versión anterior bloquee la página tras una recarga.
    if (document.querySelector('.modal.show')) return;

    document.querySelectorAll('.modal-backdrop').forEach(function (backdrop) {
      backdrop.remove();
    });

    document.body.classList.remove('modal-open');
    document.body.style.removeProperty('padding-right');
  }

  document.addEventListener('DOMContentLoaded', function () {
    setupDocumentInput();
    setupPasswordToggles();
    setupCarousel();
    setupDemoOptions();
    cleanUnexpectedBackdrop();

    if (window.jQuery) {
      window.jQuery('[data-toggle="tooltip"]').tooltip({ trigger: 'hover focus' });
    }
  });
})();
