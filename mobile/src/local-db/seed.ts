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

const DEMO_TABLES = [
  { table_number: '1', label: 'Meja 1' },
  { table_number: '2', label: 'Meja 2' },
  { table_number: '3', label: 'Meja 3' },
  { table_number: 'TA', label: 'Take Away' },
];

async function columnExists(db: SQLiteDatabase, table: string, column: string): Promise<boolean> {
  const rows = await db.getAllAsync<{ name: string }>(`PRAGMA table_info(${table})`);

  return rows.some((row) => row.name === column);
}

async function addColumnIfMissing(
  db: SQLiteDatabase,
  table: string,
  column: string,
  definition: string,
): Promise<void> {
  if (!(await columnExists(db, table, column))) {
    await db.execAsync(`ALTER TABLE ${table} ADD COLUMN ${column} ${definition}`);
  }
}

export async function ensureSchema(db: SQLiteDatabase): Promise<void> {
  await db.execAsync(`
    PRAGMA journal_mode = WAL;

    CREATE TABLE IF NOT EXISTS products (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      name TEXT NOT NULL,
      category TEXT NOT NULL,
      price INTEGER NOT NULL,
      emoji TEXT NOT NULL DEFAULT '☕',
      description TEXT,
      is_active INTEGER NOT NULL DEFAULT 1
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
      order_type TEXT NOT NULL DEFAULT 'takeaway',
      table_label TEXT,
      subtotal INTEGER NOT NULL,
      total INTEGER NOT NULL,
      payment_method TEXT NOT NULL,
      amount_received INTEGER,
      change_amount INTEGER,
      status TEXT NOT NULL DEFAULT 'paid',
      source TEXT NOT NULL DEFAULT 'kasir',
      confirmed_at TEXT,
      paid_at TEXT,
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

    CREATE TABLE IF NOT EXISTS pos_tables (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      table_number TEXT NOT NULL,
      label TEXT NOT NULL,
      is_active INTEGER NOT NULL DEFAULT 1,
      created_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS app_meta (
      key TEXT PRIMARY KEY,
      value TEXT NOT NULL
    );
  `);

  // Migrasi untuk database lama yang dibuat sebelum kolom baru ditambahkan.
  await addColumnIfMissing(db, 'products', 'description', 'TEXT');
  await addColumnIfMissing(db, 'products', 'is_active', 'INTEGER NOT NULL DEFAULT 1');
  await addColumnIfMissing(db, 'local_orders', 'order_type', "TEXT NOT NULL DEFAULT 'takeaway'");
  await addColumnIfMissing(db, 'local_orders', 'table_label', 'TEXT');
  await addColumnIfMissing(db, 'local_orders', 'confirmed_at', 'TEXT');
  await addColumnIfMissing(db, 'local_orders', 'paid_at', 'TEXT');
}

export async function seedProductsIfEmpty(db: SQLiteDatabase): Promise<void> {
  const row = await db.getFirstAsync<{ count: number }>('SELECT COUNT(*) AS count FROM products');

  if ((row?.count ?? 0) === 0) {
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

  const tableRow = await db.getFirstAsync<{ count: number }>('SELECT COUNT(*) AS count FROM pos_tables');

  if ((tableRow?.count ?? 0) === 0) {
    const now = new Date().toISOString();

    for (const table of DEMO_TABLES) {
      await db.runAsync(
        'INSERT INTO pos_tables (table_number, label, is_active, created_at) VALUES (?, ?, 1, ?)',
        table.table_number,
        table.label,
        now,
      );
    }
  }
}
