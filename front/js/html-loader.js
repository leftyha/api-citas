async function injectPartial(selector, path) {
  const target = document.querySelector(selector);
  if (!target) return;

  const response = await fetch(path);
  if (!response.ok) {
    throw new Error(`No se pudo cargar ${path} (${response.status})`);
  }

  target.innerHTML = await response.text();
}

window.__htmlReady = Promise.all([
  injectPartial('#layout-root', './partials/layout.html'),
  injectPartial('#templates-root', './partials/templates.html')
]);
