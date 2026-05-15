(function initBookingConfig(global) {
  function getDefaultConfig() {
    return {
      timezone: 'Europe/Lisbon',
      bookingRules: {
        slotIntervalMinutes: 30,
        minAdvanceMinutes: 0,
        maxAdvanceDays: 90
      },
      openingHours: [1, 2, 3, 4, 5].map((weekday) => ({ weekday, start: '09:00', end: '20:00' })),
      services: [
        {
          id: 'consulta-optometria',
          name: 'Consulta de Optometria',
          category: 'ÓCULOS',
          durationMinutes: 30,
          active: true
        },
        {
          id: 'avaliacao-carta-conducao',
          name: 'Avaliação Visual para Efeitos de Carta de Condução',
          category: 'ÓCULOS',
          durationMinutes: 30,
          active: true
        },
        {
          id: 'lentes-contacto-primeira-vez',
          name: 'Consulta de Optometria — 1ª vez para Lentes de Contato',
          category: 'LENTES DE CONTACTO',
          durationMinutes: 45,
          active: true
        },
        {
          id: 'verificacao-adaptacao-lentes',
          name: 'Verificação de adaptação de Lentes de Contato',
          category: 'LENTES DE CONTACTO',
          durationMinutes: 30,
          active: true
        }
      ],
      metadata: {
        branchName: 'AFFLELOU ÓPTICO - FUNCHAL'
      },
      branding: {
        pageTitle: 'Marcación online',
        heroTitle: 'ALAIN AFFLELOU ÓPTICO - MARCAÇÃO ONLINE',
        logoText: 'ALAIN AFFLELOU',
        headerBackgroundImage: 'https://images.unsplash.com/photo-1574258495973-f010dfbb5371?auto=format&fit=crop&w=1800&q=80',
        socialLinks: [],
        websiteLinks: [{ label: 'Web', url: 'http://afflelou.pt' }]
      },
      card: {
        title: 'Nueva Reserva',
        subtitle: '',
        locationTitle: 'AFFLELOU ÓPTICO - FUNCHAL'
      },
      footer: {
        thanksText: 'OBRIGADO POR NOS CONTACTAR',
        newsPrefix: 'DESCUBRA AS NOVIDADES AFFLELOU:',
        newsUrl: 'http://afflelou.pt',
        cookiesLabel: 'Ver política de cookies',
        cookiesUrl: 'http://afflelou.pt/politica-cookies'
      }
    };
  }

  function parseLicenseId(search) {
    const params = new URLSearchParams(search);
    const provided = String(params.get('licenseId') || '').trim();
    return provided || 'AFFLELOU_FUNCHAL_01';
  }

  function getOpeningHoursForDate(config, date) {
    const weekday = global.BookingDateTime.getWeekday(date);
    return (config.openingHours || []).find((item) => item.weekday === weekday) || null;
  }

  function isSlotBlocked(slotDate, time, durationMinutes, appointments) {
    const slotStart = global.BookingDateTime.toDateTime(slotDate, time).getTime();
    const slotEnd = slotStart + (durationMinutes * 60 * 1000);

    return appointments.some((appointment) => {
      const start = new Date(appointment.startAt).getTime();
      const end = new Date(appointment.endAt).getTime();
      return slotStart < end && slotEnd > start;
    });
  }

  function resolveDataSourceLabel(config) {
    if (config?.debug?.source === 'dummy-system' || window.__BOOKING_DUMMY_SYSTEM__?.mode === 'enabled') {
      return '🧪 Modo dummy activo';
    }

    return '🌐 Backend remoto';
  }

  global.BookingConfig = {
    getDefaultConfig,
    getOpeningHoursForDate,
    isSlotBlocked,
    parseLicenseId,
    resolveDataSourceLabel
  };
}(window));
