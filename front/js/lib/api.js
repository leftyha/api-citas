(function initBookingApi(global) {
  const ENDPOINTS = {
    configuration: '/api/configuration',
    availability: '/availability.php',
    createAppointment: '/appointments_create.php'
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
    constructor({ licenseId, configEndpoint }) {
      this.licenseId = assertLicenseId(licenseId);
      this.configEndpoint = String(configEndpoint || ENDPOINTS.configuration).trim() || ENDPOINTS.configuration;
    }

    async getConfig() {
      const params = new URLSearchParams({
        id_lice_encr: this.licenseId,
        licenseId: this.licenseId
      });

      return request(`${this.configEndpoint}?${params.toString()}`);
    }

    async getAvailability({ date }) {
      const params = new URLSearchParams({
        id_lice_encr: this.licenseId,
        date
      });

      const response = await request(`${ENDPOINTS.availability}?${params.toString()}`);
      return { slots: response?.data?.slots || [] };
    }

    async createAppointment(payload) {
      return request(ENDPOINTS.createAppointment, {
        method: 'POST',
        body: JSON.stringify({
          id_lice_encr: this.licenseId,
          startAt: payload.startAt,
          customerDocument: payload.customerDocument
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
