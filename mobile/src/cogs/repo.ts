import { getCogsDb } from '@/cogs/db';
import { availableQuantity, getWeightedAverageCost } from '@/cogs/engine';
import type {
  AllocationBase,
  BomRow,
  CogsCalculation,
  CostingMethod,
  InventoryLot,
  OverheadRate,
  Product,
  ProductionLabor,
  ProductionMaterial,
  ProductionOrder,
  ProductType,
} from '@/cogs/types';

// ── Products ────────────────────────────────────────────────────────────────

export async function listProducts(): Promise<(Product & { bom_count: number })[]> {
  const db = await getCogsDb();

  return db.getAllAsync<Product & { bom_count: number }>(
    `SELECT p.*, (
        SELECT COUNT(*) FROM bill_of_materials b WHERE b.parent_product_id = p.id
      ) AS bom_count
     FROM products p
     ORDER BY p.type DESC, p.name ASC`,
  );
}

export async function listActiveProducts(): Promise<Product[]> {
  const db = await getCogsDb();

  return db.getAllAsync<Product>('SELECT * FROM products WHERE is_active = 1 ORDER BY name ASC');
}

export async function listManufacturableProducts(): Promise<Product[]> {
  const db = await getCogsDb();

  return db.getAllAsync<Product>(
    `SELECT * FROM products
     WHERE is_active = 1 AND type IN ('semi_finished', 'finished_good')
     ORDER BY name ASC`,
  );
}

export async function getProduct(id: number): Promise<Product | null> {
  const db = await getCogsDb();

  return db.getFirstAsync<Product>('SELECT * FROM products WHERE id = ?', id);
}

export async function createProduct(input: {
  sku: string;
  name: string;
  type: ProductType;
  unit: string;
  standard_cost: number;
  costing_method: CostingMethod;
  description?: string | null;
}): Promise<void> {
  const db = await getCogsDb();

  await db.runAsync(
    `INSERT INTO products (sku, name, type, unit, standard_cost, costing_method, description, is_active)
     VALUES (?, ?, ?, ?, ?, ?, ?, 1)`,
    input.sku,
    input.name,
    input.type,
    input.unit,
    input.standard_cost,
    input.costing_method,
    input.description ?? null,
  );
}

export async function updateProduct(
  id: number,
  input: {
    name: string;
    unit: string;
    standard_cost: number;
    costing_method: CostingMethod;
    description?: string | null;
    is_active: boolean;
  },
): Promise<void> {
  const db = await getCogsDb();

  await db.runAsync(
    `UPDATE products SET name = ?, unit = ?, standard_cost = ?, costing_method = ?, description = ?, is_active = ?
     WHERE id = ?`,
    input.name,
    input.unit,
    input.standard_cost,
    input.costing_method,
    input.description ?? null,
    input.is_active ? 1 : 0,
    id,
  );
}

export async function deleteProduct(id: number): Promise<void> {
  const db = await getCogsDb();

  const cogsRow = await db.getFirstAsync<{ count: number }>(
    'SELECT COUNT(*) AS count FROM cogs_calculations WHERE product_id = ?',
    id,
  );

  if ((cogsRow?.count ?? 0) > 0) {
    throw new Error('Produk sudah punya riwayat COGS, tidak bisa dihapus.');
  }

  await db.withExclusiveTransactionAsync(async (txn) => {
    await txn.runAsync(
      'DELETE FROM bill_of_materials WHERE parent_product_id = ? OR child_product_id = ?',
      id,
      id,
    );
    await txn.runAsync('DELETE FROM inventory_lots WHERE product_id = ?', id);
    await txn.runAsync('DELETE FROM products WHERE id = ?', id);
  });
}

export type ProductStat = {
  available: number;
  average_cost: number;
};

export async function getProductStat(product: Product): Promise<ProductStat> {
  const db = await getCogsDb();
  const available = await availableQuantity(db, product.id);
  const average = await getWeightedAverageCost(db, product);

  return { available, average_cost: average };
}

// ── BOM ─────────────────────────────────────────────────────────────────────

export type BomRowView = BomRow & { child_name: string; child_sku: string; child_unit: string };

