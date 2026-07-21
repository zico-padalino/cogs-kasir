import { apiRequest, getApiBaseUrl } from './client';
import type {
  AuthUser,
  MenuProduct,
  PinStatus,
  PosBootstrap,
  PosOrder,
} from './types';

type Envelope<T> = { message?: string; data: T };

export const authApi = {
  shop() {
    return apiRequest<
      Envelope<{ name: string; title: string; logo_url?: string | null; initial: string }>
    >('/auth/shop', { auth: false });
  },
  login(email: string, password: string) {
    return apiRequest<Envelope<{ token: string; token_type: string; user: AuthUser }>>('/auth/login', {
      method: 'POST',
      body: { email, password },
      auth: false,
    });
  },
  me() {
    return apiRequest<
      Envelope<{
        user: AuthUser;
        shop: { name: string; title?: string; logo_url?: string };
        pin: PinStatus;
        attendance?: {
          enabled: boolean;
          must_attend: boolean;
          profile_required: boolean;
          required_action: string | null;
        };
        modules?: { value: string; label: string }[];
      }>
    >('/auth/me');
  },
  logout() {
    return apiRequest<{ message: string }>('/auth/logout', { method: 'POST' });
  },
  changePassword(payload: { current_password: string; password: string; password_confirmation: string }) {
    return apiRequest<Envelope<{ must_change_password: boolean }>>('/auth/password', {
      method: 'PUT',
      body: payload,
    });
  },
  hub() {
    return apiRequest<Envelope<{ modules: { value: string; label: string; home: string }[]; default: string }>>(
      '/auth/hub',
    );
  },
  switchModule(module: string) {
    return apiRequest<Envelope<{ module: string; home: string }>>(`/auth/hub/${module}`, { method: 'POST' });
  },
  profileSetup() {
    return apiRequest<Envelope<Record<string, unknown>>>('/auth/profile-setup');
  },
  updateProfile(phone: string) {
    return apiRequest<Envelope<Record<string, unknown>>>('/auth/profile-setup', {
      method: 'PUT',
      body: { phone },
    });
  },
  pinSetup() {
    return apiRequest<Envelope<{ has_pin: boolean; can_use_kasir: boolean }>>('/auth/pin-setup');
  },
  updatePinSetup(payload: { current_password: string; pin: string; pin_confirmation: string }) {
    return apiRequest<Envelope<{ has_pin: boolean }>>('/auth/pin-setup', { method: 'PUT', body: payload });
  },
};

export const attendanceApi = {
  status() {
    return apiRequest<Envelope<Record<string, unknown>>>('/attendance/status');
  },
  scanShow() {
    return apiRequest<Envelope<Record<string, unknown>>>('/attendance/scan', { auth: false });
  },
  scanStore(payload: {
    employee_id: number;
    latitude: number;
    longitude: number;
    photo: string;
    mode: 'check_in' | 'check_out';
  }) {
    return apiRequest<Envelope<Record<string, unknown>>>('/attendance/scan', {
      method: 'POST',
      body: payload,
      auth: false,
    });
  },
};

export const pesanApi = {
  show(orderId?: number) {
    const qs = orderId ? `?order_id=${orderId}` : '';
    return apiRequest<Envelope<Record<string, unknown>>>(`/pesan${qs}`, { auth: false });
  },
  newOrder() {
    return apiRequest<Envelope<PosOrder>>('/pesan/new-order', { method: 'POST', auth: false });
  },
  updateCustomer(payload: { order_id: number; customer_note: string; order_type?: string }) {
    return apiRequest<Envelope<PosOrder>>('/pesan/customer', { method: 'PATCH', body: payload, auth: false });
  },
  addItem(payload: {
    order_id: number;
    product_id: number;
    quantity: number;
    notes?: string;
    addon_ids?: number[];
  }) {
    return apiRequest<Envelope<PosOrder>>('/pesan/items', { method: 'POST', body: payload, auth: false });
  },
  submit(payload: { order_id: number; customer_note: string; order_type: string }) {
    return apiRequest<Envelope<PosOrder>>('/pesan/submit', { method: 'POST', body: payload, auth: false });
  },
  status(orderId: number) {
    return apiRequest<Envelope<Record<string, unknown>>>(`/pesan/status?order_id=${orderId}`, { auth: false });
  },
};

export const pinApi = {
  show() {
    return apiRequest<Envelope<PinStatus & { ttl_minutes: number; shop_name?: string }>>('/kasir/pin');
  },
  status() {
    return apiRequest<Envelope<PinStatus & { ttl_minutes: number }>>('/kasir/pin/status');
  },
  touch() {
    return apiRequest<Envelope<PinStatus & { ttl_minutes: number }>>('/kasir/pin/touch', { method: 'POST' });
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
          notify_order_ids?: number[];
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
  openBill() {
    return apiRequest<
      Envelope<{ held_order: PosOrder; active_order: PosOrder; merged?: boolean }>
    >('/kasir/open-bill', { method: 'POST' });
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
  pembukuanPdf(params: Record<string, string> = {}) {
    const qs = new URLSearchParams(params).toString();
    return apiRequest<Envelope<Record<string, unknown>>>(`/kasir/pembukuan/pdf${qs ? `?${qs}` : ''}`);
  },
  receiptPdfUrl(orderId: number) {
    return `${getApiBaseUrl()}/kasir/orders/${orderId}/receipt/pdf`;
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
