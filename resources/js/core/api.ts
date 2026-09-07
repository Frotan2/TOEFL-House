export type ApiErrorPayload = { message?: string; error?: string; category?: string; correlation_id?: string; retryable?: boolean };

export class ApiError extends Error {
  readonly status: number;
  readonly code: string | null;
  readonly category: string | null;
  readonly correlationId: string | null;
  readonly retryable: boolean;

  constructor(status: number, payload: ApiErrorPayload, fallback: string) {
    const code = payload.error ?? null;
    super(`${payload.message ?? code ?? fallback}${code && payload.message && code !== payload.message ? ` (${code})` : ''}`);
    this.name = 'ApiError';
    this.status = status;
    this.code = code;
    this.category = payload.category ?? null;
    this.correlationId = payload.correlation_id ?? null;
    this.retryable = payload.retryable === true;
  }
}

export type ApiClientOptions = { baseUrl?: string; csrfToken?: string; idempotencyPrefix?: string };

export function createApiClient(options: ApiClientOptions = {}) {
  const baseUrl = options.baseUrl ?? '/api/v1';
  const csrfToken = options.csrfToken ?? '';
  const prefix = options.idempotencyPrefix ?? 'console';

  async function read<T>(response: Response): Promise<T> {
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
    throw new ApiError(response.status, {}, text.trim() || 'Request returned a non-JSON response');
  }

  async function get<T>(path: string): Promise<T> {
    return read(await fetch(`${baseUrl}${path}`, { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' } }));
  }

  async function post<T>(path: string, body?: Record<string, unknown>): Promise<T> {
    return read(await fetch(`${baseUrl}${path}`, {
      method: 'POST', credentials: 'same-origin', cache: 'no-store',
      headers: {
        Accept: 'application/json',
        ...(body ? { 'Content-Type': 'application/json' } : {}),
        'X-CSRF-TOKEN': csrfToken,
        'X-Requested-With': 'XMLHttpRequest',
        'Idempotency-Key': `${prefix}-${crypto.randomUUID()}`,
      },
      ...(body ? { body: JSON.stringify(body) } : {}),
    }));
  }

  return { get, post } as const;
}
