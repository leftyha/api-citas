function createToast(message, tone = 'info', duration = 4000) {
  const colors = {
    info: 'bg-sky-50 border-sky-200 text-sky-700',
    success: 'bg-emerald-50 border-emerald-200 text-emerald-700',
    warning: 'bg-amber-50 border-amber-200 text-amber-700',
    error: 'bg-red-50 border-red-200 text-red-700'
  };

  const icons = {
    info: 'fa-circle-info',
    success: 'fa-circle-check',
    warning: 'fa-triangle-exclamation',
    error: 'fa-circle-xmark'
  };

  const toast = document.createElement('div');
  toast.className = `
    flex items-center gap-2 border px-4 py-3 rounded-md shadow-lg text-sm
    animate-[fadeIn_.3s]
    ${colors[tone] || colors.info}
  `;

  toast.innerHTML = `
    <i class="fa-solid ${icons[tone] || icons.info}"></i>
    <span>${message}</span>
  `;

  const container = document.getElementById('toast-container');
  if (!container) return;

  if (container.children.length >= 3) {
    container.removeChild(container.firstChild);
  }

  container.appendChild(toast);

  setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transform = 'translateY(-10px)';
    setTimeout(() => toast.remove(), 300);
  }, duration);
}

window.showAlert = (message, tone) => createToast(message, tone);
