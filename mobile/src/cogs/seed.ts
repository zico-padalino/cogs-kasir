import type { SQLiteDatabase } from 'expo-sqlite';

export async function seedDemoData(db: SQLiteDatabase): Promise<void> {
  const seeded = await db.getFirstAsync<{ value: string }>(
    "SELECT value FROM app_meta WHERE key = 'seeded'",
  );

  if (seeded?.value === '1') {
    return;
  }

  await db.withExclusiveTransactionAsync(async (txn) => {
    await txn.runAsync(
      `INSERT INTO overhead_rates (name, allocation_base, rate, is_active, description) VALUES
        ('Overhead Pabrik - Bahan Langsung', 'direct_material', 0.15, 1, 'Listrik, sewa, penyusutan (15% dari bahan)'),
        ('Overhead Tenaga Kerja', 'labor_hours', 25000, 1, 'Overhead per jam kerja'),
        ('Overhead Mesin', 'machine_hours', 50000, 1, 'Overhead per jam mesin')`,
    );

    const insertProduct = async (
      sku: string,
      name: string,
      type: string,
      unit: string,
      standardCost: number,
      method: string,
    ): Promise<number> => {
      const result = await txn.runAsync(
        `INSERT INTO products (sku, name, type, unit, standard_cost, costing_method, is_active)
         VALUES (?, ?, ?, ?, ?, ?, 1)`,
        sku,
        name,
        type,
        unit,
        standardCost,
        method,
      );

      return result.lastInsertRowId;
    };

    const flour = await insertProduct('RM-FLOUR-001', 'Tepung Terigu', 'raw_material', 'kg', 12000, 'fifo');
    const sugar = await insertProduct('RM-SUGAR-001', 'Gula Pasir', 'raw_material', 'kg', 15000, 'fifo');
    const butter = await insertProduct('RM-BUTTER-001', 'Mentega', 'raw_material', 'kg', 85000, 'weighted_average');
    const dough = await insertProduct('SF-DOUGH-001', 'Adonan Roti', 'semi_finished', 'kg', 0, 'weighted_average');
    const bread = await insertProduct('FG-BREAD-001', 'Roti Tawar Premium', 'finished_good', 'loaf', 0, 'weighted_average');

    const insertBom = async (
      parent: number,
      child: number,
      qty: number,
      scrap: number,
      seq: number,
    ): Promise<void> => {
      await txn.runAsync(
        `INSERT INTO bill_of_materials (parent_product_id, child_product_id, quantity, scrap_percentage, sequence)
         VALUES (?, ?, ?, ?, ?)`,
        parent,
        child,
        qty,
        scrap,
        seq,
      );
    };

    await insertBom(dough, flour, 0.6, 2, 1);
    await insertBom(dough, sugar, 0.1, 1, 2);
    await insertBom(dough, butter, 0.05, 0, 3);
    await insertBom(bread, dough, 0.5, 3, 1);

    const now = new Date();
    const iso = (minutesAgo: number) => new Date(now.getTime() - minutesAgo * 60000).toISOString();

    const insertLot = async (
      productId: number,
      lotNumber: string,
      qty: number,
      unitCost: number,
      minutesAgo: number,
    ): Promise<void> => {
      await txn.runAsync(
        `INSERT INTO inventory_lots (product_id, lot_number, quantity_received, quantity_remaining, unit_cost, received_at)
         VALUES (?, ?, ?, ?, ?, ?)`,
        productId,
        lotNumber,
        qty,
        qty,
        unitCost,
        iso(minutesAgo),
      );
    };

    await insertLot(flour, 'LOT-FLOUR-001', 500, 11500, 120);
    await insertLot(flour, 'LOT-FLOUR-002', 300, 12500, 60);
    await insertLot(sugar, 'LOT-SUGAR-001', 200, 14800, 90);
    await insertLot(butter, 'LOT-BUTTER-001', 50, 84000, 90);
    await insertLot(butter, 'LOT-BUTTER-002', 30, 86000, 45);

    await txn.runAsync("INSERT OR REPLACE INTO app_meta (key, value) VALUES ('seeded', '1')");
  });
}
