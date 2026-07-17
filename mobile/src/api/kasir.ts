import { apiRequest } from './client';
import type {
  AuthUser,
  MenuProduct,
  PinStatus,
  PosBootstrap,
  PosOrder,
} from './types';

type Envelope<T> = { message?: string; data: T };

export const authApi = {
  login(email: string, password: string) {
    return apiRequest<Envelope<{ token: string; token_type: string; user: AuthUser }>>('/auth/login', {
      method: 'POST',
      body: { email, password },
      auth: false,
    });
  },
  me() {
    return apiRequest<Envelope<{ user: AuthUser; shop: { name: string; title?: string; logo_url?: string }; pin: PinStatus }>>(
      '/auth/me',
    );
  },
  logout() {
    return apiRequest<{ message: string }>('/auth/logout', { method: 'POST' });
  },
};

export const pinApi = {
  show() {
    return apiRequest<Envelope<PinStatus & { ttl_minutes: number; shop_name?: string }>>('/kasir/pin');
  },
  status() {
    return apiRequest<Envelope<PinStatus & { ttl_minutes: number }>>('/kasir/pin/status');
  },
  unlock(pin: string) {
    return apiRequest<Envelope<PinStatus & { ttl_minutes: number; operator?: { id: number; name: string } }>>(
      '/kasir/pin',
      { method: 'POST', body: { pin } },
    );
  },
  lock() {
    return apiRequest<Envelope<PinStatus>>('/kasir/pin/lock', { method: 'POST' });
  },
};

export const kasirApi = {
  pos() {
    return apiRequest<Envelope<PosBootstrap>>('/kasir/pos');
  },
  poll() {
    return apiRequest<
      Envelope<
        PinStatus & {
          count: number;
          total: number;
          order_ids: number[];
          has_pending: boolean;
          latest_order_id?: number | null;
          orders: PosOrder[];
          active_order_id?: number | null;
        }
      >
    >('/kasir/pending-orders/poll');
  },
  newOrder() {
    return apiRequest<Envelope<PosOrder>>('/kasir/orders/new', { method: 'POST' });
  },
  updateOrder(payload: { order_type: string; customer_note?: string | null }) {
    return apiRequest<Envelope<PosOrder>>('/kasir/orders/current', { method: 'PATCH', body: payload });
  },
  updateDiscount(payload: { discount_type?: string | null; discount_value?: number }) {
    return apiRequest<Envelope<PosOrder>>('/kasir/orders/discount', { method: 'PATCH', body: payload });
  },
  cancelOrder() {
    return apiRequest<Envelope<PosOrder>>('/kasir/orders/cancel', { method: 'POST' });
  },
  loadOrder(orderId: number) {
    return apiRequest<Envelope<PosOrder>>(`/kasir/orders/${orderId}/load`, { method: 'POST' });
  },
  confirmOrder(orderId: number) {
    return apiRequest<Envelope<PosOrder>>(`/kasir/orders/${orderId}/confirm`, { method: 'POST' });
  },
  cancelPending(orderId: number) {
    return apiRequest<Envelope<{ active_order: PosOrder | null }>>(`/kasir/orders/${orderId}/cancel`, {
      method: 'POST',
    });
  },
  addItem(payload: { product_id: number; quantity: number; notes?: string; addon_ids?: number[] }) {
    return apiRequest<Envelope<PosOrder>>('/kasir/items', { method: 'POST', body: payload });
  },
  updateItem(itemId: number, payload: { quantity?: number; notes?: string | null }) {
    return apiRequest<Envelope<PosOrder>>(`/kasir/items/${itemId}`, { method: 'PATCH', body: payload });
  },
  removeItem(itemId: number) {
    return apiRequest<Envelope<PosOrder>>(`/kasir/items/${itemId}`, { method: 'DELETE' });
  },
  pay(formData: FormData) {
    return apiRequest<Envelope<PosOrder>>('/kasir/pay', { method: 'POST', formData });
  },
  receipt(orderId: number) {
    return apiRequest<
      Envelope<{ order: PosOrder; pdf_url: string; wa_message: string; shop_name: string }>
    >(`/kasir/orders/${orderId}/receipt`);
  },
  orders() {
    return apiRequest<{ data: PosOrder[]; meta?: unknown; links?: unknown }>('/kasir/orders');
  },
  order(orderId: number) {
    return apiRequest<Envelope<PosOrder>>(`/kasir/orders/${orderId}`);
  },
  tables() {
    return apiRequest<
      Envelope<{
        tables: { id: number; table_number: string; label: string; is_active: boolean; open_orders_count: number }[];
        order_url: string;
        shop_name: string;
        shop_title?: string;
      }>
    >('/kasir/tables');
  },
  createTable(payload: { table_number: string; label: string }) {
    return apiRequest<Envelope<{ id: number; table_number: string; label: string }>>('/kasir/tables', {
      method: 'POST',
      body: payload,
    });
  },
  products() {
    return apiRequest<
      Envelope<{ products: MenuProduct[]; menu_categories: Record<string, string> | { slug: string; name: string }[] }>
    >('/kasir/products');
  },
  product(productId: number) {
    return apiRequest<Envelope<MenuProduct & { presets?: Record<string, string> }>>(`/kasir/products/${productId}`);
  },
  updateProduct(productId: number, formData: FormData) {
    formData.append('_method', 'PUT');
    return apiRequest<Envelope<MenuProduct>>(`/kasir/products/${productId}`, {
      method: 'POST',
      formData,
    });
  },
  categories() {
    return apiRequest<Envelope<{ id: number; name: string; slug: string; sort_order: number; product_count: number }[]>>(
      '/kasir/menu-categories',
    );
  },
  createCategory(name: string) {
    return apiRequest<Envelope<{ id: number; name: string; slug: string }>>('/kasir/menu-categories', {
      method: 'POST',
      body: { name },
    });
  },
  deleteCategory(id: number) {
    return apiRequest<{ message: string }>(`/kasir/menu-categories/${id}`, { method: 'DELETE' });
  },
  pembukuan(params: Record<string, string> = {}) {
    const qs = new URLSearchParams(params).toString();
    return apiRequest<Envelope<Record<string, unknown>>>(`/kasir/pembukuan${qs ? `?${qs}` : ''}`);
  },
  kasTunai(date?: string) {
    const qs = date ? `?date=${encodeURIComponent(date)}` : '';
    return apiRequest<Envelope<Record<string, unknown>>>(`/kasir/kas-tunai${qs}`);
  },
  kasFloat(payload: { amount: number; note: string }) {
    return apiRequest<Envelope<{ balance: number }>>('/kasir/kas-tunai/float', { method: 'POST', body: payload });
  },
  kasExpense(payload: { amount: number; note: string }) {
    return apiRequest<Envelope<{ balance: number }>>('/kasir/kas-tunai/expense', { method: 'POST', body: payload });
  },
};
