(function initBookingApi(global) {
  const ENDPOINTS = {
    configuration: '/api/configuration',
    appointments: '/api/appointments',
    createAppointment: '/api/appointments/create'
  };

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
    constructor({ licenseId }) {
      this.licenseId = assertLicenseId(licenseId);
    }

    async getConfig() {
      const params = new URLSearchParams({ licenseId: this.licenseId });
      return request(`${ENDPOINTS.configuration}?${params.toString()}`);
    }

    async getAppointments({ from, to }) {
      const params = new URLSearchParams({
        licenseId: this.licenseId,
        from,
        to
      });

      return request(`${ENDPOINTS.appointments}?${params.toString()}`);
    }

    async createAppointment(payload) {
      return request(ENDPOINTS.createAppointment, {
        method: 'POST',
        body: JSON.stringify({
          licenseId: this.licenseId,
          ...payload
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