export async function listBom(parentId: number): Promise<BomRowView[]> {
  const db = await getCogsDb();

  return db.getAllAsync<BomRowView>(
    `SELECT b.*, p.name AS child_name, p.sku AS child_sku, p.unit AS child_unit
     FROM bill_of_materials b
     INNER JOIN products p ON p.id = b.child_product_id
     WHERE b.parent_product_id = ?
     ORDER BY b.sequence ASC, b.id ASC`,
    parentId,
  );
}

export async function upsertBom(input: {
  parent_product_id: number;
  child_product_id: number;
  quantity: number;
  scrap_percentage: number;
  sequence: number;
}): Promise<void> {
  const db = await getCogsDb();

  if (input.parent_product_id === input.child_product_id) {
    throw new Error('Produk tidak bisa menjadi bahan untuk dirinya sendiri.');
  }

  await db.runAsync(
    `INSERT INTO bill_of_materials (parent_product_id, child_product_id, quantity, scrap_percentage, sequence)
     VALUES (?, ?, ?, ?, ?)
     ON CONFLICT (parent_product_id, child_product_id)
     DO UPDATE SET quantity = excluded.quantity, scrap_percentage = excluded.scrap_percentage, sequence = excluded.sequence`,
    input.parent_product_id,
    input.child_product_id,
    input.quantity,
    input.scrap_percentage,
    input.sequence,
  );
}

export async function deleteBom(id: number): Promise<void> {
  const db = await getCogsDb();
  await db.runAsync('DELETE FROM bill_of_materials WHERE id = ?', id);
}

// ── Inventory ────────────────────────────────────────────────────────────────

export type LotView = InventoryLot & { product_name: string; product_unit: string };

export async function listLots(): Promise<LotView[]> {
  const db = await getCogsDb();

  return db.getAllAsync<LotView>(
    `SELECT l.*, p.name AS product_name, p.unit AS product_unit
     FROM inventory_lots l
     INNER JOIN products p ON p.id = l.product_id
     WHERE l.quantity_remaining > 0
     ORDER BY l.received_at DESC, l.id DESC`,
  );
}

export async function listRawMaterials(): Promise<Product[]> {
  const db = await getCogsDb();

  return db.getAllAsync<Product>(
    "SELECT * FROM products WHERE is_active = 1 AND type = 'raw_material' ORDER BY name ASC",
  );
}

export async function receiveInventory(input: {
  product_id: number;
  quantity: number;
  unit_cost: number;
  lot_number?: string | null;
}): Promise<void> {
  const db = await getCogsDb();

  await db.runAsync(
    `INSERT INTO inventory_lots
      (product_id, lot_number, quantity_received, quantity_remaining, unit_cost, received_at)
     VALUES (?, ?, ?, ?, ?, ?)`,
    input.product_id,
    input.lot_number ?? null,
    input.quantity,
    input.quantity,
    input.unit_cost,
    new Date().toISOString(),
  );
}

export async function deleteLot(lot: InventoryLot): Promise<void> {
  if (lot.quantity_remaining < lot.quantity_received) {
    throw new Error('Lot sudah terpakai sebagian, tidak bisa dihapus.');
  }

  const db = await getCogsDb();
  await db.runAsync('DELETE FROM inventory_lots WHERE id = ?', lot.id);
}

// ── Overhead ─────────────────────────────────────────────────────────────────

export async function listOverheadRates(): Promise<OverheadRate[]> {
  const db = await getCogsDb();

  return db.getAllAsync<OverheadRate>('SELECT * FROM overhead_rates ORDER BY id ASC');
}

export async function createOverheadRate(input: {
  name: string;
  allocation_base: AllocationBase;
  rate: number;
  description?: string | null;
}): Promise<void> {
  const db = await getCogsDb();

  await db.runAsync(
    'INSERT INTO overhead_rates (name, allocation_base, rate, is_active, description) VALUES (?, ?, ?, 1, ?)',
    input.name,
    input.allocation_base,
    input.rate,
    input.description ?? null,
  );
}

export async function toggleOverheadRate(id: number, active: boolean): Promise<void> {
  const db = await getCogsDb();
  await db.runAsync('UPDATE overhead_rates SET is_active = ? WHERE id = ?', active ? 1 : 0, id);
}

export async function deleteOverheadRate(id: number): Promise<void> {
  const db = await getCogsDb();
  await db.runAsync('DELETE FROM overhead_rates WHERE id = ?', id);
}

// ── Production ───────────────────────────────────────────────────────────────

export type ProductionView = ProductionOrder & { product_name: string; product_unit: string };

