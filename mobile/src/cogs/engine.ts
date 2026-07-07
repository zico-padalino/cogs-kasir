import type { SQLiteDatabase, SQLiteRunResult } from 'expo-sqlite';
import { round4 } from '@/cogs/format';
import type {
  BomNode,
  BomRow,
  InventoryLot,
  LaborInput,
  OverheadRate,
  Product,
  ProductionLabor,
  ProductionMaterial,
  ProductionOrder,
} from '@/cogs/types';

type Executor = Pick<SQLiteDatabase, 'getAllAsync' | 'getFirstAsync' | 'runAsync'>;

export type LotConsumption = {
  lot_id?: number;
  lot_number?: string | null;
  quantity: number;
  unit_cost: number;
  cost: number;
  method?: string;
};

export type ConsumptionResult = {
  total_cost: number;
  average_unit_cost: number;
  lot_consumptions: LotConsumption[];
};

export type OverheadResult = {
  total: number;
  details: {
    overhead_rate_id: number;
    name: string;
    allocation_base: string;
    base_value: number;
    rate: number;
    allocated_cost: number;
  }[];
};

export type CogsResult = {
  direct_material: number;
  direct_labor: number;
  manufacturing_overhead: number;
  total_cogs: number;
  unit_cogs: number;
  calculation_method: string;
  breakdown: Record<string, unknown>;
};

const EPSILON = 0.000001;

// ── Inventory cost service ──────────────────────────────────────────────────

