<?php

namespace App\Http\Controllers\Web;

use App\Enums\CostingMethod;
use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\BillOfMaterial;
use App\Models\OverheadRate;
use App\Models\Product;
use App\Models\ProductAddon;
use App\Services\BomCostService;
use App\Services\CogsCalculationService;
use App\Services\OverheadAllocationService;
use App\Services\ProductDeletionService;
use App\Services\ProductHppService;
use App\Support\Format;
use App\Support\MaterialUnits;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;

class ProductController extends Controller
{
  public function index()
  {
    $products = Product::query()
      ->where('type', ProductType::FinishedGood->value)
      ->withCount('billOfMaterials')
      ->orderBy('name')
      ->get();

    return view('products.index', [
      'products' => $products,
      'format' => Format::class,
    ]);
  }

  public function create()
  {
    return view('products.create', [
      'costingMethods' => CostingMethod::cases(),
    ]);
  }

  public function store(StoreProductRequest $request, ProductHppService $productHppService)
  {
    @set_time_limit(60);

    try {
      $data = $this->productPayload($request->validated());
      $data['type'] = ProductType::FinishedGood->value;
      $data['is_menu_item'] = true;

      if (empty($data['sku'])) {
        $data['sku'] = $this->generateMenuSku($data['name']);
      }

      $product = Product::create($data);
      $productHppService->markAsMenuItem($product, true);

      return redirect()->route('products.show', $product)
        ->with('success', 'Menu ditambahkan. Lanjut isi bahan resepnya.');
    } catch (\Throwable $e) {
      report($e);

      return response()->view('errors.timeout', [
        'retryUrl' => route('products.create'),
        'productsUrl' => route('products.index'),
        'homeUrl' => url('/'),
      ], \App\Support\ServerBusy::isServerBusy($e) ? 504 : 500);
    }
  }

  public function show(Product $product, BomCostService $bomCostService, OverheadAllocationService $overheadService)
  {
    @set_time_limit(60);

    try {
      return $this->renderRecipeShow($product, $bomCostService, $overheadService);
    } catch (\Throwable $e) {
      report($e);

      return response()->view('errors.timeout', [
        'retryUrl' => route('products.show', $product),
        'productsUrl' => route('products.index'),
        'homeUrl' => url('/'),
      ], \App\Support\ServerBusy::isServerBusy($e) ? 504 : 500);
    }
  }

  /**
   * Cari bahan untuk form resep/add-on — pagination (paket halaman), jangan load semua.
   */
  public function recipeMaterials(Request $request, Product $product)
  {
    if ($product->effectiveType() === ProductType::RawMaterial) {
      return response()->json(['message' => 'Tidak valid.', 'data' => [], 'meta' => []], 404);
    }

    $validated = $request->validate([
      'q' => ['nullable', 'string', 'max:100'],
      'type' => ['nullable', 'in:raw_material,semi_finished'],
      'page' => ['nullable', 'integer', 'min:1'],
      'per_page' => ['nullable', 'integer', 'min:5', 'max:50'],
    ]);

    $page = (int) ($validated['page'] ?? 1);
    $perPage = (int) ($validated['per_page'] ?? 20);
    $q = trim((string) ($validated['q'] ?? ''));
    $type = $validated['type'] ?? null;

    $allowedTypes = $product->effectiveType() === ProductType::SemiFinished
      ? [ProductType::RawMaterial->value]
      : [ProductType::RawMaterial->value, ProductType::SemiFinished->value];

    if ($type !== null && ! in_array($type, $allowedTypes, true)) {
      return response()->json([
        'data' => [],
        'meta' => [
          'page' => $page,
          'per_page' => $perPage,
          'has_more' => false,
          'total' => 0,
        ],
      ]);
    }

    $query = Product::query()
      ->where('is_active', true)
      ->whereIn('type', $type ? [$type] : $allowedTypes)
      ->orderBy('name');

    if ($q !== '') {
      $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';
      $query->where(function ($builder) use ($like) {
        $builder->where('name', 'like', $like)
          ->orWhere('sku', 'like', $like);
      });
    }

    $paginator = $query->paginate($perPage, ['id', 'name', 'type', 'unit', 'is_active'], 'page', $page);

    $data = collect($paginator->items())->map(function (Product $material) {
      $unit = MaterialUnits::normalize($material->unit) ?: $material->unit;
      $options = MaterialUnits::recipeOptions($material->unit);

      return [
        'id' => $material->id,
        'name' => $material->name,
        'type' => $material->effectiveType()->value,
        'type_label' => $material->effectiveType()->label(),
        'unit' => $unit,
        'unit_label' => MaterialUnits::label($material->unit),
        'units' => [
          'unit' => $unit,
          'label' => MaterialUnits::label($material->unit),
          'options' => $options,
          'preferred' => MaterialUnits::preferredInputUnit($material->unit),
        ],
      ];
    })->values();

    return response()->json([
      'data' => $data,
      'meta' => [
        'page' => $paginator->currentPage(),
        'per_page' => $paginator->perPage(),
        'has_more' => $paginator->hasMorePages(),
        'total' => $paginator->total(),
      ],
    ]);
  }

