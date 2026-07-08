<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMenuCategoryRequest;
use App\Models\MenuCategory;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class MenuCategoryController extends Controller
{
    public function index(): View
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

        return view('kasir.menu-categories.index', [
            'categories' => $categories,
        ]);
    }

    public function store(StoreMenuCategoryRequest $request): RedirectResponse
    {
        $name = trim($request->string('name')->toString());
        $maxSort = (int) MenuCategory::query()->max('sort_order');

        MenuCategory::query()->create([
            'name' => $name,
            'slug' => MenuCategory::makeSlug($name),
            'sort_order' => $maxSort + 1,
        ]);

        return redirect()
            ->route('kasir.menu-categories.index')
            ->with('success', 'Kategori "'.$name.'" ditambahkan.');
    }

    public function destroy(MenuCategory $menuCategory): RedirectResponse
    {
        $inUse = Product::query()
            ->where('menu_category', $menuCategory->slug)
            ->exists();

        if ($inUse) {
            return redirect()
                ->route('kasir.menu-categories.index')
                ->with('error', 'Kategori "'.$menuCategory->name.'" masih dipakai menu. Pindahkan atau ubah kategori menu terlebih dahulu.');
        }

        $name = $menuCategory->name;
        $menuCategory->delete();

        return redirect()
            ->route('kasir.menu-categories.index')
            ->with('success', 'Kategori "'.$name.'" dihapus.');
    }
}