export async function listProductionOrders(): Promise<ProductionView[]> {
  const db = await getCogsDb();

  return db.getAllAsync<ProductionView>(
    `SELECT o.*, p.name AS product_name, p.unit AS product_unit
     FROM production_orders o
     INNER JOIN products p ON p.id = o.product_id
     ORDER BY o.id DESC`,
  );
}

export async function getProductionOrder(id: number): Promise<ProductionView | null> {
  const db = await getCogsDb();

  return db.getFirstAsync<ProductionView>(
    `SELECT o.*, p.name AS product_name, p.unit AS product_unit
     FROM production_orders o
     INNER JOIN products p ON p.id = o.product_id
     WHERE o.id = ?`,
    id,
  );
}

export type ProductionMaterialView = ProductionMaterial & { product_name: string; product_unit: string };

export async function getProductionMaterials(orderId: number): Promise<ProductionMaterialView[]> {
  const db = await getCogsDb();

  return db.getAllAsync<ProductionMaterialView>(
    `SELECT m.*, p.name AS product_name, p.unit AS product_unit
     FROM production_order_materials m
     INNER JOIN products p ON p.id = m.product_id
     WHERE m.production_order_id = ?
     ORDER BY m.id ASC`,
    orderId,
  );
}

export async function getProductionLabors(orderId: number): Promise<ProductionLabor[]> {
  const db = await getCogsDb();

  return db.getAllAsync<ProductionLabor>(
    'SELECT * FROM production_order_labors WHERE production_order_id = ? ORDER BY id ASC',
    orderId,
  );
}

export async function deleteProductionOrder(order: ProductionOrder): Promise<void> {
  if (order.status === 'completed') {
    throw new Error('Order selesai tidak bisa dihapus.');
  }

  const db = await getCogsDb();

  await db.withExclusiveTransactionAsync(async (txn) => {
    await txn.runAsync('DELETE FROM production_order_materials WHERE production_order_id = ?', order.id);
    await txn.runAsync('DELETE FROM production_order_labors WHERE production_order_id = ?', order.id);
    await txn.runAsync('DELETE FROM production_orders WHERE id = ?', order.id);
  });
}

// ── COGS history ─────────────────────────────────────────────────────────────

export type CogsCalculationView = CogsCalculation & { product_name: string };

export async function listCogsCalculations(): Promise<CogsCalculationView[]> {
  const db = await getCogsDb();

  return db.getAllAsync<CogsCalculationView>(
    `SELECT c.*, p.name AS product_name
     FROM cogs_calculations c
     INNER JOIN products p ON p.id = c.product_id
     ORDER BY c.id DESC`,
  );
}

export async function getCogsCalculation(id: number): Promise<CogsCalculationView | null> {
  const db = await getCogsDb();

  return db.getFirstAsync<CogsCalculationView>(
    `SELECT c.*, p.name AS product_name
     FROM cogs_calculations c
     INNER JOIN products p ON p.id = c.product_id
     WHERE c.id = ?`,
    id,
  );
}

export async function deleteCogsCalculation(id: number): Promise<void> {
  const db = await getCogsDb();
  await db.runAsync('DELETE FROM cogs_calculations WHERE id = ?', id);
}

// ── Summary ──────────────────────────────────────────────────────────────────

export type CogsSummary = {
  total_cogs: number;
  total_direct_material: number;
  total_direct_labor: number;
  total_overhead: number;
  total_records: number;
  by_product: {
    product_id: number;
    name: string;
    total_quantity: number;
    total_cogs: number;
    average_unit_cogs: number;
  }[];
};

