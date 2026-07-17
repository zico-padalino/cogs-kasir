/**
 * Client helper untuk modul COGS & Admin.
 * Base path: /api/v1/cogs/* dan /api/v1/admin/*
 */
import { apiRequest } from './client';

type Envelope<T> = { message?: string; data: T };

export const cogsApi = {
  dashboard: () => apiRequest<Envelope<Record<string, unknown>>>('/cogs/dashboard'),
  products: () => apiRequest<Envelope<unknown>>('/cogs/products'),
  product: (id: number) => apiRequest<Envelope<unknown>>(`/cogs/products/${id}`),
  storeProduct: (body: unknown) => apiRequest<Envelope<unknown>>('/cogs/products', { method: 'POST', body }),
  updateProduct: (id: number, body: unknown) =>
    apiRequest<Envelope<unknown>>(`/cogs/products/${id}`, { method: 'PUT', body }),
  deleteProduct: (id: number) => apiRequest<{ message: string }>(`/cogs/products/${id}`, { method: 'DELETE' }),
  storeBom: (productId: number, body: unknown) =>
    apiRequest<Envelope<unknown>>(`/cogs/products/${productId}/bom`, { method: 'POST', body }),
  updateBom: (productId: number, bomId: number, body: unknown) =>
    apiRequest<Envelope<unknown>>(`/cogs/products/${productId}/bom/${bomId}`, { method: 'PUT', body }),
  deleteBom: (productId: number, bomId: number) =>
    apiRequest<{ message: string }>(`/cogs/products/${productId}/bom/${bomId}`, { method: 'DELETE' }),
  storeAddon: (productId: number, body: unknown) =>
    apiRequest<Envelope<unknown>>(`/cogs/products/${productId}/addons`, { method: 'POST', body }),
  materials: () => apiRequest<Envelope<unknown>>('/cogs/materials'),
  materialsHistory: (qs = '') => apiRequest<Envelope<unknown>>(`/cogs/materials/history${qs}`),
  receiveStock: (body: unknown) => apiRequest<Envelope<unknown>>('/cogs/materials/stock', { method: 'POST', body }),
  productionOrders: () => apiRequest<Envelope<unknown>>('/cogs/production-orders'),
  startProduction: (id: number) =>
    apiRequest<Envelope<unknown>>(`/cogs/production-orders/${id}/start`, { method: 'POST' }),
  completeProduction: (id: number, body?: unknown) =>
    apiRequest<Envelope<unknown>>(`/cogs/production-orders/${id}/complete`, { method: 'POST', body }),
  menuPricing: () => apiRequest<Envelope<unknown>>('/cogs/menu-pricing'),
  updateMenuPrice: (productId: number, body: unknown) =>
    apiRequest<Envelope<unknown>>(`/cogs/menu-pricing/${productId}`, { method: 'PUT', body }),
  calculate: (body: unknown) => apiRequest<Envelope<unknown>>('/cogs/calculate', { method: 'POST', body }),
  history: () => apiRequest<Envelope<unknown>>('/cogs/history'),
  overheadRates: () => apiRequest<Envelope<unknown>>('/cogs/overhead-rates'),
  resetData: (confirmation = 'RESET') =>
    apiRequest<Envelope<unknown>>('/cogs/reset-data', { method: 'POST', body: { confirmation } }),
};

export const adminApi = {
  dashboard: (params: Record<string, string> = {}) => {
    const qs = new URLSearchParams(params).toString();
    return apiRequest<Envelope<unknown>>(`/admin/dashboard${qs ? `?${qs}` : ''}`);
  },
  employees: () => apiRequest<Envelope<unknown>>('/admin/employees'),
  storeEmployee: (body: unknown) => apiRequest<Envelope<unknown>>('/admin/employees', { method: 'POST', body }),
  updateEmployee: (id: number, body: unknown) =>
    apiRequest<Envelope<unknown>>(`/admin/employees/${id}`, { method: 'PUT', body }),
  deleteEmployee: (id: number) => apiRequest<{ message: string }>(`/admin/employees/${id}`, { method: 'DELETE' }),
  enrollFace: (id: number, body: unknown) =>
    apiRequest<Envelope<unknown>>(`/admin/employees/${id}/face`, { method: 'POST', body }),
  attendances: (params: Record<string, string> = {}) => {
    const qs = new URLSearchParams(params).toString();
    return apiRequest<Envelope<unknown>>(`/admin/attendances${qs ? `?${qs}` : ''}`);
  },
  attendanceQr: () => apiRequest<Envelope<unknown>>('/admin/attendances/qr'),
  salaries: () => apiRequest<Envelope<unknown>>('/admin/salaries'),
  users: () => apiRequest<Envelope<unknown>>('/admin/users'),
  settings: () => apiRequest<Envelope<unknown>>('/admin/settings'),
  updateSettings: (body: unknown) => apiRequest<Envelope<unknown>>('/admin/settings', { method: 'PUT', body }),
};
