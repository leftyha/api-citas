(function initBookingApi(global) {
  const DEFAULT_API_BASE = window.location.origin;

  function assertLicenseId(licenseId) {
    const normalized = String(licenseId || '').trim();
    if (!/^[A-Za-z0-9_-]{2,50}$/.test(normalized)) {
      throw new Error('licenseId inválido o ausente en la URL.');
    }

    return normalized;
  }

  async function request(url, options = {}) {
    const response = await fetch(url, {
      headers: { 'Content-Type': 'application/json', ...(options.headers || {}) },
      ...options
    });

    const raw = await response.text();
    let data = null;
    if (raw) {
      try {
        data = JSON.parse(raw);
      } catch {
        data = { message: raw };
      }
    }

    if (!response.ok) {
      const message = data?.message || `Error ${response.status}`;
      const error = new Error(message);
      error.status = response.status;
      throw error;
    }

    return data;
  }

  class ApiClient {
    constructor({ licenseId, apiBaseUrl = DEFAULT_API_BASE }) {
      this.licenseId = assertLicenseId(licenseId);
      this.apiBaseUrl = String(apiBaseUrl || DEFAULT_API_BASE).replace(/\/+$/, '');
    }

    buildUrl(path, params = {}) {
      const url = new URL(path, `${this.apiBaseUrl}/`);
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
          url.searchParams.set(key, String(value));
        }
      });
      return url.toString();
    }

    async getConfig() {
      return {
        debug: {
          source: 'remote-url'
        }
      };
    }

    async getAvailability({ date }) {
      const url = this.buildUrl('availability.php', {
        id_lice_encr: this.licenseId,
        date
      });
      const response = await request(url);
      const slots = response?.data?.slots || [];
      return slots.map((slot) => {
        const start = new Date(slot.startAt);
        const hh = String(start.getHours()).padStart(2, '0');
        const mm = String(start.getMinutes()).padStart(2, '0');
        return `${hh}:${mm}`;
      });
    }

    async getAppointments({ from, to }) {
      const url = this.buildUrl('appointments_list.php', {
        id_lice_encr: this.licenseId,
        from,
        to
      });
      const response = await request(url);
      const appointments = (response?.data?.appointments || []).map((appointment) => ({
        ...appointment,
        startAt: appointment.fech_cita,
        endAt: new Date(new Date(appointment.fech_cita).getTime() + (30 * 60 * 1000)).toISOString()
      }));
      return { appointments };
    }

    async createAppointment(payload) {
      const customerDocument = payload?.customer?.email || payload?.customer?.phone || payload?.customer?.name || 'SIN_DOCUMENTO';
      const url = this.buildUrl('appointments_create.php');
      return request(url, {
        method: 'POST',
        body: JSON.stringify({
          id_lice_encr: this.licenseId,
          startAt: payload.startAt,
          customerDocument,
          status: 'pending'
        })
      });
    }
  }

  global.BookingApi = {
    ApiClient,
    assertLicenseId,
    request
  };
}(window));
