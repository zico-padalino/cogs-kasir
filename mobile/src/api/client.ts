import * as SecureStore from 'expo-secure-store';
import type { ApiError } from './types';

const TOKEN_KEY = 'kasir_api_token';

type PinLockedListener = () => void;

let pinLockedListener: PinLockedListener | null = null;

/** Dipanggil AuthProvider: saat API 423/PIN_LOCKED, set state PIN terkunci + redirect. */
export function setPinLockedListener(listener: PinLockedListener | null): void {
  pinLockedListener = listener;
}

export function isPinSessionError(err: unknown): boolean {
  const e = err as ApiError | undefined;
  return e?.status === 423 || e?.code === 'PIN_LOCKED';
}

function defaultBaseUrl(): string {
  const fromEnv = process.env.EXPO_PUBLIC_API_URL?.replace(/\/$/, '');
  if (fromEnv) {
    return fromEnv;
  }

  return 'https://kedaitjoan.online/api/v1';
}

export function getApiBaseUrl(): string {
  return defaultBaseUrl();
}

export async function getToken(): Promise<string | null> {
  try {
    return await SecureStore.getItemAsync(TOKEN_KEY);
  } catch {
    return null;
  }
}

export async function setToken(token: string | null): Promise<void> {
  if (!token) {
    await SecureStore.deleteItemAsync(TOKEN_KEY);
    return;
  }
  await SecureStore.setItemAsync(TOKEN_KEY, token);
}

type RequestOptions = {
  method?: string;
  body?: unknown;
  formData?: FormData;
  auth?: boolean;
  signal?: AbortSignal;
};

export async function apiRequest<T>(path: string, options: RequestOptions = {}): Promise<T> {
  const { method = 'GET', body, formData, auth = true, signal } = options;
  const headers: Record<string, string> = {
    Accept: 'application/json',
  };

  if (auth) {
    const token = await getToken();
    if (token) {
      headers.Authorization = `Bearer ${token}`;
    }
  }

  let payload: BodyInit | undefined;
  if (formData) {
    payload = formData;
  } else if (body !== undefined) {
    headers['Content-Type'] = 'application/json';
    payload = JSON.stringify(body);
  }

  const response = await fetch(`${getApiBaseUrl()}${path.startsWith('/') ? path : `/${path}`}`, {
    method,
    headers,
    body: payload,
    signal,
  });

  const text = await response.text();
  let json: unknown = null;
  if (text) {
    try {
      json = JSON.parse(text);
    } catch {
      json = { message: text };
    }
  }

  if (!response.ok) {
    const bodyPayload = json as {
      message?: string;
      code?: string;
      errors?: Record<string, string[]>;
    };
    const firstFieldError = bodyPayload?.errors
      ? Object.values(bodyPayload.errors).flat()[0]
      : undefined;
    const err = new Error(firstFieldError || bodyPayload?.message || `HTTP ${response.status}`) as ApiError;
    err.status = response.status;
    err.code = bodyPayload?.code;
    err.payload = json;

    // Mirror web: sesi PIN habis → langsung ke halaman PIN (bukan alert "Gagal")
    if (response.status === 423 || bodyPayload?.code === 'PIN_LOCKED') {
      pinLockedListener?.();
    }

    throw err;
  }

  return json as T;
}
