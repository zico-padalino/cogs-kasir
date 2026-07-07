import type { SQLiteDatabase } from 'expo-sqlite';

const DEMO_PRODUCTS = [
  { name: 'Espresso', category: 'minuman', price: 18000, emoji: '☕' },
  { name: 'Cappuccino', category: 'minuman', price: 25000, emoji: '☕' },
  { name: 'Latte', category: 'minuman', price: 28000, emoji: '🥛' },
  { name: 'Americano', category: 'minuman', price: 22000, emoji: '☕' },
  { name: 'Matcha Latte', category: 'minuman', price: 32000, emoji: '🍵' },
  { name: 'Croissant', category: 'pastry', price: 22000, emoji: '🥐' },
  { name: 'Donat Coklat', category: 'pastry', price: 15000, emoji: '🍩' },
  { name: 'Roti Tawar', category: 'makanan', price: 12000, emoji: '🍞' },
  { name: 'Sandwich', category: 'makanan', price: 35000, emoji: '🥪' },
  { name: 'Kentang Goreng', category: 'snack', price: 18000, emoji: '🍟' },
];

export async function ensureSchema(db: SQLiteDatabase): Promise<void> {
  await db.execAsync(`
    PRAGMA journal_mode = WAL;

    CREATE TABLE IF NOT EXISTS products (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      name TEXT NOT NULL,
      category TEXT NOT NULL,
      price INTEGER NOT NULL,
      emoji TEXT NOT NULL DEFAULT '☕'
    );

    CREATE TABLE IF NOT EXISTS cart_items (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      product_id INTEGER NOT NULL,
      quantity INTEGER NOT NULL DEFAULT 1,
      unit_price INTEGER NOT NULL,
      notes TEXT,
      FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    );

    CREATE TABLE IF NOT EXISTS local_orders (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      order_number TEXT NOT NULL,
      order_day TEXT NOT NULL,
      customer_name TEXT,
      subtotal INTEGER NOT NULL,
      total INTEGER NOT NULL,
      payment_method TEXT NOT NULL,
      amount_received INTEGER,
      change_amount INTEGER,
      status TEXT NOT NULL DEFAULT 'paid',
      source TEXT NOT NULL DEFAULT 'kasir',
      created_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS local_order_items (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      order_id INTEGER NOT NULL,
      product_name TEXT NOT NULL,
      quantity INTEGER NOT NULL,
      unit_price INTEGER NOT NULL,
      line_total INTEGER NOT NULL,
      FOREIGN KEY (order_id) REFERENCES local_orders(id) ON DELETE CASCADE
    );

    CREATE TABLE IF NOT EXISTS app_meta (
      key TEXT PRIMARY KEY,
      value TEXT NOT NULL
    );
  `);
}

export async function seedProductsIfEmpty(db: SQLiteDatabase): Promise<void> {
  const row = await db.getFirstAsync<{ count: number }>('SELECT COUNT(*) AS count FROM products');

  if ((row?.count ?? 0) > 0) {
    return;
  }

  for (const product of DEMO_PRODUCTS) {
    await db.runAsync(
      'INSERT INTO products (name, category, price, emoji) VALUES (?, ?, ?, ?)',
      product.name,
      product.category,
      product.price,
      product.emoji,
    );
  }
}
