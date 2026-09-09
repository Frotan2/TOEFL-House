export type ApiErrorPayload = {
  message?: string;
  error?: string;
  category?: string;
  correlation_id?: string;
  retryable?: boolean;
};

export class ApiError extends Error {
  readonly status: number;
  readonly code: string | null;
  readonly category: string | null;
  readonly correlationId: string | null;
  readonly retryable: boolean;

  constructor(status: number, payload: ApiErrorPayload, fallback: string) {
    const code = payload.error ?? null;
    const message = payload.message ?? code ?? fallback;
    const suffix = code && payload.message && code !== payload.message ? ` (${code})` : '';
    super(`${message}${suffix}`);
    this.name = 'ApiError';
    this.status = status;
    this.code = code;
    this.category = payload.category ?? null;
    this.correlationId = payload.correlation_id ?? null;
    this.retryable = payload.retryable === true;
  }
}

export type ApiClient = {
  getJson: <T>(path: string) => Promise<T>;
  postJson: <T>(path: string, body?: Record<string, unknown>, idempotencyKey?: string) => Promise<T>;
};

type ApiClientConfig = {
  apiBase: string;
  csrfToken: string;
  timeoutMs?: number;
};

const DEFAULT_TIMEOUT_MS = 30_000;

function requestId(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') return crypto.randomUUID();
  return `${Date.now().toString(36)}-${Math.random().toString(36).slice(2)}`;
}

async function readResponse<T>(response: Response): Promise<T> {
  const contentType = response.headers.get('content-type') ?? '';
  if (contentType.toLowerCase().includes('application/json')) {
    const payload = await response.json() as ApiErrorPayload & T;
    if (!response.ok) throw new ApiError(response.status, payload, `Request failed with ${response.status}`);
    return payload as T;
  }

  const text = await response.text();
  if (response.redirected && response.url.endsWith('/login')) {
    throw new ApiError(401, { error: 'authentication_required', message: 'Your session has expired. Please sign in again.' }, 'Authentication required');
  }
  if (!response.ok) throw new ApiError(response.status, {}, text.trim() || `Request failed with ${response.status}`);
  throw new ApiError(response.status, {}, text.trim() || `Request returned a non-JSON response (${response.status})`);
}

async function request<T>(url: string, init: RequestInit, timeoutMs: number): Promise<T> {
  const controller = new AbortController();
  const timeout = window.setTimeout(() => controller.abort(), timeoutMs);
  try {
    return await readResponse<T>(await fetch(url, { ...init, signal: controller.signal }));
  } catch (reason: unknown) {
    if (reason instanceof DOMException && reason.name === 'AbortError') {
      throw new ApiError(408, { error: 'request_timeout', message: 'The request took too long. Please retry.' }, 'Request timed out');
    }
    throw reason;
  } finally {
    window.clearTimeout(timeout);
  }
}

export function createApiClient(config: ApiClientConfig): ApiClient {
  const timeoutMs = config.timeoutMs ?? DEFAULT_TIMEOUT_MS;
  const getJson = async <T>(path: string): Promise<T> => request<T>(`${config.apiBase}${path}`, {
    credentials: 'same-origin',
    cache: 'no-store',
    headers: { Accept: 'application/json' },
  }, timeoutMs);

  const postJson = async <T>(path: string, body?: Record<string, unknown>, idempotencyKey?: string): Promise<T> => request<T>(`${config.apiBase}${path}`, {
    method: 'POST',
    credentials: 'same-origin',
    cache: 'no-store',
    headers: {
      Accept: 'application/json',
      ...(body ? { 'Content-Type': 'application/json' } : {}),
      'X-CSRF-TOKEN': config.csrfToken,
      'X-Requested-With': 'XMLHttpRequest',
      'Idempotency-Key': idempotencyKey ?? `${path.replaceAll('/', '.')}-${requestId()}`,
    },
    ...(body ? { body: JSON.stringify(body) } : {}),
  }, timeoutMs);

  return { getJson, postJson };
}