  private function renderRecipeShow(
    Product $product,
    BomCostService $bomCostService,
    OverheadAllocationService $overheadService,
  ) {
    if ($product->effectiveType() === ProductType::RawMaterial) {
      return redirect()->route('materials.index');
    }

    $product->load(['billOfMaterials.childProduct', 'addons.material']);

    // Hanya seed unit untuk bahan yang sudah dipakai di resep/add-on (bukan semua katalog).
    $materialUnits = [];
    foreach ($product->billOfMaterials as $bom) {
      $child = $bom->childProduct;
      if (! $child) {
        continue;
      }
      $materialUnits[(string) $child->id] = [
        'unit' => MaterialUnits::normalize($child->unit) ?: $child->unit,
        'label' => MaterialUnits::label($child->unit),
        'options' => MaterialUnits::recipeOptions($child->unit),
        'preferred' => MaterialUnits::preferredInputUnit($child->unit),
      ];
    }
    foreach ($product->addons as $addon) {
      $mat = $addon->material;
      if (! $mat) {
        continue;
      }
      $materialUnits[(string) $mat->id] = [
        'unit' => MaterialUnits::normalize($mat->unit) ?: $mat->unit,
        'label' => MaterialUnits::label($mat->unit),
        'options' => MaterialUnits::recipeOptions($mat->unit),
        'preferred' => MaterialUnits::preferredInputUnit($mat->unit),
      ];
    }

    $allowedTypes = $product->effectiveType() === ProductType::SemiFinished
      ? [ProductType::RawMaterial->value]
      : [ProductType::RawMaterial->value, ProductType::SemiFinished->value];

    $hasMaterials = Product::query()
      ->where('is_active', true)
      ->whereIn('type', $allowedTypes)
      ->exists();

    $selectedAddonMaterials = $product->addons
      ->pluck('material')
      ->filter()
      ->unique('id')
      ->values();

    $seedIds = collect([old('child_product_id'), old('material_product_id')])
      ->merge($selectedAddonMaterials->pluck('id'))
      ->filter()
      ->map(fn ($id) => (int) $id)
      ->unique()
      ->values();

    $seedMaterials = $seedIds->isEmpty()
      ? collect()
      : Product::query()
        ->whereIn('id', $seedIds)
        ->get(['id', 'name', 'type', 'unit', 'is_active']);

    foreach ($seedMaterials as $material) {
      $materialUnits[(string) $material->id] = [
        'unit' => MaterialUnits::normalize($material->unit) ?: $material->unit,
        'label' => MaterialUnits::label($material->unit),
        'options' => MaterialUnits::recipeOptions($material->unit),
        'preferred' => MaterialUnits::preferredInputUnit($material->unit),
      ];
    }

    $overheadRates = OverheadRate::query()
      ->where('is_active', true)
      ->orderBy('name')
      ->get(['id', 'name', 'allocation_base', 'rate', 'description', 'is_active']);

    // Hitung biaya hanya dari bahan di resep produk ini (bukan seluruh katalog).
    $bomLineCosts = [];
    $materialCost = 0.0;
    $overheadCost = 0.0;
    $overheadDetails = [];
    $estimatedModal = (float) ($product->unit_hpp ?: 0);

    if ($product->billOfMaterials->isNotEmpty()) {
      $rollUp = $bomCostService->rollUpCost($product, 1);
      $materialCost = (float) ($rollUp['total_cost'] ?? 0);
      $overhead = $overheadService->allocateForSale(
        directMaterial: $materialCost,
        units: 1,
        overheadRateIds: $overheadRates->pluck('id')->all(),
      );
      $overheadCost = (float) ($overhead['total'] ?? 0);
      $overheadDetails = $overhead['details'] ?? [];
      $estimatedModal = $materialCost + $overheadCost;

      foreach ($rollUp['components'] ?? [] as $component) {
        $bomLineCosts[(int) $component['product_id']] = $component;
      }
    }

    $bomChildren = $product->billOfMaterials
      ->pluck('childProduct')
      ->filter()
      ->unique('id')
      ->values();
    $this->hydrateAvailableQuantities($bomChildren);

    $emptyMaterials = collect();

    return view('products.show', [
      'product' => $product,
      'hasMaterials' => $hasMaterials,
      'allProducts' => $emptyMaterials,
      'rawMaterials' => $emptyMaterials,
      'addonRawMaterials' => $emptyMaterials,
      'addonSemiFinishedMaterials' => $emptyMaterials,
      'seedMaterials' => $seedMaterials,
      'selectedAddonMaterials' => $selectedAddonMaterials,
      'recipeMaterialsUrl' => route('products.recipe-materials', $product),
      'allowSemiFinishedInRecipe' => $product->effectiveType() !== ProductType::SemiFinished,
      'materialUnits' => $materialUnits,
      'bomLineCosts' => $bomLineCosts,
      'materialCost' => $materialCost,
      'overheadCost' => $overheadCost,
      'overheadDetails' => $overheadDetails,
      'overheadRates' => $overheadRates,
      'estimatedModal' => $estimatedModal,
      'format' => Format::class,
      'units' => MaterialUnits::class,
    ]);
  }

