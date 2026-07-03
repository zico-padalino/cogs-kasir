export type LocalProduct = {
  id: number;
  name: string;
  category: string;
  price: number;
  emoji: string;
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
  subtotal: number;
  total: number;
  payment_method: string;
  amount_received: number | null;
  change_amount: number | null;
  status: string;
  created_at: string;
};

export type LocalOrderItem = {
  id: number;
  order_id: number;
  product_name: string;
  quantity: number;
  unit_price: number;
  line_total: number;
};
