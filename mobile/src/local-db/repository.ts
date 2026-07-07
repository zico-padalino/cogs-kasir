import * as SQLite from 'expo-sqlite';
import type {
  LocalCartItem,
  LocalOrder,
  LocalOrderItem,
  LocalProduct,
  OnlineOrderInput,
} from '@/local-db/types';
import { ensureSchema, seedProductsIfEmpty } from '@/local-db/seed';

let databasePromise: Promise<SQLite.SQLiteDatabase> | null = null;

export async function getLocalDatabase(): Promise<SQLite.SQLiteDatabase> {
  if (!databasePromise) {
    databasePromise = (async () => {
      const db = await SQLite.openDatabaseAsync('pos_local.db');
      await ensureSchema(db);
      await seedProductsIfEmpty(db);

      return db;
    })();
  }

  return databasePromise;
}

export async function getLocalProducts(): Promise<LocalProduct[]> {
  const db = await getLocalDatabase();

  return db.getAllAsync<LocalProduct>(
    'SELECT id, name, category, price, emoji FROM products ORDER BY category, name',
  );
}

export async function getLocalCart(): Promise<LocalCartItem[]> {
  const db = await getLocalDatabase();

  return db.getAllAsync<LocalCartItem>(`
    SELECT
      c.id,
      c.product_id,
      p.name,
      p.category,
      p.emoji,
      c.quantity,
      c.unit_price,
      (c.quantity * c.unit_price) AS line_total,
      c.notes
    FROM cart_items c
    INNER JOIN products p ON p.id = c.product_id
    ORDER BY c.id ASC
  `);
}

export async function addProductToLocalCart(productId: number, quantity = 1): Promise<void> {
  const db = await getLocalDatabase();
  const product = await db.getFirstAsync<LocalProduct>(
    'SELECT id, name, category, price, emoji FROM products WHERE id = ?',
    productId,
  );

  if (!product) {
    return;
  }

  const existing = await db.getFirstAsync<{ id: number; quantity: number }>(
    'SELECT id, quantity FROM cart_items WHERE product_id = ?',
    productId,
  );

  if (existing) {
    await db.runAsync(
      'UPDATE cart_items SET quantity = ? WHERE id = ?',
      existing.quantity + quantity,
      existing.id,
    );

    return;
  }

  await db.runAsync(
    'INSERT INTO cart_items (product_id, quantity, unit_price) VALUES (?, ?, ?)',
    productId,
    quantity,
    product.price,
  );
}

export async function updateLocalCartQuantity(cartItemId: number, quantity: number): Promise<void> {
  const db = await getLocalDatabase();

  if (quantity <= 0) {
    await db.runAsync('DELETE FROM cart_items WHERE id = ?', cartItemId);

    return;
  }

  await db.runAsync('UPDATE cart_items SET quantity = ? WHERE id = ?', quantity, cartItemId);
}

export async function removeLocalCartItem(cartItemId: number): Promise<void> {
  const db = await getLocalDatabase();
  await db.runAsync('DELETE FROM cart_items WHERE id = ?', cartItemId);
}

export async function clearLocalCart(): Promise<void> {
  const db = await getLocalDatabase();
  await db.runAsync('DELETE FROM cart_items');
}

async function nextLocalOrderNumber(db: SQLite.SQLiteDatabase, orderDay: string): Promise<string> {
  const row = await db.getFirstAsync<{ max_number: number | null }>(
    `SELECT MAX(CAST(order_number AS INTEGER)) AS max_number
     FROM local_orders
     WHERE order_day = ?`,
    orderDay,
  );

  const next = (row?.max_number ?? 0) + 1;

  return next < 1000
    ? String(next).padStart(3, '0')
    : String(next);
}

export async function checkoutLocalCart(input: {
  customerName?: string;
  paymentMethod: 'cash' | 'qris' | 'transfer';
  amountReceived?: number;
}): Promise<LocalOrder> {
  const db = await getLocalDatabase();
  const cart = await getLocalCart();

  if (cart.length === 0) {
    throw new Error('Keranjang masih kosong.');
  }

  const subtotal = cart.reduce((sum, item) => sum + item.line_total, 0);
  const total = subtotal;
  let changeAmount: number | null = null;

  if (input.paymentMethod === 'cash') {
    const received = input.amountReceived ?? 0;

    if (received < total) {
      throw new Error('Uang diterima harus minimal sebesar total tagihan.');
    }

    changeAmount = received - total;
  }

  const now = new Date();
  const orderDay = now.toISOString().slice(0, 10);
  const orderNumber = await nextLocalOrderNumber(db, orderDay);
  const createdAt = now.toISOString();

  await db.withTransactionAsync(async () => {
    const result = await db.runAsync(
      `INSERT INTO local_orders (
        order_number, order_day, customer_name, subtotal, total,
        payment_method, amount_received, change_amount, status, source, created_at
      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'paid', 'kasir', ?)`,
      orderNumber,
      orderDay,
      input.customerName?.trim() || null,
      subtotal,
      total,
      input.paymentMethod,
      input.amountReceived ?? null,
      changeAmount,
      createdAt,
    );

    const orderId = result.lastInsertRowId;

    for (const item of cart) {
      await db.runAsync(
        `INSERT INTO local_order_items (order_id, product_name, quantity, unit_price, line_total)
         VALUES (?, ?, ?, ?, ?)`,
        orderId,
        item.name,
        item.quantity,
        item.unit_price,
        item.line_total,
      );
    }

    await db.runAsync('DELETE FROM cart_items');
  });

  const order = await db.getFirstAsync<LocalOrder>(
    'SELECT * FROM local_orders WHERE order_number = ? AND order_day = ?',
    orderNumber,
    orderDay,
  );

  if (!order) {
    throw new Error('Gagal menyimpan pesanan lokal.');
  }

  return order;
}

