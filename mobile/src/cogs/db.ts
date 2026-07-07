import * as SQLite from 'expo-sqlite';
import { seedDemoData } from '@/cogs/seed';

let dbPromise: Promise<SQLite.SQLiteDatabase> | null = null;

export async function getCogsDb(): Promise<SQLite.SQLiteDatabase> {
  if (!dbPromise) {
    dbPromise = (async () => {
      const db = await SQLite.openDatabaseAsync('cogs_local.db');
      await createSchema(db);
      await seedDemoData(db);

      return db;
    })();
  }

  return dbPromise;
}

async function createSchema(db: SQLite.SQLiteDatabase): Promise<void> {
  await db.execAsync(`
    PRAGMA journal_mode = WAL;
    PRAGMA foreign_keys = ON;

    CREATE TABLE IF NOT EXISTS products (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      sku TEXT NOT NULL UNIQUE,
      name TEXT NOT NULL,
      type TEXT NOT NULL,
      unit TEXT NOT NULL DEFAULT 'pcs',
      standard_cost REAL NOT NULL DEFAULT 0,
      costing_method TEXT NOT NULL DEFAULT 'weighted_average',
      description TEXT,
      is_active INTEGER NOT NULL DEFAULT 1
    );

    CREATE TABLE IF NOT EXISTS bill_of_materials (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      parent_product_id INTEGER NOT NULL,
      child_product_id INTEGER NOT NULL,
      quantity REAL NOT NULL DEFAULT 0,
      scrap_percentage REAL NOT NULL DEFAULT 0,
      sequence INTEGER NOT NULL DEFAULT 0,
      UNIQUE (parent_product_id, child_product_id)
    );

    CREATE TABLE IF NOT EXISTS inventory_lots (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      product_id INTEGER NOT NULL,
      lot_number TEXT,
      quantity_received REAL NOT NULL DEFAULT 0,
      quantity_remaining REAL NOT NULL DEFAULT 0,
      unit_cost REAL NOT NULL DEFAULT 0,
      received_at TEXT NOT NULL,
      source_type TEXT,
      source_id INTEGER
    );

    CREATE TABLE IF NOT EXISTS overhead_rates (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      name TEXT NOT NULL,
      allocation_base TEXT NOT NULL,
      rate REAL NOT NULL DEFAULT 0,
      is_active INTEGER NOT NULL DEFAULT 1,
      description TEXT
    );

    CREATE TABLE IF NOT EXISTS production_orders (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      order_number TEXT NOT NULL UNIQUE,
      product_id INTEGER NOT NULL,
      quantity_planned REAL NOT NULL DEFAULT 0,
      quantity_completed REAL NOT NULL DEFAULT 0,
      status TEXT NOT NULL DEFAULT 'draft',
      machine_hours REAL NOT NULL DEFAULT 0,
      started_at TEXT,
      completed_at TEXT,
      notes TEXT
    );

    CREATE TABLE IF NOT EXISTS production_order_materials (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      production_order_id INTEGER NOT NULL,
      product_id INTEGER NOT NULL,
      quantity_planned REAL NOT NULL DEFAULT 0,
      quantity_used REAL NOT NULL DEFAULT 0,
      unit_cost REAL NOT NULL DEFAULT 0,
      total_cost REAL NOT NULL DEFAULT 0
    );

    CREATE TABLE IF NOT EXISTS production_order_labors (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      production_order_id INTEGER NOT NULL,
      description TEXT NOT NULL,
      labor_hours REAL NOT NULL DEFAULT 0,
      hourly_rate REAL NOT NULL DEFAULT 0,
      total_cost REAL NOT NULL DEFAULT 0
    );

    CREATE TABLE IF NOT EXISTS cogs_calculations (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      reference_type TEXT NOT NULL,
      reference_id INTEGER NOT NULL,
      product_id INTEGER NOT NULL,
      quantity REAL NOT NULL DEFAULT 0,
      direct_material REAL NOT NULL DEFAULT 0,
      direct_labor REAL NOT NULL DEFAULT 0,
      manufacturing_overhead REAL NOT NULL DEFAULT 0,
      total_cogs REAL NOT NULL DEFAULT 0,
      unit_cogs REAL NOT NULL DEFAULT 0,
      calculation_method TEXT NOT NULL,
      breakdown TEXT NOT NULL DEFAULT '{}',
      calculated_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS app_meta (
      key TEXT PRIMARY KEY,
      value TEXT NOT NULL
    );
  `);
}

export async function resetCogsData(): Promise<void> {
  const db = await getCogsDb();

  await db.withExclusiveTransactionAsync(async (txn) => {
    for (const table of [
      'cogs_calculations',
      'production_order_labors',
      'production_order_materials',
      'production_orders',
      'inventory_lots',
      'bill_of_materials',
      'overhead_rates',
      'products',
    ]) {
      await txn.runAsync(`DELETE FROM ${table}`);
    }

    await txn.runAsync('DELETE FROM sqlite_sequence');
    // Pertahankan flag 'seeded' agar data demo tidak diisi ulang otomatis saat DB dibuka lagi.
    await txn.runAsync("INSERT OR REPLACE INTO app_meta (key, value) VALUES ('seeded', '1')");
  });
}
