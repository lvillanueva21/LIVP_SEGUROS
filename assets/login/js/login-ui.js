(function () {
  'use strict';

  function updateDocumentField() {
    var type = document.getElementById('document_type');
    var input = document.getElementById('document');
    var label = document.getElementById('document-label');
    if (!type || !input || !label) return;

    var value = type.value;
    var config = {
      DNI: { label: 'Número de DNI', max: 8, inputmode: 'numeric', pattern: '\\d{8}' },
      CE: { label: 'Número de carné de extranjería', max: 12, inputmode: 'text', pattern: '' },
      RUC: { label: 'Número de RUC', max: 11, inputmode: 'numeric', pattern: '\\d{11}' }
    }[value] || { label: 'Documento', max: 30, inputmode: 'text', pattern: '' };

    label.textContent = config.label;
    input.maxLength = config.max;
    input.setAttribute('inputmode', config.inputmode);
    if (config.pattern) input.setAttribute('pattern', config.pattern);
    else input.removeAttribute('pattern');
  }

  function setupDocumentFilter() {
    var type = document.getElementById('document_type');
    var input = document.getElementById('document');
    if (!input) return;

    if (type) {
      type.addEventListener('change', updateDocumentField);
      input.addEventListener('input', function () {
        if (type.value === 'DNI' || type.value === 'RUC') input.value = input.value.replace(/\D+/g, '');
        else input.value = input.value.replace(/\s+/g, '').toUpperCase();
      });
      updateDocumentField();
      return;
    }

    // Login V1: no solicita tipo. Conserva letras para CE y elimina solo espacios.
    input.addEventListener('input', function () {
      input.value = input.value.replace(/\s+/g, '').toUpperCase();
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
    if ($carousel && count > 1) $carousel.carousel({ interval: 5000, pause: false });

    var startX = 0;
    var pointerDown = false;
    var dragMoved = false;
    var suppressClick = false;
    carousel.addEventListener('pointerdown', function (event) {
      if (event.pointerType === 'mouse' && event.button !== 0) return;
      pointerDown = true; dragMoved = false; startX = event.clientX; carousel.classList.add('is-dragging');
    });
    carousel.addEventListener('pointermove', function (event) {
      if (pointerDown && Math.abs(event.clientX - startX) > 6) dragMoved = true;
    });
    function endDrag(event) {
      if (!pointerDown) return;
      var diff = event.clientX - startX;
      pointerDown = false; carousel.classList.remove('is-dragging');
      if (Math.abs(diff) >= 35 && $carousel && count > 1) {
        $carousel.carousel(diff < 0 ? 'next' : 'prev');
        suppressClick = true;
        window.setTimeout(function () { suppressClick = false; }, 0);
      }
    }
    carousel.addEventListener('pointerup', endDrag);
    carousel.addEventListener('pointercancel', endDrag);
    carousel.querySelectorAll('.js-cover-image').forEach(function (image) {
      image.addEventListener('click', function (event) {
        if (suppressClick || dragMoved) { event.preventDefault(); event.stopPropagation(); }
      });
    });
  }

  function setupImageModal() {
    var image = document.getElementById('coverModalImage');
    if (!image) return;
    document.querySelectorAll('.js-cover-image').forEach(function (cover) {
      cover.addEventListener('click', function () {
        image.setAttribute('src', this.getAttribute('data-full-src') || this.getAttribute('src') || '');
      });
    });
  }

  function setupDemoOptions() {
    var type = document.getElementById('document_type');
    var documentInput = document.getElementById('document');
    var password = document.getElementById('password-field');
    if (!documentInput || !password) return;

    document.querySelectorAll('.demo-access-option').forEach(function (option) {
      option.addEventListener('click', function () {
        if (type) {
          type.value = this.getAttribute('data-doc-type') || 'DNI';
          updateDocumentField();
        }
        documentInput.value = this.getAttribute('data-document') || '';
        password.value = this.getAttribute('data-password') || '';
        if (window.jQuery) window.jQuery('#demoAccessModal').modal('hide');
        documentInput.focus();
      });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    setupDocumentFilter();
    setupPasswordToggles();
    setupCarousel();
    setupImageModal();
    setupDemoOptions();
    if (window.jQuery) window.jQuery('[data-toggle="tooltip"]').tooltip({ trigger: 'hover focus' });
  });
})();
