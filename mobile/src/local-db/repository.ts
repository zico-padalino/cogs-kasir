import * as SQLite from 'expo-sqlite';
import type {
  LocalCartItem,
  LocalOrder,
  LocalOrderItem,
  LocalProduct,
  LocalTable,
  OnlineOrderInput,
  OrderType,
  PaymentMethod,
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

const PRODUCT_COLUMNS = 'id, name, category, price, emoji, description, is_active';

// Menu aktif untuk POS & Pesan Online.
export async function getLocalProducts(): Promise<LocalProduct[]> {
  const db = await getLocalDatabase();

  return db.getAllAsync<LocalProduct>(
    `SELECT ${PRODUCT_COLUMNS} FROM products WHERE is_active = 1 ORDER BY category, name`,
  );
}

// Seluruh menu (termasuk nonaktif) untuk layar Kelola Menu.
export async function getMenuProducts(): Promise<LocalProduct[]> {
  const db = await getLocalDatabase();

  return db.getAllAsync<LocalProduct>(
    `SELECT ${PRODUCT_COLUMNS} FROM products ORDER BY category, name`,
  );
}

export async function getLocalProduct(id: number): Promise<LocalProduct | null> {
  const db = await getLocalDatabase();

  return db.getFirstAsync<LocalProduct>(
    `SELECT ${PRODUCT_COLUMNS} FROM products WHERE id = ?`,
    id,
  );
}

export async function createLocalProduct(input: {
  name: string;
  category: string;
  price: number;
  emoji: string;
  description?: string | null;
}): Promise<void> {
  const db = await getLocalDatabase();
  await db.runAsync(
    'INSERT INTO products (name, category, price, emoji, description, is_active) VALUES (?, ?, ?, ?, ?, 1)',
    input.name,
    input.category,
    input.price,
    input.emoji || '☕',
    input.description?.trim() || null,
  );
}

export async function updateLocalProduct(
  id: number,
  input: { name: string; category: string; price: number; emoji: string; description?: string | null },
): Promise<void> {
  const db = await getLocalDatabase();
  await db.runAsync(
    'UPDATE products SET name = ?, category = ?, price = ?, emoji = ?, description = ? WHERE id = ?',
    input.name,
    input.category,
    input.price,
    input.emoji || '☕',
    input.description?.trim() || null,
    id,
  );
}

export async function toggleLocalProduct(id: number, isActive: boolean): Promise<void> {
  const db = await getLocalDatabase();
  await db.runAsync('UPDATE products SET is_active = ? WHERE id = ?', isActive ? 1 : 0, id);
}

export async function deleteLocalProduct(id: number): Promise<void> {
  const db = await getLocalDatabase();
  await db.runAsync('DELETE FROM cart_items WHERE product_id = ?', id);
  await db.runAsync('DELETE FROM products WHERE id = ?', id);
}

// ---- Meja (tables) ----

export async function listTables(): Promise<LocalTable[]> {
  const db = await getLocalDatabase();

  return db.getAllAsync<LocalTable>(`
    SELECT
      t.id,
      t.table_number,
      t.label,
      t.is_active,
      (
        SELECT COUNT(*) FROM local_orders o
        WHERE o.table_label = t.label AND o.status IN ('open', 'submitted', 'confirmed')
      ) AS open_orders
    FROM pos_tables t
    ORDER BY t.id ASC
  `);
}

export async function createTable(input: { table_number: string; label: string }): Promise<void> {
  const db = await getLocalDatabase();
  await db.runAsync(
    'INSERT INTO pos_tables (table_number, label, is_active, created_at) VALUES (?, ?, 1, ?)',
    input.table_number.trim(),
    input.label.trim() || `Meja ${input.table_number.trim()}`,
    new Date().toISOString(),
  );
}

export async function toggleTable(id: number, isActive: boolean): Promise<void> {
  const db = await getLocalDatabase();
  await db.runAsync('UPDATE pos_tables SET is_active = ? WHERE id = ?', isActive ? 1 : 0, id);
}

export async function deleteTable(id: number): Promise<void> {
  const db = await getLocalDatabase();
  await db.runAsync('DELETE FROM pos_tables WHERE id = ?', id);
}

// ---- Keranjang kasir ----

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
    `SELECT ${PRODUCT_COLUMNS} FROM products WHERE id = ?`,
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

  return next < 1000 ? String(next).padStart(3, '0') : String(next);
}

