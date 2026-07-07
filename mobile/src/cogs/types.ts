export type ProductType = 'raw_material' | 'semi_finished' | 'finished_good';
export type CostingMethod = 'fifo' | 'weighted_average' | 'standard';
export type ProductionStatus = 'draft' | 'in_progress' | 'completed' | 'cancelled';
export type AllocationBase =
  | 'direct_material'
  | 'direct_labor'
  | 'labor_hours'
  | 'machine_hours'
  | 'units_produced';

export type Product = {
  id: number;
  sku: string;
  name: string;
  type: ProductType;
  unit: string;
  standard_cost: number;
  costing_method: CostingMethod;
  description: string | null;
  is_active: number;
};

export type BomRow = {
  id: number;
  parent_product_id: number;
  child_product_id: number;
  quantity: number;
  scrap_percentage: number;
  sequence: number;
};

export type InventoryLot = {
  id: number;
  product_id: number;
  lot_number: string | null;
  quantity_received: number;
  quantity_remaining: number;
  unit_cost: number;
  received_at: string;
  source_type: string | null;
  source_id: number | null;
};

export type OverheadRate = {
  id: number;
  name: string;
  allocation_base: AllocationBase;
  rate: number;
  is_active: number;
  description: string | null;
};

export type ProductionOrder = {
  id: number;
  order_number: string;
  product_id: number;
  quantity_planned: number;
  quantity_completed: number;
  status: ProductionStatus;
  machine_hours: number;
  started_at: string | null;
  completed_at: string | null;
  notes: string | null;
};

export type ProductionMaterial = {
  id: number;
  production_order_id: number;
  product_id: number;
  quantity_planned: number;
  quantity_used: number;
  unit_cost: number;
  total_cost: number;
};

export type ProductionLabor = {
  id: number;
  production_order_id: number;
  description: string;
  labor_hours: number;
  hourly_rate: number;
  total_cost: number;
};

export type CogsCalculation = {
  id: number;
  reference_type: string;
  reference_id: number;
  product_id: number;
  quantity: number;
  direct_material: number;
  direct_labor: number;
  manufacturing_overhead: number;
  total_cogs: number;
  unit_cogs: number;
  calculation_method: string;
  breakdown: string;
  calculated_at: string;
};

export type LaborInput = {
  description: string;
  labor_hours: number;
  hourly_rate: number;
};

export type BomNode = {
  product_id: number;
  name: string;
  sku: string;
  unit: string;
  unit_cost: number;
  total_cost: number;
  is_leaf: boolean;
  bom_quantity?: number;
  scrap_percentage?: number;
  effective_quantity?: number;
  components: BomNode[];
};

export const PRODUCT_TYPE_LABEL: Record<ProductType, string> = {
  raw_material: 'Bahan Baku',
  semi_finished: 'Barang Setengah Jadi',
  finished_good: 'Barang Jadi',
};

export const COSTING_METHOD_LABEL: Record<CostingMethod, string> = {
  fifo: 'FIFO (First In First Out)',
  weighted_average: 'Rata-rata Tertimbang',
  standard: 'Biaya Standar',
};

export const ALLOCATION_BASE_LABEL: Record<AllocationBase, string> = {
  direct_material: 'Bahan Langsung',
  direct_labor: 'Tenaga Kerja Langsung',
  labor_hours: 'Jam Kerja',
  machine_hours: 'Jam Mesin',
  units_produced: 'Unit Produksi',
};

export const PRODUCTION_STATUS_LABEL: Record<ProductionStatus, string> = {
  draft: 'Draft',
  in_progress: 'Berjalan',
  completed: 'Selesai',
  cancelled: 'Batal',
};
