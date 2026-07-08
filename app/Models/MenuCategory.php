<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MenuCategory extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return static::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name', 'slug')
            ->all();
    }

    public static function makeSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'kategori';
        $base = Str::limit($base, 50, '');
        $slug = $base;
        $suffix = 2;

        while (
            static::query()
                ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = Str::limit($base, 46, '').'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