export async function checkoutLocalCart(input: {
  customerName?: string;
  orderType?: OrderType;
  tableLabel?: string | null;
  paymentMethod: PaymentMethod;
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
        order_number, order_day, customer_name, order_type, table_label, subtotal, total,
        payment_method, amount_received, change_amount, status, source, paid_at, created_at
      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'paid', 'kasir', ?, ?)`,
      orderNumber,
      orderDay,
      input.customerName?.trim() || null,
      input.orderType ?? 'takeaway',
      input.tableLabel ?? null,
      subtotal,
      total,
      input.paymentMethod,
      input.amountReceived ?? null,
      changeAmount,
      createdAt,
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

  const order = await getOrderByNumber(db, orderNumber, orderDay);

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
        order_number, order_day, customer_name, order_type, table_label, subtotal, total,
        payment_method, amount_received, change_amount, status, source, created_at
      ) VALUES (?, ?, ?, 'takeaway', NULL, ?, ?, 'unpaid', NULL, NULL, 'submitted', 'online', ?)`,
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

  const order = await getOrderByNumber(db, orderNumber, orderDay);

  if (!order) {
    throw new Error('Gagal menyimpan pesanan online.');
  }

  return order;
}

const ORDER_COLUMNS =
  'id, order_number, customer_name, order_type, table_label, subtotal, total, payment_method, amount_received, change_amount, status, source, confirmed_at, paid_at, created_at';

async function getOrderByNumber(
  db: SQLite.SQLiteDatabase,
  orderNumber: string,
  orderDay: string,
): Promise<LocalOrder | null> {
  return db.getFirstAsync<LocalOrder>(
    `SELECT ${ORDER_COLUMNS} FROM local_orders WHERE order_number = ? AND order_day = ?`,
    orderNumber,
    orderDay,
  );
}

export async function getOrder(orderId: number): Promise<LocalOrder | null> {
  const db = await getLocalDatabase();

  return db.getFirstAsync<LocalOrder>(
    `SELECT ${ORDER_COLUMNS} FROM local_orders WHERE id = ?`,
    orderId,
  );
}

export async function getIncomingOnlineOrders(): Promise<LocalOrder[]> {
  const db = await getLocalDatabase();

  return db.getAllAsync<LocalOrder>(
    `SELECT ${ORDER_COLUMNS} FROM local_orders WHERE status IN ('submitted', 'confirmed') AND source = 'online' ORDER BY datetime(created_at) ASC`,
  );
}

export async function getIncomingOnlineOrderCount(): Promise<number> {
  const db = await getLocalDatabase();
  const row = await db.getFirstAsync<{ count: number }>(
    "SELECT COUNT(*) AS count FROM local_orders WHERE status = 'submitted' AND source = 'online'",
  );

  return row?.count ?? 0;
}

export async function confirmOnlineOrder(orderId: number): Promise<void> {
  const db = await getLocalDatabase();
  const order = await getOrder(orderId);

  if (!order) {
    throw new Error('Pesanan tidak ditemukan.');
  }

  if (order.status !== 'submitted') {
    throw new Error('Pesanan ini sudah dikonfirmasi atau diproses.');
  }

  await db.runAsync(
    "UPDATE local_orders SET status = 'confirmed', confirmed_at = ? WHERE id = ?",
    new Date().toISOString(),
    orderId,
  );
}

export async function payOnlineOrder(
  orderId: number,
  input: { paymentMethod: PaymentMethod; amountReceived?: number },
): Promise<void> {
  const db = await getLocalDatabase();
  const order = await getOrder(orderId);

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

  const now = new Date().toISOString();

  await db.runAsync(
    `UPDATE local_orders
     SET status = 'paid', payment_method = ?, amount_received = ?, change_amount = ?, paid_at = ?
     WHERE id = ?`,
    input.paymentMethod,
    input.amountReceived ?? null,
    changeAmount,
    now,
    orderId,
  );
}

export async function cancelOrder(orderId: number): Promise<void> {
  const db = await getLocalDatabase();
  await db.runAsync(
    "UPDATE local_orders SET status = 'cancelled' WHERE id = ? AND status != 'paid'",
    orderId,
  );
}

// Riwayat lengkap (submitted, confirmed, paid) — seperti kasir.orders di Laravel.
export async function getOrdersHistory(): Promise<LocalOrder[]> {
  const db = await getLocalDatabase();

  return db.getAllAsync<LocalOrder>(
    `SELECT ${ORDER_COLUMNS} FROM local_orders
     WHERE status IN ('submitted', 'confirmed', 'paid')
     ORDER BY datetime(created_at) DESC
     LIMIT 100`,
  );
}

export async function getLocalOrders(): Promise<LocalOrder[]> {
  const db = await getLocalDatabase();

  return db.getAllAsync<LocalOrder>(
    `SELECT ${ORDER_COLUMNS} FROM local_orders WHERE status = 'paid' ORDER BY datetime(created_at) DESC LIMIT 50`,
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