  public function edit(Product $product)
  {
    if ($product->effectiveType() === ProductType::RawMaterial) {
      return redirect()->route('materials.index');
    }

    return view('products.edit', [
      'product' => $product,
      'costingMethods' => CostingMethod::cases(),
      'format' => Format::class,
    ]);
  }

  public function update(UpdateProductRequest $request, Product $product, ProductHppService $productHppService)
  {
    if ($product->effectiveType() === ProductType::RawMaterial) {
      return redirect()->route('materials.index');
    }

    $data = $this->productPayload($request->validated());
    $product->update($data);
    $productHppService->markAsMenuItem($product, (bool) ($data['is_menu_item'] ?? false));

    return redirect()->route('products.show', $product)->with('success', 'Produk berhasil diperbarui.');
  }

  public function destroy(Product $product, ProductDeletionService $deletionService)
  {
    $isRaw = $product->effectiveType() === ProductType::RawMaterial;

    try {
      $deletionService->delete($product);
    } catch (\RuntimeException $e) {
      return back()->with('error', $e->getMessage());
    }

    return redirect()
      ->route($isRaw ? 'materials.index' : 'products.index')
      ->with('success', 'Data berhasil dihapus.');
  }

  public function storeBom(Request $request, Product $product)
  {
    @set_time_limit(60);

    try {
      $validated = $request->validate([
        'child_product_id' => [
          'required',
          'exists:products,id',
          'not_in:'.$product->id,
        ],
        'quantity' => ['required', 'numeric', 'gt:0'],
        'unit' => ['required', 'string', 'max:20'],
        'scrap_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
        'sequence' => ['nullable', 'integer', 'min:0'],
      ]);

      $child = Product::query()->findOrFail($validated['child_product_id']);

      $allowedChildTypes = $product->effectiveType() === ProductType::SemiFinished
        ? [ProductType::RawMaterial]
        : [ProductType::RawMaterial, ProductType::SemiFinished];

      if (! in_array($child->effectiveType(), $allowedChildTypes, true)) {
        throw ValidationException::withMessages([
          'child_product_id' => $product->effectiveType() === ProductType::SemiFinished
            ? 'Resep bahan jadi hanya boleh dari bahan baku.'
            : 'Hanya bahan baku atau bahan jadi yang bisa dimasukkan ke resep.',
        ]);
      }

      if (! $child->is_active) {
        throw ValidationException::withMessages([
          'child_product_id' => 'Bahan ini tidak aktif.',
        ]);
      }

      $quantity = $this->quantityInStockUnit(
        (float) $validated['quantity'],
        $validated['unit'],
        $child->unit,
      );

      BillOfMaterial::updateOrCreate(
        [
          'parent_product_id' => $product->id,
          'child_product_id' => $validated['child_product_id'],
        ],
        [
          'quantity' => $quantity,
          'scrap_percentage' => $validated['scrap_percentage'] ?? 0,
          'sequence' => $validated['sequence'] ?? 0,
        ],
      );

      return redirect()->route('products.show', $product)->with('success', 'Bahan resep ditambahkan.');
    } catch (ValidationException $e) {
      throw $e;
    } catch (\Throwable $e) {
      report($e);

      return response()->view('errors.timeout', [
        'retryUrl' => route('products.show', $product),
        'productsUrl' => route('products.index'),
        'homeUrl' => url('/'),
      ], \App\Support\ServerBusy::isServerBusy($e) ? 504 : 500);
    }
  }

