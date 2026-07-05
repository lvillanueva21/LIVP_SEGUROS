(() => {
  const originalAddEventListener = EventTarget.prototype.addEventListener;
  // No global interception: this hook simply rewires the button after the quote preview is rendered.
  const retry = () => {
    const button = document.getElementById('preview-print');
    if (!button || button.dataset.printFix === '1') return;
    button.dataset.printFix = '1';
    button.addEventListener('click', (event) => {
      event.preventDefault();
      event.stopImmediatePropagation();
      document.body.classList.add('quote-printing');
      requestAnimationFrame(() => requestAnimationFrame(() => setTimeout(() => window.print(), 60)));
    }, true);
  };
  const observer = new MutationObserver(retry);
  observer.observe(document.documentElement, { childList: true, subtree: true });
  retry();
})();