export async function getCogsSummary(): Promise<CogsSummary> {
  const db = await getCogsDb();

  const totals = await db.getFirstAsync<{
    total_cogs: number | null;
    total_direct_material: number | null;
    total_direct_labor: number | null;
    total_overhead: number | null;
    total_records: number | null;
  }>(
    `SELECT
        SUM(total_cogs) AS total_cogs,
        SUM(direct_material) AS total_direct_material,
        SUM(direct_labor) AS total_direct_labor,
        SUM(manufacturing_overhead) AS total_overhead,
        COUNT(*) AS total_records
     FROM cogs_calculations`,
  );

  const byProduct = await db.getAllAsync<{
    product_id: number;
    name: string;
    total_quantity: number;
    total_cogs: number;
  }>(
    `SELECT c.product_id, p.name, SUM(c.quantity) AS total_quantity, SUM(c.total_cogs) AS total_cogs
     FROM cogs_calculations c
     INNER JOIN products p ON p.id = c.product_id
     GROUP BY c.product_id, p.name
     ORDER BY total_cogs DESC`,
  );

  return {
    total_cogs: totals?.total_cogs ?? 0,
    total_direct_material: totals?.total_direct_material ?? 0,
    total_direct_labor: totals?.total_direct_labor ?? 0,
    total_overhead: totals?.total_overhead ?? 0,
    total_records: totals?.total_records ?? 0,
    by_product: byProduct.map((row) => ({
      product_id: row.product_id,
      name: row.name,
      total_quantity: row.total_quantity,
      total_cogs: row.total_cogs,
      average_unit_cogs: row.total_quantity > 0 ? row.total_cogs / row.total_quantity : 0,
    })),
  };
}

// ── Setup progress ───────────────────────────────────────────────────────────

export type SetupStep = {
  number: number;
  key: string;
  short: string;
  title: string;
  description: string;
  hint: string;
  route: string;
  done: boolean;
};

export type SetupProgress = {
  steps: SetupStep[];
  currentStep: number;
  percent: number;
  fullyComplete: boolean;
};

export async function getSetupProgress(): Promise<SetupProgress> {
  const db = await getCogsDb();

  const count = async (sql: string): Promise<number> => {
    const row = await db.getFirstAsync<{ count: number }>(sql);

    return row?.count ?? 0;
  };

  const overheadDone = (await count('SELECT COUNT(*) AS count FROM overhead_rates WHERE is_active = 1')) > 0;
  const rawCount = await count("SELECT COUNT(*) AS count FROM products WHERE type = 'raw_material'");
  const finishedCount = await count(
    "SELECT COUNT(*) AS count FROM products WHERE type IN ('semi_finished', 'finished_good')",
  );
  const productsDone = rawCount > 0 && finishedCount > 0;
  const bomDone = (await count('SELECT COUNT(*) AS count FROM bill_of_materials')) > 0;
  const inventoryDone = (await count('SELECT COUNT(*) AS count FROM inventory_lots WHERE quantity_remaining > 0')) > 0;
  const resultDone = (await count('SELECT COUNT(*) AS count FROM cogs_calculations')) > 0;

  const steps: SetupStep[] = [
    {
      number: 1,
      key: 'overhead',
      short: 'Biaya Overhead',
      title: 'Biaya Overhead',
      description: 'Tentukan biaya tidak langsung: listrik, sewa, penyusutan.',
      hint: 'Contoh: 15% dari bahan (isi 0.15) atau Rp per jam.',
      route: '/cogs/overhead',
      done: overheadDone,
    },
    {
      number: 2,
      key: 'products',
      short: 'Daftar Produk',
      title: 'Daftar Produk',
      description: 'Buat bahan baku dan produk jadi/setengah jadi.',
      hint: 'Minimal 1 bahan baku dan 1 produk jadi.',
      route: '/cogs/products',
      done: productsDone,
    },
    {
      number: 3,
      key: 'bom',
      short: 'Resep (BOM)',
      title: 'Resep Produksi (BOM)',
      description: 'Susun resep: bahan apa saja untuk 1 produk.',
      hint: 'Buka detail produk jadi lalu tambah bahan.',
      route: '/cogs/products',
      done: bomDone,
    },
    {
      number: 4,
      key: 'inventory',
      short: 'Stok Bahan',
      title: 'Stok Bahan Baku',
      description: 'Catat pembelian bahan: jumlah & harga per lot.',
      hint: 'Harga beli dipakai untuk hitung COGS (FIFO / rata-rata).',
      route: '/cogs/inventory',
      done: inventoryDone,
    },
    {
      number: 5,
      key: 'result',
      short: 'Hasil COGS',
      title: 'Lihat Hasil COGS',
      description: 'Lihat rincian biaya per produk & per unit.',
      hint: 'COGS = Bahan + Tenaga Kerja + Overhead.',
      route: '/cogs/history',
      done: resultDone,
    },
  ];

  const firstUndone = steps.find((step) => !step.done);
  const completed = steps.filter((step) => step.done).length;

  return {
    steps,
    currentStep: firstUndone?.number ?? 5,
    percent: Math.round((completed / steps.length) * 100),
    fullyComplete: completed === steps.length,
  };
}
