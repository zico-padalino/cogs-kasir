export type PaymentMethod = 'cash' | 'qris' | 'transfer';
export type OrderType = 'dine_in' | 'takeaway';
export type OrderSource = 'kasir' | 'online';
export type OrderStatus = 'open' | 'submitted' | 'confirmed' | 'paid' | 'cancelled';

export type LocalProduct = {
  id: number;
  name: string;
  category: string;
  price: number;
  emoji: string;
  description: string | null;
  is_active: number;
};

export type LocalCartItem = {
  id: number;
  product_id: number;
  name: string;
  category: string;
  emoji: string;
  quantity: number;
  unit_price: number;
  line_total: number;
  notes: string | null;
};

export type LocalOrder = {
  id: number;
  order_number: string;
  customer_name: string | null;
  order_type: OrderType;
  table_label: string | null;
  subtotal: number;
  total: number;
  payment_method: string;
  amount_received: number | null;
  change_amount: number | null;
  status: OrderStatus;
  source: OrderSource;
  confirmed_at: string | null;
  paid_at: string | null;
  created_at: string;
};

export type OnlineOrderInput = {
  customerName?: string;
  items: { productId: number; quantity: number }[];
};

export type LocalOrderItem = {
  id: number;
  order_id: number;
  product_name: string;
  quantity: number;
  unit_price: number;
  line_total: number;
};

export type LocalTable = {
  id: number;
  table_number: string;
  label: string;
  is_active: number;
  open_orders: number;
};
