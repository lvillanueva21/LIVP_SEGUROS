/* Fix de impresión A4: asegura que el DOM se recalcule antes de abrir el diálogo de impresión. */
(() => {
  const originalPrint = window.print.bind(window);
  window.BrokerQuotesPrint = () => {
    document.body.classList.add('quote-printing');
    window.requestAnimationFrame(() => {
      window.requestAnimationFrame(() => {
        window.setTimeout(originalPrint, 60);
      });
    });
  };
})();