export async function submitOnlineOrder(input: OnlineOrderInput): Promise<LocalOrder> {
  const db = await getLocalDatabase();

  if (input.items.length === 0) {
    throw new Error('Pilih minimal satu menu dulu.');
  }

  const products = await getLocalProducts();
  const priceById = new Map(products.map((product) => [product.id, product]));

  const lines = input.items
    .map((item) => {
      const product = priceById.get(item.productId);

      if (!product || item.quantity <= 0) {
        return null;
      }

      return {
        name: product.name,
        quantity: item.quantity,
        unit_price: product.price,
        line_total: product.price * item.quantity,
      };
    })
    .filter((line): line is NonNullable<typeof line> => line !== null);

  if (lines.length === 0) {
    throw new Error('Menu yang dipilih tidak valid.');
  }

  const subtotal = lines.reduce((sum, line) => sum + line.line_total, 0);
  const now = new Date();
  const orderDay = now.toISOString().slice(0, 10);
  const orderNumber = await nextLocalOrderNumber(db, orderDay);
  const createdAt = now.toISOString();

  await db.withTransactionAsync(async () => {
    const result = await db.runAsync(
      `INSERT INTO local_orders (
        order_number, order_day, customer_name, subtotal, total,
        payment_method, amount_received, change_amount, status, source, created_at
      ) VALUES (?, ?, ?, ?, ?, 'unpaid', NULL, NULL, 'submitted', 'online', ?)`,
      orderNumber,
      orderDay,
      input.customerName?.trim() || null,
      subtotal,
      subtotal,
      createdAt,
    );

    const orderId = result.lastInsertRowId;

    for (const line of lines) {
      await db.runAsync(
        `INSERT INTO local_order_items (order_id, product_name, quantity, unit_price, line_total)
         VALUES (?, ?, ?, ?, ?)`,
        orderId,
        line.name,
        line.quantity,
        line.unit_price,
        line.line_total,
      );
    }
  });

  const order = await db.getFirstAsync<LocalOrder>(
    'SELECT * FROM local_orders WHERE order_number = ? AND order_day = ?',
    orderNumber,
    orderDay,
  );

  if (!order) {
    throw new Error('Gagal menyimpan pesanan online.');
  }

  return order;
}

export async function getIncomingOnlineOrders(): Promise<LocalOrder[]> {
  const db = await getLocalDatabase();

  return db.getAllAsync<LocalOrder>(
    "SELECT * FROM local_orders WHERE status = 'submitted' ORDER BY datetime(created_at) ASC",
  );
}

export async function getIncomingOnlineOrderCount(): Promise<number> {
  const db = await getLocalDatabase();
  const row = await db.getFirstAsync<{ count: number }>(
    "SELECT COUNT(*) AS count FROM local_orders WHERE status = 'submitted'",
  );

  return row?.count ?? 0;
}

export async function payOnlineOrder(
  orderId: number,
  input: { paymentMethod: 'cash' | 'qris' | 'transfer'; amountReceived?: number },
): Promise<void> {
  const db = await getLocalDatabase();
  const order = await db.getFirstAsync<LocalOrder>('SELECT * FROM local_orders WHERE id = ?', orderId);

  if (!order) {
    throw new Error('Pesanan tidak ditemukan.');
  }

  let changeAmount: number | null = null;

  if (input.paymentMethod === 'cash') {
    const received = input.amountReceived ?? 0;

    if (received < order.total) {
      throw new Error('Uang diterima harus minimal sebesar total tagihan.');
    }

    changeAmount = received - order.total;
  }

  await db.runAsync(
    `UPDATE local_orders
     SET status = 'paid', payment_method = ?, amount_received = ?, change_amount = ?
     WHERE id = ?`,
    input.paymentMethod,
    input.amountReceived ?? null,
    changeAmount,
    orderId,
  );
}

export async function getLocalOrders(): Promise<LocalOrder[]> {
  const db = await getLocalDatabase();

  return db.getAllAsync<LocalOrder>(
    "SELECT * FROM local_orders WHERE status = 'paid' ORDER BY datetime(created_at) DESC LIMIT 50",
  );
}

export async function getLocalOrderItems(orderId: number): Promise<LocalOrderItem[]> {
  const db = await getLocalDatabase();

  return db.getAllAsync<LocalOrderItem>(
    'SELECT * FROM local_order_items WHERE order_id = ? ORDER BY id ASC',
    orderId,
  );
}

export async function getLocalStorageStats(): Promise<{ products: number; orders: number }> {
  const db = await getLocalDatabase();
  const products = await db.getFirstAsync<{ count: number }>('SELECT COUNT(*) AS count FROM products');
  const orders = await db.getFirstAsync<{ count: number }>('SELECT COUNT(*) AS count FROM local_orders');

  return {
    products: products?.count ?? 0,
    orders: orders?.count ?? 0,
  };
}
