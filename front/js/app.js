const { ApiClient } = window.BookingApi;
const {
  formatLongDate,
  generateTimeSlots,
  monthTitle,
  normalizeDate,
  toDateTime
} = window.BookingDateTime;
const {
  getDefaultConfig,
  getOpeningHoursForDate,
  parseApiBaseUrl,
  parseLicenseId,
  resolveDataSourceLabel
} = window.BookingConfig;
const {
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
} = window.BookingUi;

async function bootstrap() {
  const root = document.getElementById('app');
  if (!root) return;

  renderShell(root);

  const form = document.getElementById('booking-form');
  const selectedSlotNode = document.getElementById('selected-slot');
  const selectedServiceNode = document.getElementById('selected-service');

  const serviceStep = document.getElementById('step-service');
  const calendarStep = document.getElementById('step-calendar');
  const confirmStep = document.getElementById('step-confirm');

  const licenseId = parseLicenseId(window.location.search);
  const apiBaseUrl = parseApiBaseUrl(window.location.search);
  let api;

  try {
    api = new ApiClient({ licenseId, apiBaseUrl });
  } catch {
    showAlert('Cargando configuración...', 'info');
    return;
  }

  const state = {
    config: getDefaultConfig(),
    currentStep: 'service',
    selectedDate: normalizeDate(new Date()),
    selectedTime: '',
    currentMonth: new Date(),
    slots: []
  };

  showAlert('Cargando configuración...', 'info');
  try {
    state.config = {
      ...state.config,
      ...(await api.getConfig())
    };
    showAlert(`Configuración cargada · ${resolveDataSourceLabel(state.config)}`, 'success');
  } catch (error) {
    showAlert(`No se pudo cargar configuración remota (${error.message}). Usando valores por defecto.`, 'warning');
  }

  buildServiceStep(serviceStep, state.config.services || []);
  bindCategoryChevron(serviceStep);
  applyBranding(state.config);
  buildCalendarStep(calendarStep);
  buildConfirmStep(confirmStep);

  const phoneInput = document.querySelector('#phone');
  window.intlTelInput(phoneInput, {
    initialCountry: 'pt',
    preferredCountries: ['pt', 'es'],
    separateDialCode: true,
    defaultCountry: 'pt',
    utilsScript: 'https://cdn.jsdelivr.net/npm/intl-tel-input@18/build/js/utils.js'
  });

  const continueToConfirm = document.getElementById('continue-to-confirm');
  const backStep = document.getElementById('back-step');
  const calendarTitle = document.getElementById('calendar-title');
  const calendarGrid = document.getElementById('calendar-grid');
  const timeGroups = document.getElementById('time-groups');

  function selectedService() {
    return (state.config.services || []).find((service) => service.id === state.selectedServiceId);
  }

  function updateSummary() {
    const serviceName = selectedService()?.name || '';
    selectedServiceNode.textContent = serviceName ? `Servicio - ${serviceName}` : '';

    if (state.selectedDate && state.selectedTime) {
      const pretty = `${formatLongDate(state.selectedDate)} / ${state.selectedTime}`;
      selectedSlotNode.textContent = pretty;
      const confirmSlot = document.getElementById('confirm-slot');
      if (confirmSlot) confirmSlot.textContent = pretty;
    }

    const confirmTitle = document.getElementById('confirm-title');
    const confirmSubtitle = document.getElementById('confirm-subtitle');
    const confirmLocation = document.getElementById('confirm-location');
    const confirmService = document.getElementById('confirm-service');
    const card = state.config?.card || {};
    const metadata = state.config?.metadata || {};

    if (confirmTitle) confirmTitle.textContent = card.title || 'Nueva Reserva';
    if (confirmSubtitle) confirmSubtitle.textContent = card.subtitle || '';
    if (confirmLocation) confirmLocation.textContent = card.locationTitle || metadata.branchName || '';
    if (confirmService) confirmService.textContent = `Servicio - ${serviceName || 'Seleccionar servicio'}`;
  }

  async function refreshSlots() {
    const date = state.selectedDate;
    const service = selectedService();
    if (!date || !service) return;

    let availableTimesFromApi = null;

    try {
      availableTimesFromApi = await api.getAvailability({ date });
    } catch (error) {
      availableTimesFromApi = null;
      showAlert(`No se pudo consultar disponibilidad (${error.message}).`, 'warning');
    }

    const hours = getOpeningHoursForDate(state.config, date);
    if (!hours) {
      state.slots = [];
      renderTimeGroups(timeGroups, groupSlots([]), state.selectedTime);
      return;
    }

    const slotTimes = generateTimeSlots({
      start: hours.start,
      end: hours.end,
      step: state.config.bookingRules?.slotIntervalMinutes || 30,
      duration: service.durationMinutes || 30
    });

    state.slots = slotTimes.map((time) => ({
      time,
      available: availableTimesFromApi ? availableTimesFromApi.includes(time) : true
    }));

    const firstAvailable = state.slots.find((slot) => slot.available);
    if (!state.selectedTime || !state.slots.some((slot) => slot.time === state.selectedTime && slot.available)) {
      state.selectedTime = firstAvailable?.time || '';
    }

    renderTimeGroups(timeGroups, groupSlots(state.slots), state.selectedTime);
    updateSummary();
  }

  function renderCalendar() {
    calendarTitle.textContent = monthTitle(state.currentMonth);
    renderCalendarGrid(calendarGrid, state.currentMonth, state.selectedDate, state.minDate);
  }

  function goToStep(step) {
    const stepTitle = document.getElementById('step-title');
    const titles = {
      service: 'Seleccionar servicio',
      calendar: 'Seleccionar fecha y hora',
      confirm: 'Confirmar reserva'
    };

    if (stepTitle) stepTitle.textContent = titles[step] || '';

    state.currentStep = step;
    showStep(step);
    backStep.classList.toggle('hidden', step === 'service');
    selectedSlotNode.classList.toggle('hidden', step === 'service');
  }

  state.selectedServiceId = null;
  state.minDate = normalizeDate(new Date());
  if (state.selectedDate < state.minDate) state.selectedDate = state.minDate;

  serviceStep.addEventListener('click', async (event) => {
    const serviceButton = event.target.closest('[data-service-id]');
    if (!serviceButton) return;

    state.selectedServiceId = serviceButton.dataset.serviceId;
    paintSelectedServiceButtons(serviceStep, state.selectedServiceId);

    updateSummary();
    renderCalendar();
    goToStep('calendar');
    await refreshSlots();
  });

  paintSelectedServiceButtons(serviceStep, state.selectedServiceId);

  calendarStep.addEventListener('click', async (event) => {
    const dayButton = event.target.closest('[data-day]');
    if (dayButton) {
      state.selectedDate = dayButton.dataset.day;
      state.currentMonth = new Date(`${state.selectedDate}T00:00:00`);
      renderCalendar();
      await refreshSlots();
      return;
    }

    const timeButton = event.target.closest('[data-time]');
    if (timeButton) {
      state.selectedTime = timeButton.dataset.time;
      renderTimeGroups(timeGroups, groupSlots(state.slots), state.selectedTime);
      updateSummary();
      return;
    }

    if (event.target.id === 'prev-month') {
      state.currentMonth = new Date(state.currentMonth.getFullYear(), state.currentMonth.getMonth() - 1, 1);
      renderCalendar();
    }

    if (event.target.id === 'next-month') {
      state.currentMonth = new Date(state.currentMonth.getFullYear(), state.currentMonth.getMonth() + 1, 1);
      renderCalendar();
    }
  });

  continueToConfirm.addEventListener('click', () => {
    if (!state.selectedDate || !state.selectedTime) {
      showAlert('Selecciona fecha y hora para continuar.', 'warning');
      return;
    }
    updateSummary();
    goToStep('confirm');
  });

  backStep.addEventListener('click', () => {
    if (state.currentStep === 'confirm') {
      goToStep('calendar');
      return;
    }
    goToStep('service');
  });

  form.addEventListener('submit', async (event) => {
    event.preventDefault();

    if (!state.selectedDate || !state.selectedTime) {
      showAlert('Selecciona una fecha y horario disponibles.', 'warning');
      goToStep('calendar');
      return;
    }

    const formData = new FormData(form);
    const service = selectedService();

    const payload = {
      serviceId: state.selectedServiceId,
      startAt: toDateTime(state.selectedDate, state.selectedTime).toISOString(),
      durationMinutes: service?.durationMinutes || 30,
      customer: {
        name: String(formData.get('name') || '').trim(),
        phone: String(formData.get('phone') || '').trim(),
        email: String(formData.get('email') || '').trim()
      },
      notes: String(formData.get('notes') || '').trim()
    };

    showAlert('Guardando cita...', 'info');

    try {
      await api.createAppointment(payload);
      showAlert('✅ Cita creada correctamente', 'success');
      form.reset();
      goToStep('service');
      await refreshSlots();
    } catch (error) {
      showAlert(
        error.status === 409
          ? '⚠️ El horario ya no está disponible. Actualizando disponibilidad...'
          : `❌ No se pudo guardar la cita (${error.message})`,
        error.status === 409 ? 'warning' : 'error'
      );

      if (error.status === 409) {
        await refreshSlots();
      }
    }
  });

  document.querySelectorAll('details').forEach((details) => {
    const icon = details.querySelector('summary i');

    details.addEventListener('toggle', () => {
      icon.classList.toggle('rotate-180', details.open);
    });
  });

  updateSummary();
  goToStep('service');
}

void (async () => {
  await (window.__htmlReady || Promise.resolve());
  bootstrap();
})();
