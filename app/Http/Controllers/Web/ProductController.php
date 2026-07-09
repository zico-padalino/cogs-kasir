<?php

namespace App\Http\Controllers\Web;

use App\Enums\CostingMethod;
use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\BillOfMaterial;
use App\Models\Product;
use App\Services\ProductDeletionService;
use App\Services\ProductHppService;
use App\Support\Format;
use Illuminate\Support\Str;

class ProductController extends Controller
{
  public function index()
  {
    $products = Product::query()
      ->whereIn('type', [ProductType::SemiFinished->value, ProductType::FinishedGood->value])
      ->withCount('billOfMaterials')
      ->latest()
      ->paginate(15);

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
  }

  public function show(Product $product)
  {
    if ($product->type === ProductType::RawMaterial) {
      return redirect()->route('materials.index');
    }

    $product->load(['billOfMaterials.childProduct']);

    $allProducts = Product::query()
      ->where('type', ProductType::RawMaterial->value)
      ->where('is_active', true)
      ->orderBy('name')
      ->get();

    return view('products.show', [
      'product' => $product,
      'allProducts' => $allProducts,
      'format' => Format::class,
    ]);
  }

  public function edit(Product $product)
  {
    if ($product->type === ProductType::RawMaterial) {
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
    if ($product->type === ProductType::RawMaterial) {
      return redirect()->route('materials.index');
    }

    $data = $this->productPayload($request->validated());
    $product->update($data);
    $productHppService->markAsMenuItem($product, (bool) ($data['is_menu_item'] ?? false));

    return redirect()->route('products.show', $product)->with('success', 'Produk berhasil diperbarui.');
  }

  public function destroy(Product $product, ProductDeletionService $deletionService)
  {
    $type = $product->type;

    try {
      $deletionService->delete($product);
    } catch (\RuntimeException $e) {
      return back()->with('error', $e->getMessage());
    }

    $route = $type === ProductType::RawMaterial ? 'materials.index' : 'products.index';

    return redirect()->route($route)->with('success', 'Data berhasil dihapus.');
  }

  public function storeBom(Request $request, Product $product)
  {
    $validated = $request->validate([
      'child_product_id' => ['required', 'exists:products,id', 'not_in:'.$product->id],
      'quantity' => ['required', 'numeric', 'gt:0'],
      'scrap_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
      'sequence' => ['nullable', 'integer', 'min:0'],
    ]);

    BillOfMaterial::updateOrCreate(
      [
        'parent_product_id' => $product->id,
        'child_product_id' => $validated['child_product_id'],
      ],
      [
        'quantity' => $validated['quantity'],
        'scrap_percentage' => $validated['scrap_percentage'] ?? 0,
        'sequence' => $validated['sequence'] ?? 0,
      ],
    );

    return redirect()->route('products.show', $product)->with('success', 'Bahan resep ditambahkan.');
  }

  public function updateBom(Request $request, Product $product, BillOfMaterial $bom)
  {
    if ($bom->parent_product_id !== $product->id) {
      abort(404);
    }

    $validated = $request->validate([
      'quantity' => ['required', 'numeric', 'gt:0'],
      'scrap_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
      'sequence' => ['nullable', 'integer', 'min:0'],
    ]);

    $bom->update($validated);

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

  /** @param  array<string, mixed>  $data */
  private function productPayload(array $data): array
  {
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
