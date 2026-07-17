<?php

namespace App\Http\Controllers\Api\Kasir;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMenuCategoryRequest;
use App\Http\Resources\Kasir\MenuCategoryResource;
use App\Models\MenuCategory;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

class MenuCategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $usage = Product::query()
            ->selectRaw('menu_category, COUNT(*) as total')
            ->whereNotNull('menu_category')
            ->groupBy('menu_category')
            ->pluck('total', 'menu_category');

        $categories = MenuCategory::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function (MenuCategory $category) use ($usage) {
                $category->product_count = (int) ($usage[$category->slug] ?? 0);

                return $category;
            });

        return response()->json([
            'data' => MenuCategoryResource::collection($categories),
        ]);
    }

    public function store(StoreMenuCategoryRequest $request): JsonResponse
    {
        $name = trim($request->string('name')->toString());
        $maxSort = (int) MenuCategory::query()->max('sort_order');

        $category = MenuCategory::query()->create([
            'name' => $name,
            'slug' => MenuCategory::makeSlug($name),
            'sort_order' => $maxSort + 1,
        ]);
        $category->product_count = 0;

        return response()->json([
            'message' => 'Kategori "'.$name.'" ditambahkan.',
            'data' => new MenuCategoryResource($category),
        ], 201);
    }

    public function destroy(MenuCategory $menuCategory): JsonResponse
    {
        $inUse = Product::query()
            ->where('menu_category', $menuCategory->slug)
            ->exists();

        if ($inUse) {
            return response()->json([
                'message' => 'Kategori "'.$menuCategory->name.'" masih dipakai menu. Pindahkan atau ubah kategori menu terlebih dahulu.',
            ], 422);
        }

        $name = $menuCategory->name;
        $menuCategory->delete();

        return response()->json([
            'message' => 'Kategori "'.$name.'" dihapus.',
        ]);
    }
}
