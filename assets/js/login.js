(() => {
  const typeSelect = document.getElementById('document_type');
  const documentInput = document.getElementById('document');
  const documentLabel = document.getElementById('document_label');
  const passwordInput = document.getElementById('password');
  const togglePassword = document.getElementById('toggle-password');
  const demoCards = document.querySelectorAll('.demo-card');

  const configurationByType = {
    DNI: { label: 'Número de DNI', placeholder: 'Ejemplo: 12345678', maxLength: 8, inputMode: 'numeric' },
    CE: { label: 'Número de Carné de extranjería', placeholder: 'Ejemplo: CE1234567', maxLength: 12, inputMode: 'text' },
    RUC: { label: 'Número de RUC', placeholder: 'Ejemplo: 20123456789', maxLength: 11, inputMode: 'numeric' },
  };

  function updateDocumentField() {
    const config = configurationByType[typeSelect.value] || configurationByType.DNI;
    documentLabel.textContent = config.label;
    documentInput.placeholder = config.placeholder;
    documentInput.maxLength = config.maxLength;
    documentInput.inputMode = config.inputMode;
  }

  typeSelect?.addEventListener('change', updateDocumentField);
  updateDocumentField();

  togglePassword?.addEventListener('click', () => {
    const isHidden = passwordInput.type === 'password';
    passwordInput.type = isHidden ? 'text' : 'password';
    togglePassword.textContent = isHidden ? 'Ocultar' : 'Ver';
    togglePassword.setAttribute('aria-label', isHidden ? 'Ocultar contraseña' : 'Mostrar contraseña');
  });

  demoCards.forEach((card) => {
    card.addEventListener('click', () => {
      typeSelect.value = card.dataset.docType || 'DNI';
      updateDocumentField();
      documentInput.value = card.dataset.document || '';
      passwordInput.value = card.dataset.password || '';
      documentInput.focus();
    });
  });
})();
