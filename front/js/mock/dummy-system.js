(function bootstrapDummySystem() {
  const BASE_DELAY_MS = 120;

  const makeResponse = (body, status = 200) => new Response(JSON.stringify(body), {
    status,
    headers: { 'Content-Type': 'application/json' }
  });

  const makeDateAtTime = (date, time) => new Date(`${date}T${time}:00.000Z`);

  const addMinutes = (isoDate, minutes) => new Date(new Date(isoDate).getTime() + (minutes * 60 * 1000)).toISOString();

  const buildAppointment = ({ id, date, time, durationMinutes, serviceId, professionalId, customer, notes, channel, tags = [] }) => {
    const startAt = makeDateAtTime(date, time).toISOString();
    return {
      id,
      serviceId,
      professionalId,
      startAt,
      endAt: addMinutes(startAt, durationMinutes),
      durationMinutes,
      customer,
      notes,
      channel,
      tags,
      status: 'confirmed',
      createdAt: new Date(`${date}T07:00:00.000Z`).toISOString(),
      updatedAt: new Date(`${date}T07:30:00.000Z`).toISOString()
    };
  };

  const demoStores = {
    AFFLELOU_FUNCHAL_01: {
      configuration: {
        timezone: 'Atlantic/Madeira',
        bookingRules: {
          slotIntervalMinutes: 30,
          minAdvanceMinutes: 60,
          maxAdvanceDays: 120,
          allowWalkIn: false,
          cancellationHours: 24
        },
        openingHours: [
          { weekday: 1, start: '09:00', end: '20:00' },
          { weekday: 2, start: '09:00', end: '20:00' },
          { weekday: 3, start: '09:00', end: '20:00' },
          { weekday: 4, start: '09:00', end: '20:00' },
          { weekday: 5, start: '09:00', end: '20:00' },
          { weekday: 6, start: '10:00', end: '14:00' }
        ],
        services: [
          { id: 'consulta-optometria', name: 'Consulta de Optometria', category: 'ÓCULOS', durationMinutes: 30, active: true, priceEur: 39 },
          { id: 'avaliacao-carta-conducao', name: 'Avaliação Visual para Efeitos de Carta de Condução', category: 'ÓCULOS', durationMinutes: 30, active: true, priceEur: 45 },
          { id: 'lentes-contacto-primeira-vez', name: 'Consulta de Optometria — 1ª vez para Lentes de Contato', category: 'LENTES DE CONTACTO', durationMinutes: 45, active: true, priceEur: 55 },
          { id: 'verificacao-adaptacao-lentes', name: 'Verificação de adaptação de Lentes de Contato', category: 'LENTES DE CONTACTO', durationMinutes: 30, active: true, priceEur: 29 }
        ],
        professionals: [
          { id: 'PRO-ANA-SILVA', name: 'Ana Silva', specialty: 'Optometría clínica', languages: ['PT', 'ES'] },
          { id: 'PRO-MARCO-REIS', name: 'Marco Reis', specialty: 'Contactología', languages: ['PT', 'EN'] },
          { id: 'PRO-INES-FREITAS', name: 'Inês Freitas', specialty: 'Control de miopía', languages: ['PT', 'ES', 'EN'] }
        ],
        metadata: {
          branchName: 'AFFLELOU ÓPTICO - FUNCHAL',
          address: 'Rua Dr. Fernão de Ornelas, 44, Funchal',
          phone: '+351 291 000 111',
          email: 'funchal@afflelou-demo.pt'
        },
        branding: {
          pageTitle: 'Marcación online',
          heroTitle: 'ALAIN AFFLELOU ÓPTICO - MARCAÇÃO ONLINE',
          headerBackgroundImage: 'https://image-uploader-service.firebaseapp.com/446fd8dd-f0f0-49fa-8425-9cf0db0c7c39/INT GRAD.jpg?w=1600&quot;',
          logoUrl: 'https://image-uploader-service.firebaseapp.com/f959d1a5-8706-4013-ae23-c127493f66bf/logo-afflelou-new.png?w=200',
          socialLinks: [
            { label: 'Instagram', url: 'https://www.instagram.com/alainafflelou/' },
            { label: 'Facebook', url: 'https://www.facebook.com/alainafflelou.pt/' }
          ],
          websiteLinks: [
            { label: 'Site oficial', url: 'http://afflelou.pt' }
          ]
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
      },
      appointments: [
        buildAppointment({ id: 'APT-1001', date: '2026-03-16', time: '09:00', durationMinutes: 30, serviceId: 'consulta-optometria', professionalId: 'PRO-ANA-SILVA', customer: { name: 'Carlos Gómez', phone: '+351910111000', email: 'carlos.gomez@email.test' }, notes: 'Primera visita. Refiera fatiga visual nocturna.', channel: 'online', tags: ['nueva-alta'] }),
        buildAppointment({ id: 'APT-1002', date: '2026-03-16', time: '10:00', durationMinutes: 45, serviceId: 'lentes-contacto-primeira-vez', professionalId: 'PRO-MARCO-REIS', customer: { name: 'Lucía Paredes', phone: '+351910111001', email: 'lucia.paredes@email.test' }, notes: 'Prueba de lentes tóricas.', channel: 'call-center', tags: ['lentes-contacto'] }),
        buildAppointment({ id: 'APT-1003', date: '2026-03-16', time: '11:30', durationMinutes: 60, serviceId: 'verificacao-adaptacao-lentes', professionalId: 'PRO-INES-FREITAS', customer: { name: 'Mateo Fernández', phone: '+351910111002', email: 'familia.fernandez@email.test' }, notes: 'Paciente pediátrico. Revisión trimestral.', channel: 'online', tags: ['pediatria'] }),
        buildAppointment({ id: 'APT-1004', date: '2026-03-17', time: '09:30', durationMinutes: 30, serviceId: 'avaliacao-carta-conducao', professionalId: 'PRO-ANA-SILVA', customer: { name: 'Teresa Almeida', phone: '+351910111003', email: 'teresa.almeida@email.test' }, notes: 'Ajuste de progresivos y cefalea ocasional.', channel: 'online' }),
        buildAppointment({ id: 'APT-1005', date: '2026-03-17', time: '16:00', durationMinutes: 20, serviceId: 'verificacao-adaptacao-lentes', professionalId: 'PRO-MARCO-REIS', customer: { name: 'João Carvalho', phone: '+351910111004', email: 'joao.carvalho@email.test' }, notes: 'Comprobación de adaptación de montura.', channel: 'store' }),
        buildAppointment({ id: 'APT-1006', date: '2026-03-18', time: '12:00', durationMinutes: 30, serviceId: 'consulta-optometria', professionalId: 'PRO-ANA-SILVA', customer: { name: 'María Rojas', phone: '+351910111005', email: 'maria.rojas@email.test' }, notes: 'Control anual y sensibilidad a pantallas.', channel: 'online' }),
        buildAppointment({ id: 'APT-1007', date: '2026-03-19', time: '18:00', durationMinutes: 45, serviceId: 'lentes-contacto-primeira-vez', professionalId: 'PRO-MARCO-REIS', customer: { name: 'Daniel Costa', phone: '+351910111006', email: 'daniel.costa@email.test' }, notes: 'Renovación de prescripción de lentillas.', channel: 'online' }),
        buildAppointment({ id: 'APT-1008', date: '2026-03-20', time: '10:30', durationMinutes: 60, serviceId: 'verificacao-adaptacao-lentes', professionalId: 'PRO-INES-FREITAS', customer: { name: 'Sofía Ramírez', phone: '+351910111007', email: 'sofia.ramirez@email.test' }, notes: 'Seguimiento con topografía corneal.', channel: 'referral', tags: ['informe-clinico'] }),
        buildAppointment({ id: 'APT-1009', date: '2026-03-21', time: '10:00', durationMinutes: 30, serviceId: 'avaliacao-carta-conducao', professionalId: 'PRO-ANA-SILVA', customer: { name: 'Bruno Nunes', phone: '+351910111008', email: 'bruno.nunes@email.test' }, notes: 'Revisión previa a renovación de gafas.', channel: 'online' }),
        buildAppointment({ id: 'APT-1010', date: '2026-03-24', time: '15:00', durationMinutes: 30, serviceId: 'consulta-optometria', professionalId: 'PRO-ANA-SILVA', customer: { name: 'Elena Martín', phone: '+351910111009', email: 'elena.martin@email.test' }, notes: 'Visión borrosa en conducción nocturna.', channel: 'online' })
      ],
      testProfiles: {
        highDemandDays: ['2026-03-16', '2026-03-20'],
        blackoutDates: ['2026-03-30'],
        sampleCampaigns: ['2x1 Gafas graduadas', 'Lentes progresivas -20%'],
        syntheticMetrics: {
          avgDailyBookings: 16,
          noShowRatePct: 7.5,
          onlineBookingPct: 63,
          satisfactionScore: 4.7
        }
      }
    }
  };

  const clone = (value) => JSON.parse(JSON.stringify(value));

  const getStore = (licenseId) => demoStores[licenseId] || demoStores.AFFLELOU_FUNCHAL_01;

  const parseBody = async (initBody) => {
    if (!initBody) return {};
    if (typeof initBody === 'string') {
      try {
        return JSON.parse(initBody);
      } catch {
        return {};
      }
    }

    if (initBody instanceof FormData) {
      return Object.fromEntries(initBody.entries());
    }

    return {};
  };

  const withDelay = async (fn) => {
    await new Promise((resolve) => setTimeout(resolve, BASE_DELAY_MS + Math.round(Math.random() * 80)));
    return fn();
  };

  const router = async (url, options) => {
    const method = (options?.method || 'GET').toUpperCase();
    const parsed = new URL(url, window.location.origin);

    if (!parsed.pathname.startsWith('/api/')) {
      return null;
    }

    if (window.location.search.includes('useMock=0')) {
      return null;
    }

    if (parsed.pathname === '/api/configuration' && method === 'GET') {
      return withDelay(() => {
        const licenseId = parsed.searchParams.get('licenseId') || 'AFFLELOU_FUNCHAL_01';
        const store = getStore(licenseId);
        return makeResponse({
          licenseId,
          ...clone(store.configuration),
          debug: {
            source: 'dummy-system',
            generatedAt: new Date().toISOString(),
            availableProfiles: Object.keys(demoStores)
          }
        });
      });
    }

    if (parsed.pathname === '/api/appointments' && method === 'GET') {
      return withDelay(() => {
        const licenseId = parsed.searchParams.get('licenseId') || 'AFFLELOU_FUNCHAL_01';
        const from = parsed.searchParams.get('from');
        const to = parsed.searchParams.get('to');
        const store = getStore(licenseId);

        const fromMs = from ? new Date(from).getTime() : Number.NEGATIVE_INFINITY;
        const toMs = to ? new Date(to).getTime() : Number.POSITIVE_INFINITY;

        const filtered = store.appointments.filter((appointment) => {
          const start = new Date(appointment.startAt).getTime();
          return start >= fromMs && start <= toMs;
        });

        return makeResponse({
          licenseId,
          appointments: clone(filtered),
          detail: clone(store.testProfiles)
        });
      });
    }

    if (parsed.pathname === '/api/appointments/create' && method === 'POST') {
      return withDelay(async () => {
        const payload = await parseBody(options?.body);
        const licenseId = payload.licenseId || 'AFFLELOU_FUNCHAL_01';
        const store = getStore(licenseId);

        const startAt = new Date(payload.startAt).getTime();
        const durationMinutes = Number(payload.durationMinutes || 30);
        const endAt = startAt + (durationMinutes * 60 * 1000);

        const hasConflict = store.appointments.some((existing) => {
          const existingStart = new Date(existing.startAt).getTime();
          const existingEnd = new Date(existing.endAt).getTime();
          return startAt < existingEnd && endAt > existingStart;
        });

        if (hasConflict) {
          return makeResponse({
            message: 'El horario ya está ocupado por otra cita de prueba.',
            code: 'SLOT_CONFLICT'
          }, 409);
        }

        const created = {
          id: `APT-${Math.floor(2000 + Math.random() * 7000)}`,
          serviceId: payload.serviceId,
          professionalId: 'PRO-AUTO-ASIGNADO',
          startAt: new Date(payload.startAt).toISOString(),
          endAt: new Date(endAt).toISOString(),
          durationMinutes,
          customer: payload.customer || {},
          notes: payload.notes || '',
          status: 'confirmed',
          createdAt: new Date().toISOString(),
          updatedAt: new Date().toISOString(),
          channel: 'online'
        };

        store.appointments.push(created);

        return makeResponse({
          message: 'Cita de prueba creada correctamente.',
          appointment: clone(created)
        }, 201);
      });
    }

    return withDelay(() => makeResponse({ message: 'Endpoint dummy no implementado.' }, 404));
  };

  const originalFetch = window.fetch.bind(window);
  window.fetch = async (input, init) => {
    const url = typeof input === 'string' ? input : input.url;
    const mocked = await router(url, init);
    if (mocked) return mocked;
    return originalFetch(input, init);
  };

  window.__BOOKING_DUMMY_SYSTEM__ = {
    mode: 'enabled',
    stores: Object.keys(demoStores),
    description: 'Sistema dummy activo. Usa ?licenseId=AFFLELOU_FUNCHAL_01 (o cualquier ID válido) para cargar datos de prueba.'
  };
})();
