(function initBookingDateTime(global) {
  function toMinutes(time) {
    const [hours, minutes] = time.split(':').map(Number);
    return (hours * 60) + minutes;
  }

  function toTimeLabel(minutes) {
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    return `${String(hours).padStart(2, '0')}:${String(mins).padStart(2, '0')}`;
  }

  function toDateTime(date, time) {
    return new Date(`${date}T${time}:00`);
  }

  function generateTimeSlots({ start = '09:00', end = '18:00', step = 30, duration = step }) {
    const startMinutes = toMinutes(start);
    const endMinutes = toMinutes(end);

    const slots = [];
    for (let cursor = startMinutes; cursor + duration <= endMinutes; cursor += step) {
      slots.push(toTimeLabel(cursor));
    }

    return slots;
  }

  function normalizeDate(dateValue) {
    const date = new Date(dateValue);
    if (Number.isNaN(date.getTime())) return null;
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
  }

  function getWeekday(date) {
    const day = new Date(date).getDay();
    return day === 0 ? 7 : day;
  }

  function toIsoRangeForMonth(date) {
    const base = new Date(`${date}T00:00:00`);
    const year = base.getUTCFullYear();
    const month = base.getUTCMonth();

    const from = new Date(Date.UTC(year, month, 1, 0, 0, 0)).toISOString();
    const to = new Date(Date.UTC(year, month + 1, 0, 23, 59, 59)).toISOString();

    return { from, to };
  }

  function formatLongDate(dateIso) {
    const date = new Date(`${dateIso}T00:00:00`);
    return new Intl.DateTimeFormat('es-ES', { day: 'numeric', month: 'long', year: 'numeric' }).format(date);
  }

  function monthTitle(date) {
    return new Intl.DateTimeFormat('es-ES', { month: 'long', year: 'numeric' }).format(date);
  }

  global.BookingDateTime = {
    formatLongDate,
    generateTimeSlots,
    getWeekday,
    monthTitle,
    normalizeDate,
    toDateTime,
    toIsoRangeForMonth
  };
}(window));
