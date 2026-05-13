(function (global) {
  function normalizeServerUtc(raw) {
    if (!raw && raw !== 0) return '';
    const s = String(raw).trim();
    if (!s) return '';
    if (/[zZ]$/.test(s) || /[+-]\d{2}:?\d{2}$/.test(s)) return s;
    return s.replace(' ', 'T') + 'Z';
  }

  function parseServerUtc(raw) {
    const iso = normalizeServerUtc(raw);
    if (!iso) return null;
    const d = new Date(iso);
    return Number.isNaN(d.getTime()) ? null : d;
  }

  function pad(v) { return String(v).padStart(2, '0'); }

  function formatForDisplayLocal(raw, options = {}) {
    const d = parseServerUtc(raw);
    if (!d) return options.compact ? 'N/A' : { date: 'N/A', time: 'N/A' };
    const out = {
      date: `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`,
      time: `${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`
    };
    return options.compact ? `${out.date} ${out.time}` : out;
  }

  function utcToDatetimeLocalValue(raw) {
    const d = parseServerUtc(raw);
    if (!d) return '';
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
  }

  function datetimeLocalToUtcIso(value) {
    if (!value) return '';
    const local = new Date(value);
    return Number.isNaN(local.getTime()) ? '' : local.toISOString();
  }

  function durationBetweenUtc(startRaw, endRaw) {
    const s = parseServerUtc(startRaw);
    const e = parseServerUtc(endRaw);
    if (!s || !e) return null;
    const diff = e.getTime() - s.getTime();
    if (diff <= 0) return null;
    return Math.round(diff / 60000);
  }

  global.DateTimeUtils = {
    parseServerUtc,
    formatForDisplayLocal,
    utcToDatetimeLocalValue,
    datetimeLocalToUtcIso,
    durationBetweenUtc,
  };
})(window);
