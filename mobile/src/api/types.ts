export type Role = 'cogs' | 'kasir' | 'admin';

export type AuthUser = {
  id: number;
  name: string;
  email: string;
  role: Role | string;
  modules: string[];
  must_change_password: boolean;
  is_root: boolean;
  has_kasir: boolean;
  has_cogs: boolean;
  has_admin: boolean;
};

export type PinStatus = {
  unlocked: boolean;
  expires_at: number | null;
  server_now: number;
  remaining_seconds: number;
  operator_name?: string | null;
  ttl_minutes?: number;
};

export type ProductAddon = {
  id: number;
  name: string;
  price: number;
};

export type MenuProduct = {
  id: number;
  sku?: string | null;
  name: string;
  description?: string | null;
  menu_category?: string | null;
  selling_price: number;
  unit_hpp?: number;
  image_url: string;
  image_path?: string | null;
  is_active?: boolean;
  stock_qty?: number;
  stock_tracked?: boolean;
  in_stock?: boolean;
  can_add?: boolean;
  is_sold_out?: boolean;
  addons?: ProductAddon[];
  gross_margin?: number;
  margin_percent?: number;
};

export type OrderItem = {
  id: number;
  product_id: number;
  product_name?: string | null;
  product_image_url?: string | null;
  quantity: number;
  unit_price: number;
  line_total: number;
  notes?: string | null;
  addon_ids?: number[];
};

export type PosOrder = {
  id: number;
  order_number: string;
  order_day?: string | null;
  source?: string | null;
  order_type?: 'dine_in' | 'takeaway' | string | null;
  order_type_label?: string | null;
  order_type_icon?: string | null;
  status?: string | null;
  status_label?: string | null;
  status_badge?: string | null;
  customer_note?: string | null;
  subtotal: number;
  discount_type?: string | null;
  discount_value: number;
  discount_amount: number;
  has_discount?: boolean;
  total: number;
  amount_received?: number | null;
  change_amount?: number | null;
  payment_method?: string | null;
  payment_method_label?: string | null;
  payment_proof_url?: string | null;
  paid_at?: string | null;
  cashier_name?: string | null;
  item_count?: number;
  can_checkout?: boolean;
  is_editable?: boolean;
  is_open_bill?: boolean;
  table?: { id: number; table_number: string; label: string } | null;
  items?: OrderItem[];
  created_at?: string | null;
};

export type PosBootstrap = {
  order: PosOrder;
  products: MenuProduct[];
  menu_categories: string[];
  menu_category_labels: Record<string, string>;
  order_types: { value: string; label: string; icon: string }[];
  pending_orders: PosOrder[];
  shop_name: string;
  poll_interval_seconds: number;
  auto_load_new_order: boolean;
  pin: PinStatus;
};

export type ApiError = Error & {
  status?: number;
  code?: string;
  payload?: unknown;
};
