const API_BASE = import.meta.env.VITE_API_BASE ?? '';

export class ApiError extends Error {
  constructor(
    message: string,
    public status: number,
  ) {
    super(message);
  }
}

export async function api<T>(
  path: string,
  options: RequestInit = {},
  token?: string | null,
): Promise<T> {
  const headers = new Headers(options.headers);
  if (!headers.has('Content-Type') && options.body) {
    headers.set('Content-Type', 'application/json');
  }
  if (token) {
    headers.set('Authorization', `Bearer ${token}`);
  }

  const response = await fetch(`${API_BASE}${path}`, {
    ...options,
    headers,
  });

  const text = await response.text();
  let data: Record<string, unknown> = {};
  if (text !== '') {
    try {
      data = JSON.parse(text) as Record<string, unknown>;
    } catch {
      throw new ApiError(
        response.ok ? 'Invalid JSON response from API' : text.slice(0, 200) || 'Request failed',
        response.status,
      );
    }
  }

  if (!response.ok) {
    throw new ApiError(typeof data.error === 'string' ? data.error : 'Request failed', response.status);
  }
  return data as T;
}
