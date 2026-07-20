import { getApiBaseUrl } from '@/api/client';

/**
 * Ubah path relatif (/uploads/...) jadi URL absolut agar Image di APK bisa load logo toko.
 */
export function resolveMediaUrl(url?: string | null): string | null {
  if (!url) {
    return null;
  }

  const trimmed = url.trim();
  if (!trimmed) {
    return null;
  }

  if (/^https?:\/\//i.test(trimmed)) {
    return trimmed;
  }

  const origin = getApiBaseUrl().replace(/\/api\/v1\/?$/, '');
  if (trimmed.startsWith('/')) {
    return `${origin}${trimmed}`;
  }

  return `${origin}/${trimmed}`;
}