  public function updateBom(Request $request, Product $product, BillOfMaterial $bom)
  {
    if ($bom->parent_product_id !== $product->id) {
      abort(404);
    }

    $validated = $request->validate([
      'quantity' => ['required', 'numeric', 'gt:0'],
      'unit' => ['required', 'string', 'max:20'],
      'scrap_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
      'sequence' => ['nullable', 'integer', 'min:0'],
    ]);

    $child = $bom->childProduct;

    if (! $child) {
      throw ValidationException::withMessages([
        'quantity' => 'Bahan pada resep tidak ditemukan.',
      ]);
    }

    $quantity = $this->quantityInStockUnit(
      (float) $validated['quantity'],
      $validated['unit'],
      $child->unit,
    );

    $bom->update([
      'quantity' => $quantity,
      'scrap_percentage' => $validated['scrap_percentage'] ?? $bom->scrap_percentage,
      'sequence' => $validated['sequence'] ?? $bom->sequence,
    ]);

    return redirect()->route('products.show', $product)->with('success', 'Resep diperbarui.');
  }

  public function destroyBom(Product $product, BillOfMaterial $bom)
  {
    if ($bom->parent_product_id !== $product->id) {
      abort(404);
    }

    $bom->delete();

    return redirect()->route('products.show', $product)->with('success', 'Bahan dihapus dari resep.');
  }

