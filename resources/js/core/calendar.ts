/**
 * Calendar / Shamsi / Kabul date-time authority — frontend projection.
 *
 * - No business date is ever derived from `new Date().toISOString().slice(0,10)` (UTC)
 * - All business dates come from the server's CalendarAuthority (Kabul AFT UTC+04:30)
 * - Gregorian storage is canonical (YYYY-MM-DD UTC midnight)
 * - Shamsi is derived via ratified version-1 series
 * - Browser timezone never defines business meaning
 */

export type CalendarTodayPayload = {
  gregorian: string; // YYYY-MM-DD
  shamsi: string; // YYYY-MM-DD canonical
  shamsi_year: number;
  shamsi_month: number;
  shamsi_day: number;
  shamsi_month_name: string;
  version: string;
  kabul_offset_minutes: number;
  kabul_timezone: string;
  utc_now: string;
  kabul_now: string;
};

export type CalendarConversionPayload = {
  gregorian: string;
  shamsi: string;
  shamsi_year: number;
  shamsi_month: number;
  shamsi_day: number;
  shamsi_month_name: string;
  version: string;
};

export type CalendarPeriod = {
  id: string;
  period_key: string;
  name: string;
  starts_on: string;
  ends_on: string;
  lifecycle_state: string;
};

export type ShamsiYearPeriod = {
  starts_on: string;
  ends_on: string;
  shamsi_year: number;
  starts_on_exclusive_end: string;
  shamsi_months?: ShamsiMonthPeriod[];
  leap?: boolean;
  version?: string;
};

export type ShamsiMonthPeriod = {
  starts_on: string;
  ends_on: string;
  shamsi_year: number;
  shamsi_month: number;
  shamsi_month_name: string;
  length: number;
  starts_on_exclusive_end: string;
};

/**
 * Format a Gregorian date as Shamsi display — uses authoritative server values only.
 * No client-side Jalali conversion.
 */
export function formatShamsiDisplay(payload: { shamsi: string; shamsi_month_name: string; shamsi_year: number; shamsi_day: number }): string {
  return `${payload.shamsi_day} ${payload.shamsi_month_name} ${payload.shamsi_year} (${payload.shamsi})`;
}

/**
 * Format Gregorian + Shamsi together for UX.
 */
export function formatGregorianShamsi(payload: { gregorian: string; shamsi: string; shamsi_month_name: string }): string {
  return `${payload.gregorian} · ${payload.shamsi} ${payload.shamsi_month_name}`;
}

/**
 * Validate Gregorian YYYY-MM-DD — no time, no timezone.
 */
export function isValidGregorianDate(value: string): boolean {
  return /^\d{4}-\d{2}-\d{2}$/.test(value) && !Number.isNaN(Date.parse(value));
}

export const CALENDAR_FALLBACK: CalendarTodayPayload = {
  gregorian: '1970-01-01',
  shamsi: '1348-10-11',
  shamsi_year: 1348,
  shamsi_month: 10,
  shamsi_day: 11,
  shamsi_month_name: 'Jadi',
  version: 'fallback',
  kabul_offset_minutes: 270,
  kabul_timezone: 'Asia/Kabul',
  utc_now: '',
  kabul_now: '',
};

let calendarTodayCache: CalendarTodayPayload | null = null;
let calendarTodayInflight: Promise<CalendarTodayPayload> | null = null;

export async function fetchCalendarToday(apiBase = '/api/v1'): Promise<CalendarTodayPayload> {
  if (calendarTodayCache) return calendarTodayCache;
  if (calendarTodayInflight) return calendarTodayInflight;
  calendarTodayInflight = (async () => {
    try {
      const resp = await fetch(`${apiBase}/calendar/today`, { headers: { Accept: 'application/json' } });
      if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
      const json = (await resp.json()) as { data: CalendarTodayPayload };
      calendarTodayCache = json.data;
      return calendarTodayCache;
    } catch {
      return CALENDAR_FALLBACK;
    } finally {
      calendarTodayInflight = null;
    }
  })();
  return calendarTodayInflight;
}

export function getCachedCalendarToday(): CalendarTodayPayload | null {
  return calendarTodayCache;
}

export function authoritativeToday(): string {
  if (calendarTodayCache) return calendarTodayCache.gregorian;
  if (typeof console !== 'undefined' && console.warn) {
    console.warn('[CAL-01] CalendarAuthority not yet loaded — using fallback; fetch /api/v1/calendar/today for authoritative business date');
  }
  return CALENDAR_FALLBACK.gregorian;
}

export function setCalendarTodayCache(payload: CalendarTodayPayload): void {
  calendarTodayCache = payload;
}

/**
 * Safe today fallback — ONLY for non-business UI (e.g., input min/max), never for business meaning.
 * Business meaning must come from server's CalendarTodayPayload.gregorian.
 */
export function unsafeTodayFallback(): string {
  if (typeof console !== 'undefined' && console.warn) {
    console.warn('[CAL-01] Using unsafe browser UTC date fallback — business meaning should come from server CalendarAuthority');
  }
  return new Date().toISOString().slice(0, 10);
}

export function formatKabulDateTime(isoString: string): string {
  try {
    return new Intl.DateTimeFormat('en-GB', {
      timeZone: 'Asia/Kabul',
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
    }).format(new Date(isoString));
  } catch {
    return isoString;
  }
}