export async function receiveStock(
  db: Executor,
  productId: number,
  quantity: number,
  unitCost: number,
  lotNumber?: string | null,
  sourceType?: string | null,
  sourceId?: number | null,
): Promise<void> {
  await db.runAsync(
    `INSERT INTO inventory_lots
      (product_id, lot_number, quantity_received, quantity_remaining, unit_cost, received_at, source_type, source_id)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
    productId,
    lotNumber ?? null,
    quantity,
    quantity,
    unitCost,
    new Date().toISOString(),
    sourceType ?? null,
    sourceId ?? null,
  );
}

export async function getWeightedAverageCost(db: Executor, product: Product): Promise<number> {
  const row = await db.getFirstAsync<{ total_value: number | null; total_qty: number | null }>(
    `SELECT SUM(quantity_remaining * unit_cost) AS total_value, SUM(quantity_remaining) AS total_qty
     FROM inventory_lots
     WHERE product_id = ? AND quantity_remaining > 0`,
    product.id,
  );

  const totalQty = row?.total_qty ?? 0;

  if (totalQty <= 0) {
    return product.standard_cost;
  }

  return (row?.total_value ?? 0) / totalQty;
}

async function consumeFifo(
  db: Executor,
  product: Product,
  quantity: number,
  persist: boolean,
): Promise<ConsumptionResult> {
  const lots = await db.getAllAsync<InventoryLot>(
    `SELECT * FROM inventory_lots
     WHERE product_id = ? AND quantity_remaining > 0
     ORDER BY received_at ASC, id ASC`,
    product.id,
  );

  let remaining = quantity;
  let totalCost = 0;
  const consumptions: LotConsumption[] = [];

  for (const lot of lots) {
    if (remaining <= EPSILON) {
      break;
    }

    const consumed = Math.min(lot.quantity_remaining, remaining);
    const cost = consumed * lot.unit_cost;
    totalCost += cost;
    remaining -= consumed;

    consumptions.push({
      lot_id: lot.id,
      lot_number: lot.lot_number,
      quantity: round4(consumed),
      unit_cost: lot.unit_cost,
      cost: round4(cost),
    });

    if (persist) {
      await db.runAsync(
        'UPDATE inventory_lots SET quantity_remaining = quantity_remaining - ? WHERE id = ?',
        consumed,
        lot.id,
      );
    }
  }

  if (remaining > EPSILON) {
    throw new Error(
      `Stok ${product.name} tidak mencukupi. Kurang ${round4(remaining)} ${product.unit}.`,
    );
  }

  return {
    total_cost: round4(totalCost),
    average_unit_cost: quantity > 0 ? round4(totalCost / quantity) : 0,
    lot_consumptions: consumptions,
  };
}

export async function consumeStock(
  db: Executor,
  product: Product,
  quantity: number,
  persist = true,
): Promise<ConsumptionResult> {
  if (product.costing_method === 'fifo') {
    return consumeFifo(db, product, quantity, persist);
  }

  if (product.costing_method === 'standard') {
    const totalCost = quantity * product.standard_cost;

    if (persist) {
      await consumeFifo(db, product, quantity, true);
    }

    return {
      total_cost: round4(totalCost),
      average_unit_cost: round4(product.standard_cost),
      lot_consumptions: [
        {
          quantity: round4(quantity),
          unit_cost: round4(product.standard_cost),
          cost: round4(totalCost),
          method: 'standard',
        },
      ],
    };
  }

  const waCost = await getWeightedAverageCost(db, product);
  const totalCost = quantity * waCost;

  if (persist) {
    await consumeFifo(db, product, quantity, true);
  }

  return {
    total_cost: round4(totalCost),
    average_unit_cost: round4(waCost),
    lot_consumptions: [
      {
        quantity: round4(quantity),
        unit_cost: round4(waCost),
        cost: round4(totalCost),
        method: 'weighted_average',
      },
    ],
  };
}

export async function availableQuantity(db: Executor, productId: number): Promise<number> {
  const row = await db.getFirstAsync<{ total: number | null }>(
    'SELECT SUM(quantity_remaining) AS total FROM inventory_lots WHERE product_id = ? AND quantity_remaining > 0',
    productId,
  );

  return row?.total ?? 0;
}

// ── BOM cost service ────────────────────────────────────────────────────────

function effectiveQuantity(row: BomRow): number {
  return row.quantity * (1 + row.scrap_percentage / 100);
}

export async function rollUpCost(
  db: Executor,
  product: Product,
  quantity = 1,
  depth = 0,
): Promise<BomNode> {
  const bomRows = await db.getAllAsync<BomRow>(
    'SELECT * FROM bill_of_materials WHERE parent_product_id = ? ORDER BY sequence ASC, id ASC',
    product.id,
  );

  if (bomRows.length === 0 || depth >= 20) {
    const unitCost =
      product.costing_method === 'standard'
        ? product.standard_cost
        : await getWeightedAverageCost(db, product);

    return {
      product_id: product.id,
      name: product.name,
      sku: product.sku,
      unit: product.unit,
      unit_cost: round4(unitCost),
      total_cost: round4(unitCost * quantity),
      is_leaf: true,
      components: [],
    };
  }

  const components: BomNode[] = [];
  let totalCost = 0;

  for (const row of bomRows) {
    const child = await db.getFirstAsync<Product>('SELECT * FROM products WHERE id = ?', row.child_product_id);

    if (!child) {
      continue;
    }

    const requiredQty = effectiveQuantity(row) * quantity;
    const childNode = await rollUpCost(db, child, requiredQty, depth + 1);
    childNode.bom_quantity = row.quantity;
    childNode.scrap_percentage = row.scrap_percentage;
    childNode.effective_quantity = round4(effectiveQuantity(row));
    totalCost += childNode.total_cost;
    components.push(childNode);
  }

  return {
    product_id: product.id,
    name: product.name,
    sku: product.sku,
    unit: product.unit,
    unit_cost: quantity > 0 ? round4(totalCost / quantity) : 0,
    total_cost: round4(totalCost),
    is_leaf: false,
    components,
  };
}

export type ExplodedMaterial = {
  product: Product;
  quantity: number;
};

export async function explodeBom(
  db: Executor,
  product: Product,
  quantity: number,
): Promise<ExplodedMaterial[]> {
  const accumulator = new Map<number, ExplodedMaterial>();
  await explodeRecursive(db, product, quantity, accumulator, 0);

  return [...accumulator.values()];
}

async function explodeRecursive(
  db: Executor,
  product: Product,
  quantity: number,
  accumulator: Map<number, ExplodedMaterial>,
  depth: number,
): Promise<void> {
  if (depth >= 20) {
    return;
  }

  const bomRows = await db.getAllAsync<BomRow>(
    'SELECT * FROM bill_of_materials WHERE parent_product_id = ? ORDER BY sequence ASC, id ASC',
    product.id,
  );

  if (bomRows.length === 0) {
    const existing = accumulator.get(product.id);

    if (existing) {
      existing.quantity = round4(existing.quantity + quantity);
    } else {
      accumulator.set(product.id, { product, quantity: round4(quantity) });
    }

    return;
  }

  for (const row of bomRows) {
    const child = await db.getFirstAsync<Product>('SELECT * FROM products WHERE id = ?', row.child_product_id);

    if (!child) {
      continue;
    }

    const requiredQty = effectiveQuantity(row) * quantity;
    await explodeRecursive(db, child, requiredQty, accumulator, depth + 1);
  }
}

// ── Overhead allocation service ─────────────────────────────────────────────

async function activeRates(db: Executor): Promise<OverheadRate[]> {
  return db.getAllAsync<OverheadRate>('SELECT * FROM overhead_rates WHERE is_active = 1 ORDER BY id ASC');
}

function baseValue(
  base: string,
  bases: {
    directMaterial: number;
    directLabor: number;
    laborHours: number;
    machineHours: number;
    units: number;
  },
): number {
  switch (base) {
    case 'direct_material':
      return bases.directMaterial;
    case 'direct_labor':
      return bases.directLabor;
    case 'labor_hours':
      return bases.laborHours;
    case 'machine_hours':
      return bases.machineHours;
    case 'units_produced':
      return bases.units;
    default:
      return 0;
  }
}

async function allocateOverhead(
  db: Executor,
  bases: {
    directMaterial: number;
    directLabor: number;
    laborHours: number;
    machineHours: number;
    units: number;
  },
): Promise<OverheadResult> {
  const rates = await activeRates(db);
  const details: OverheadResult['details'] = [];
  let total = 0;

  for (const rate of rates) {
    const value = baseValue(rate.allocation_base, bases);
    const allocated = value * rate.rate;
    total += allocated;
    details.push({
      overhead_rate_id: rate.id,
      name: rate.name,
      allocation_base: rate.allocation_base,
      base_value: round4(value),
      rate: rate.rate,
      allocated_cost: round4(allocated),
    });
  }

  return { total: round4(total), details };
}

export async function allocateForSale(
  db: Executor,
  directMaterial: number,
  units = 1,
): Promise<OverheadResult> {
  return allocateOverhead(db, {
    directMaterial,
    directLabor: 0,
    laborHours: 0,
    machineHours: 0,
    units,
  });
}

// ── COGS calculation service ────────────────────────────────────────────────

export async function calculateSaleCogs(
  db: Executor,
  product: Product,
  quantity: number,
  consumeInventory: boolean,
): Promise<CogsResult> {
  const available = await availableQuantity(db, product.id);
  const isSellable = product.type === 'finished_good' || product.type === 'semi_finished';

  let directMaterial = 0;
  let consumptionMode = 'bom_explosion';
  const consumptionDetails: LotConsumption[] = [];

  if (consumeInventory && isSellable && available + EPSILON >= quantity) {
    const consumption = await consumeStock(db, product, quantity, true);
    directMaterial = consumption.total_cost;
    consumptionMode = 'finished_goods_inventory';
    consumptionDetails.push(...consumption.lot_consumptions);
  } else {
    const rollUp = await rollUpCost(db, product, quantity);
    directMaterial = rollUp.total_cost;

    if (consumeInventory) {
      const leaves = await explodeBom(db, product, quantity);
      let consumedMaterial = 0;

      for (const leaf of leaves) {
        const consumption = await consumeStock(db, leaf.product, leaf.quantity, true);
        consumedMaterial += consumption.total_cost;
        consumptionDetails.push(...consumption.lot_consumptions);
      }

      directMaterial = round4(consumedMaterial);
    }
  }

  const overhead = await allocateForSale(db, directMaterial, quantity);
  const totalCogs = directMaterial + overhead.total;

  return {
    direct_material: round4(directMaterial),
    direct_labor: 0,
    manufacturing_overhead: round4(overhead.total),
    total_cogs: round4(totalCogs),
    unit_cogs: quantity > 0 ? round4(totalCogs / quantity) : 0,
    calculation_method: product.costing_method,
    breakdown: {
      consumption_mode: consumptionMode,
      inventory_consumed: consumeInventory,
      consumption_details: consumptionDetails,
      overhead: overhead.details,
    },
  };
}

export async function calculateProductionCogs(
  db: Executor,
  order: ProductionOrder,
): Promise<CogsResult> {
  const materials = await db.getAllAsync<ProductionMaterial>(
    'SELECT * FROM production_order_materials WHERE production_order_id = ?',
    order.id,
  );
  const labors = await db.getAllAsync<ProductionLabor>(
    'SELECT * FROM production_order_labors WHERE production_order_id = ?',
    order.id,
  );

  const directMaterial = materials.reduce((sum, m) => sum + m.total_cost, 0);
  const directLabor = labors.reduce((sum, l) => sum + l.total_cost, 0);
  const laborHours = labors.reduce((sum, l) => sum + l.labor_hours, 0);
  const completed = order.quantity_completed;

  const overhead = await allocateOverhead(db, {
    directMaterial,
    directLabor,
    laborHours,
    machineHours: order.machine_hours,
    units: completed,
  });

  const totalCogs = directMaterial + directLabor + overhead.total;

  return {
    direct_material: round4(directMaterial),
    direct_labor: round4(directLabor),
    manufacturing_overhead: round4(overhead.total),
    total_cogs: round4(totalCogs),
    unit_cogs: completed > 0 ? round4(totalCogs / completed) : 0,
    calculation_method: 'absorption_costing_production',
    breakdown: {
      materials: materials.map((m) => ({
        product_id: m.product_id,
        quantity_used: m.quantity_used,
        unit_cost: m.unit_cost,
        total_cost: m.total_cost,
      })),
      labors: labors.map((l) => ({
        description: l.description,
        labor_hours: l.labor_hours,
        hourly_rate: l.hourly_rate,
        total_cost: l.total_cost,
      })),
      overhead: overhead.details,
    },
  };
}

async function persistCogs(
  db: Executor,
  referenceType: string,
  referenceId: number,
  productId: number,
  quantity: number,
  result: CogsResult,
): Promise<SQLiteRunResult> {
  return db.runAsync(
    `INSERT INTO cogs_calculations
      (reference_type, reference_id, product_id, quantity, direct_material, direct_labor,
       manufacturing_overhead, total_cogs, unit_cogs, calculation_method, breakdown, calculated_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
    referenceType,
    referenceId,
    productId,
    quantity,
    result.direct_material,
    result.direct_labor,
    result.manufacturing_overhead,
    result.total_cogs,
    result.unit_cogs,
    result.calculation_method,
    JSON.stringify(result.breakdown),
    new Date().toISOString(),
  );
}

// ── Production order service ────────────────────────────────────────────────

export async function createProductionFromBom(
  db: Executor,
  product: Product,
  quantityPlanned: number,
  labors: LaborInput[],
  machineHours: number,
  notes: string | null,
): Promise<number> {
  const orderNumber = `PO-${new Date().toISOString().replace(/[-:.TZ]/g, '').slice(0, 14)}-${Math.floor(
    100 + Math.random() * 900,
  )}`;

  const order = await db.runAsync(
    `INSERT INTO production_orders
      (order_number, product_id, quantity_planned, quantity_completed, status, machine_hours, notes)
     VALUES (?, ?, ?, 0, 'draft', ?, ?)`,
    orderNumber,
    product.id,
    quantityPlanned,
    machineHours,
    notes,
  );

  const orderId = order.lastInsertRowId;
  const leaves = await explodeBom(db, product, quantityPlanned);

  for (const leaf of leaves) {
    await db.runAsync(
      `INSERT INTO production_order_materials
        (production_order_id, product_id, quantity_planned, quantity_used, unit_cost, total_cost)
       VALUES (?, ?, ?, 0, 0, 0)`,
      orderId,
      leaf.product.id,
      leaf.quantity,
    );
  }

  for (const labor of labors) {
    await db.runAsync(
      `INSERT INTO production_order_labors
        (production_order_id, description, labor_hours, hourly_rate, total_cost)
       VALUES (?, ?, ?, ?, ?)`,
      orderId,
      labor.description,
      labor.labor_hours,
      labor.hourly_rate,
      round4(labor.labor_hours * labor.hourly_rate),
    );
  }

  return orderId;
}

export async function startProduction(db: Executor, order: ProductionOrder): Promise<void> {
  if (order.status !== 'draft') {
    throw new Error('Hanya order draft yang bisa dimulai.');
  }

  await db.runAsync(
    "UPDATE production_orders SET status = 'in_progress', started_at = ? WHERE id = ?",
    new Date().toISOString(),
    order.id,
  );
}

export async function completeProduction(
  db: Executor,
  order: ProductionOrder,
  quantityCompleted?: number,
): Promise<void> {
  if (order.status === 'completed') {
    throw new Error('Order produksi sudah selesai.');
  }

  const completedQty = quantityCompleted ?? order.quantity_planned;
  const ratio = order.quantity_planned > 0 ? completedQty / order.quantity_planned : 0;

  const materials = await db.getAllAsync<ProductionMaterial>(
    'SELECT * FROM production_order_materials WHERE production_order_id = ?',
    order.id,
  );

  for (const material of materials) {
    const product = await db.getFirstAsync<Product>('SELECT * FROM products WHERE id = ?', material.product_id);

    if (!product) {
      continue;
    }

    const qtyToUse = material.quantity_planned * ratio;
    const consumption = await consumeStock(db, product, qtyToUse, true);

    await db.runAsync(
      'UPDATE production_order_materials SET quantity_used = ?, unit_cost = ?, total_cost = ? WHERE id = ?',
      round4(qtyToUse),
      consumption.average_unit_cost,
      consumption.total_cost,
      material.id,
    );
  }

  await db.runAsync(
    "UPDATE production_orders SET status = 'completed', quantity_completed = ?, completed_at = ? WHERE id = ?",
    completedQty,
    new Date().toISOString(),
    order.id,
  );

  const freshOrder = await db.getFirstAsync<ProductionOrder>(
    'SELECT * FROM production_orders WHERE id = ?',
    order.id,
  );

  if (!freshOrder) {
    return;
  }

  const cogs = await calculateProductionCogs(db, freshOrder);
  const finished = await db.getFirstAsync<Product>('SELECT * FROM products WHERE id = ?', order.product_id);

  if (finished) {
    await receiveStock(
      db,
      finished.id,
      completedQty,
      cogs.unit_cogs,
      freshOrder.order_number,
      'ProductionOrder',
      order.id,
    );
  }

  await persistCogs(db, 'ProductionOrder', order.id, order.product_id, completedQty, cogs);
}

export async function recordSaleCogs(
  db: Executor,
  product: Product,
  quantity: number,
): Promise<CogsResult> {
  const result = await calculateSaleCogs(db, product, quantity, true);
  await persistCogs(db, 'SalesTransaction', Date.now(), product.id, quantity, result);

  return result;
}