  public function calculateModal(Request $request, Product $product, CogsCalculationService $cogsService)
  {
    if ($product->effectiveType() === ProductType::RawMaterial) {
      return redirect()->route('materials.index');
    }

    $validated = $request->validate([
      'overhead_rate_ids' => ['nullable', 'array'],
      'overhead_rate_ids.*' => ['integer', 'exists:overhead_rates,id'],
    ]);

    $overheadRateIds = array_values(array_unique(array_map(
      'intval',
      $validated['overhead_rate_ids'] ?? [],
    )));

    try {
      $result = $cogsService->recalculateRecipeHpp($product, $overheadRateIds);
    } catch (RuntimeException $e) {
      return redirect()->route('products.show', $product)->with('error', $e->getMessage());
    }

    $overheadDetails = $result->breakdown['overhead']['details'] ?? [];

    $message = sprintf(
      'Modal dihitung: %s / %s (bahan %s + biaya lain %s).',
      Format::rupiah($result->unitHpp, 0),
      $product->unit,
      Format::rupiah($result->directMaterial, 0),
      Format::rupiah($result->manufacturingOverhead, 0),
    );

    return redirect()
      ->route('products.show', $product)
      ->with('success', $message)
      ->with('modal_overhead_details', $overheadDetails)
      ->with('modal_material_cost', $result->directMaterial)
      ->with('modal_total', $result->unitHpp);
  }

  public function storeAddon(Request $request, Product $product)
  {
    if ($product->effectiveType() === ProductType::RawMaterial) {
      return redirect()->route('materials.index');
    }

    $validated = $request->validate([
      'name' => ['required', 'string', 'max:100'],
      'selling_price' => ['required'],
      'material_product_id' => ['nullable', 'exists:products,id'],
      'material_quantity' => ['nullable', 'numeric', 'gt:0'],
      'unit' => ['nullable', 'string', 'max:20'],
    ]);

    $sellingPrice = Format::parseRupiah($validated['selling_price']);
    if ($sellingPrice < 0) {
      throw ValidationException::withMessages([
        'selling_price' => 'Harga add-on tidak boleh negatif.',
      ]);
    }

    $materialId = $validated['material_product_id'] ?? null;
    $materialQty = null;

    if ($materialId) {
      $material = Product::query()->findOrFail($materialId);
      if (! in_array($material->effectiveType(), [ProductType::RawMaterial, ProductType::SemiFinished], true)) {
        throw ValidationException::withMessages([
          'material_product_id' => 'Add-on hanya bisa dihubungkan ke bahan baku atau bahan jadi.',
        ]);
      }

      if (empty($validated['material_quantity'])) {
        throw ValidationException::withMessages([
          'material_quantity' => 'Isi jumlah bahan untuk add-on ini.',
        ]);
      }

      $materialQty = $this->quantityInStockUnit(
        (float) $validated['material_quantity'],
        $validated['unit'] ?? $material->unit,
        $material->unit,
      );
    }

    $maxOrder = (int) $product->addons()->max('sort_order');

    ProductAddon::create([
      'product_id' => $product->id,
      'name' => trim($validated['name']),
      'selling_price' => $sellingPrice,
      'material_product_id' => $materialId,
      'material_quantity' => $materialQty,
      'is_active' => true,
      'sort_order' => $maxOrder + 1,
    ]);

    return redirect()->route('products.show', $product)->with('success', 'Add-on ditambahkan.');
  }

  public function updateAddon(Request $request, Product $product, ProductAddon $addon)
  {
    if ($addon->product_id !== $product->id) {
      abort(404);
    }

    $validated = $request->validate([
      'name' => ['required', 'string', 'max:100'],
      'selling_price' => ['required'],
      'material_product_id' => ['nullable', 'exists:products,id'],
      'material_quantity' => ['nullable', 'numeric', 'gt:0'],
      'unit' => ['nullable', 'string', 'max:20'],
      'is_active' => ['sometimes', 'boolean'],
    ]);

    $sellingPrice = Format::parseRupiah($validated['selling_price']);
    if ($sellingPrice < 0) {
      throw ValidationException::withMessages([
        'selling_price' => 'Harga add-on tidak boleh negatif.',
      ]);
    }

    $materialId = $validated['material_product_id'] ?? null;
    $materialQty = null;

    if ($materialId) {
      $material = Product::query()->findOrFail($materialId);
      if (! in_array($material->effectiveType(), [ProductType::RawMaterial, ProductType::SemiFinished], true)) {
        throw ValidationException::withMessages([
          'material_product_id' => 'Add-on hanya bisa dihubungkan ke bahan baku atau bahan jadi.',
        ]);
      }

      if (empty($validated['material_quantity'])) {
        throw ValidationException::withMessages([
          'material_quantity' => 'Isi jumlah bahan untuk add-on ini.',
        ]);
      }

      $materialQty = $this->quantityInStockUnit(
        (float) $validated['material_quantity'],
        $validated['unit'] ?? $material->unit,
        $material->unit,
      );
    }

    $addon->update([
      'name' => trim($validated['name']),
      'selling_price' => $sellingPrice,
      'material_product_id' => $materialId,
      'material_quantity' => $materialQty,
      'is_active' => $request->boolean('is_active'),
    ]);

    return redirect()->route('products.show', $product)->with('success', 'Add-on diperbarui.');
  }

