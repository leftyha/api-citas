(function initBookingUi(global) {
  function cloneTemplate(templateId) {
    const template = document.getElementById(templateId);
    if (!(template instanceof HTMLTemplateElement)) {
      throw new Error(`Template no encontrado: ${templateId}`);
    }

    return template.content.cloneNode(true);
  }

  function renderShell(root) {
    root.replaceChildren(cloneTemplate('tpl-shell'));
  }

  function getActiveServicesByCategory(services) {
    const grouped = new Map();
    (services || [])
      .filter((service) => service.active !== false)
      .forEach((service) => {
        const category = service.category || 'SERVICIOS';
        if (!grouped.has(category)) {
          grouped.set(category, []);
        }
        grouped.get(category).push(service);
      });

    return grouped;
  }

  function buildServiceStep(node, services) {
    const byCategory = getActiveServicesByCategory(services);
    const categories = [...byCategory.entries()];

    const fragment = document.createDocumentFragment();

    categories.forEach(([category, categoryServices]) => {
      const categoryFragment = cloneTemplate('tpl-service-category');
      const categoryName = categoryFragment.querySelector('[data-category-name]');
      const categoryServicesNode = categoryFragment.querySelector('[data-category-services]');

      categoryName.textContent = category;

      categoryServices.forEach((service) => {
        const optionFragment = cloneTemplate('tpl-service-option');
        const optionButton = optionFragment.querySelector('[data-service-id]');

        optionButton.dataset.serviceId = service.id;
        optionButton.textContent = service.name;

        categoryServicesNode.append(optionButton);
      });

      fragment.append(categoryFragment);
    });

    node.replaceChildren(fragment);
  }

  function buildCalendarStep(node) {
    node.replaceChildren(cloneTemplate('tpl-calendar-step'));
  }

  function buildConfirmStep(node) {
    node.replaceChildren(cloneTemplate('tpl-confirm-step'));
  }

  function showStep(step) {
    const steps = ['service', 'calendar', 'confirm'];
    steps.forEach((name) => {
      const panel = document.getElementById(`step-${name}`);
      if (!panel) return;
      panel.classList.toggle('hidden', name !== step);
    });
  }

  function renderCalendarGrid(node, monthDate, selectedDate, minDate) {
    const first = new Date(monthDate.getFullYear(), monthDate.getMonth(), 1);
    const offset = (first.getDay() + 6) % 7;
    const start = new Date(first);
    start.setDate(first.getDate() - offset);

    const labels = ['L', 'M', 'X', 'J', 'V', 'S', 'D'];
    const fragment = document.createDocumentFragment();

    labels.forEach((label) => {
      const weekdayFragment = cloneTemplate('tpl-calendar-weekday');
      const weekdayLabel = weekdayFragment.querySelector('[data-weekday-label]');
      weekdayLabel.textContent = label;
      fragment.append(weekdayFragment);
    });

    for (let i = 0; i < 42; i += 1) {
      const date = new Date(start);
      date.setDate(start.getDate() + i);

      const iso = global.BookingDateTime.normalizeDate(date);
      const isCurrentMonth = date.getMonth() === monthDate.getMonth();
      const isSelected = iso === selectedDate;
      const isPastDate = Boolean(minDate && iso && iso < minDate);

      const dayFragment = cloneTemplate('tpl-calendar-day-button');
      const dayButton = dayFragment.querySelector('[data-day]');
      dayButton.dataset.day = iso;
      dayButton.textContent = String(date.getDate());
      dayButton.classList.toggle('opacity-40', !isCurrentMonth || isPastDate);
      dayButton.classList.toggle('border-brand-dark', isSelected);
      dayButton.classList.toggle('bg-brand-dark', isSelected);
      dayButton.classList.toggle('text-white', isSelected);
      dayButton.disabled = isPastDate;
      dayButton.classList.toggle('cursor-not-allowed', isPastDate);

      fragment.append(dayButton);
    }

    node.replaceChildren(fragment);
  }

  function groupSlots(slots) {
    return {
      Mañana: slots.filter((slot) => Number(slot.time.slice(0, 2)) < 12),
      Tarde: slots.filter((slot) => Number(slot.time.slice(0, 2)) >= 12 && Number(slot.time.slice(0, 2)) < 18),
      Noche: slots.filter((slot) => Number(slot.time.slice(0, 2)) >= 18)
    };
  }

  function renderTimeGroups(node, grouped, selectedTime) {
    const fragment = document.createDocumentFragment();
    const container = document.createElement('div');
    container.className = 'grid grid-cols-3 gap-4';

    Object.entries(grouped).forEach(([label, slots]) => {
      const column = document.createElement('div');
      column.className = 'flex flex-col gap-2';

      const title = document.createElement('h4');
      title.className = 'text-slate-700 font-semibold text-center';
      title.textContent = label;

      column.appendChild(title);

      if (!slots.length) {
        const empty = document.createElement('p');
        empty.className = 'text-sm text-brand-muted text-center';
        empty.textContent = 'Sin horarios';
        column.appendChild(empty);
      } else {
        slots.forEach((slot) => {
          const slotFragment = cloneTemplate('tpl-time-slot-button');
          const slotButton = slotFragment.querySelector('[data-time]');

          slotButton.dataset.time = slot.time;
          slotButton.textContent = slot.time;

          slotButton.disabled = !slot.available;
          slotButton.classList.toggle('border-slate-300', !slot.available);
          slotButton.classList.toggle('cursor-not-allowed', !slot.available);
          slotButton.classList.toggle('opacity-45', !slot.available);

          slotButton.classList.toggle('border-slate-800', slot.time === selectedTime);
          slotButton.classList.toggle('bg-slate-800', slot.time === selectedTime);
          slotButton.classList.toggle('text-white', slot.time === selectedTime);

          column.appendChild(slotButton);
        });
      }

      container.appendChild(column);
    });

    fragment.appendChild(container);
    node.replaceChildren(fragment);
  }

  function paintSelectedServiceButtons(container, selectedServiceId) {
    container.querySelectorAll('.service-option').forEach((button) => {
      const selected = button.dataset.serviceId === selectedServiceId;
      button.classList.remove('bg-brand-dark', 'text-white', 'border-brand-dark');
      button.setAttribute('aria-pressed', selected ? 'true' : 'false');
    });
  }

  function bindCategoryChevron(container) {
    container.querySelectorAll('details').forEach((detailNode) => {
      const arrowNode = detailNode.querySelector('[data-category-arrow]');
      if (!arrowNode) return;

      const paintArrow = () => {
        arrowNode.textContent = detailNode.open ? '⌄' : '›';
      };

      paintArrow();
      detailNode.addEventListener('toggle', paintArrow);
    });
  }

  function applyBranding(config) {
    const branding = config?.branding || {};
    const metadata = config?.metadata || {};
    const card = config?.card || {};
    const footer = config?.footer || {};

    const hero = document.querySelector('.hero');
    if (hero && branding.headerBackgroundImage) {
      hero.style.backgroundImage = `url('${branding.headerBackgroundImage}')`;
    }

    if (branding.pageTitle) document.title = branding.pageTitle;

    const heroTitle = document.getElementById('hero-title');
    if (heroTitle) heroTitle.textContent = branding.heroTitle || 'ALAIN AFFLELOU ÓPTICO - MARCAÇÃO ONLINE';

    const logoNode = document.getElementById('hero-logo');
    if (logoNode) {
      if (branding.logoUrl) {
        logoNode.replaceChildren();
        const logoImage = document.createElement('img');
        logoImage.src = branding.logoUrl;
        logoImage.alt = 'Logo compañía';
        logoImage.classList.add('rounded-full');
        logoNode.append(logoImage);
      } else {
        logoNode.textContent = branding.logoText || 'ALAIN AFFLELOU';
      }
    }

    const linksNode = document.getElementById('hero-links');
    if (linksNode) {
      const links = [...(branding.socialLinks || []), ...(branding.websiteLinks || [])];
      linksNode.replaceChildren();

      links.forEach((link) => {
        const linkFragment = cloneTemplate('tpl-hero-link-item');
        const anchor = linkFragment.querySelector('a');
        anchor.href = link.url || '#';
        anchor.textContent = link.label || link.url || '';
        linksNode.append(linkFragment);
      });
    }

    const cardTitle = document.getElementById('card-title');
    if (cardTitle) cardTitle.textContent = card.title || 'Nueva Reserva';

    const cardSubtitle = document.getElementById('card-subtitle');
    if (cardSubtitle) cardSubtitle.textContent = card.subtitle || '';

    const cardLocation = document.getElementById('card-location');
    if (cardLocation) cardLocation.textContent = card.locationTitle || metadata.branchName || 'AFFLELOU ÓPTICO - FUNCHAL';

    const footerThanks = document.getElementById('footer-thanks');
    if (footerThanks) footerThanks.textContent = footer.thanksText || 'OBRIGADO POR NOS CONTACTAR';

    const footerNewsPrefix = document.getElementById('footer-news-prefix');
    const footerNewsLink = document.getElementById('footer-news-link');
    if (footerNewsPrefix) footerNewsPrefix.textContent = footer.newsPrefix || 'DESCUBRA AS NOVIDADES AFFLELOU:';
    if (footerNewsLink) {
      footerNewsLink.href = footer.newsUrl || 'http://afflelou.pt';
      footerNewsLink.textContent = footer.newsUrl || 'http://afflelou.pt';
    }

    const footerCookies = document.getElementById('footer-cookies');
    if (footerCookies) {
      footerCookies.href = footer.cookiesUrl || '#';
      footerCookies.textContent = footer.cookiesLabel || 'Ver política de cookies';
    }
  }

  global.BookingUi = {
    applyBranding,
    bindCategoryChevron,
    buildCalendarStep,
    buildConfirmStep,
    buildServiceStep,
    groupSlots,
    paintSelectedServiceButtons,
    renderCalendarGrid,
    renderShell,
    renderTimeGroups,
    showStep
  };
}(window));
