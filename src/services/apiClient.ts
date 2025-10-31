import { APP_CONFIG } from '@/constants/config';

const normalizeBaseUrl = (value: string) => value.replace(/\/$/, '');

const buildUrl = (path: string) => {
  const base = APP_CONFIG.endpoints.backendBaseUrl
    ? normalizeBaseUrl(APP_CONFIG.endpoints.backendBaseUrl)
    : '';

  if (/^https?:/i.test(path)) {
    return path;
  }

  const normalizedPath = path.startsWith('/') ? path : `/${path}`;
  return `${base}${normalizedPath}`;
};

interface RequestOptions extends RequestInit {
  skipJson?: boolean;
}

const handleResponse = async <T>(response: Response, skipJson?: boolean): Promise<T> => {
  if (!response.ok) {
    const message = await response.text();
    throw new Error(message || `Request failed with status ${response.status}`);
  }

  if (skipJson) {
    return undefined as T;
  }

  return (await response.json()) as T;
};

export const apiClient = {
  async post<T>(path: string, body: unknown, options: RequestOptions = {}) {
    const response = await fetch(buildUrl(path), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        ...options.headers,
      },
      body: JSON.stringify(body),
      ...options,
    });

    return handleResponse<T>(response, options.skipJson);
  },

  async upload<T>(path: string, formData: FormData, options: RequestOptions = {}) {
    const response = await fetch(buildUrl(path), {
      method: 'POST',
      body: formData,
      ...options,
    });

    return handleResponse<T>(response, options.skipJson);
  },
};