  public function destroyAddon(Product $product, ProductAddon $addon)
  {
    if ($addon->product_id !== $product->id) {
      abort(404);
    }

    $addon->delete();

    return redirect()->route('products.show', $product)->with('success', 'Add-on dihapus.');
  }

  private function quantityInStockUnit(float $quantity, ?string $inputUnit, ?string $stockUnit): float
  {
    try {
      return MaterialUnits::convert($quantity, $inputUnit, $stockUnit);
    } catch (InvalidArgumentException $e) {
      throw ValidationException::withMessages([
        'unit' => $e->getMessage(),
      ]);
    }
  }

  /**
   * @param  \Illuminate\Support\Collection<int, Product>  $products
   */
  private function hydrateAvailableQuantities($products): void
  {
    $ids = $products
      ->pluck('id')
      ->filter()
      ->map(fn ($id) => (int) $id)
      ->unique()
      ->values()
      ->all();

    if ($ids === []) {
      return;
    }

    $onHand = [];
    $reserved = [];

    try {
      if (Schema::hasTable('inventory_lots')) {
        $onHand = DB::table('inventory_lots')
          ->selectRaw('product_id, COALESCE(SUM(quantity_remaining), 0) as qty')
          ->whereIn('product_id', $ids)
          ->groupBy('product_id')
          ->pluck('qty', 'product_id')
          ->all();
      }

      if (Product::inventoryReservationsEnabled()) {
        $reserved = DB::table('inventory_reservations')
          ->selectRaw('product_id, COALESCE(SUM(quantity), 0) as qty')
          ->whereIn('product_id', $ids)
          ->groupBy('product_id')
          ->pluck('qty', 'product_id')
          ->all();
      }
    } catch (\Throwable $e) {
      report($e);
    }

    $allowNegative = (bool) config('pos.allow_negative_stock', true);

    foreach ($products as $product) {
      $qty = (float) ($onHand[$product->id] ?? 0) - (float) ($reserved[$product->id] ?? 0);
      $qty = round($qty, 6);
      $product->setAttribute('available_qty', $allowNegative ? $qty : max(0.0, $qty));
    }
  }

  /** @param  array<string, mixed>  $data */
  private function productPayload(array $data): array
  {
    unset($data['unit_preset'], $data['unit_custom']);

    $type = $data['type'] ?? ProductType::FinishedGood->value;
    $sellableType = in_array($type, [ProductType::FinishedGood->value, ProductType::SemiFinished->value], true);

    if (! array_key_exists('is_menu_item', $data)) {
      $data['is_menu_item'] = $sellableType;
    }

    if ($sellableType && (float) ($data['standard_cost'] ?? 0) > 0 && (float) ($data['unit_hpp'] ?? 0) <= 0) {
      $data['unit_hpp'] = $data['standard_cost'];
    }

    return $data;
  }

  private function generateMenuSku(string $name): string
  {
    $base = Str::upper(Str::slug($name, '-'));
    $base = $base !== '' ? Str::limit($base, 20, '') : 'MENU';

    do {
      $sku = 'MENU-'.$base.'-'.random_int(100, 999);
    } while (Product::where('sku', $sku)->exists());

    return $sku;
  }
}
