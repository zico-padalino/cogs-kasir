<?php

namespace Tests\Unit;

use App\Support\MaterialUnits;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MaterialUnitsTest extends TestCase
{
    public function test_converts_gram_to_kg(): void
    {
        $this->assertEqualsWithDelta(0.1, MaterialUnits::convert(100, 'gr', 'kg'), 0.0000001);
        $this->assertEqualsWithDelta(0.05, MaterialUnits::convert(50, 'gram', 'kg'), 0.0000001);
    }

    public function test_converts_ml_to_liter(): void
    {
        $this->assertEqualsWithDelta(0.25, MaterialUnits::convert(250, 'ml', 'liter'), 0.0000001);
    }

    public function test_same_unit_passthrough(): void
    {
        $this->assertEqualsWithDelta(2.5, MaterialUnits::convert(2.5, 'pcs', 'buah'), 0.0000001);
    }

    public function test_rejects_incompatible_units(): void
    {
        $this->expectException(InvalidArgumentException::class);
        MaterialUnits::convert(1, 'gr', 'liter');
    }

    public function test_count_recipe_options_only_stock_unit(): void
    {
        $options = MaterialUnits::recipeOptions('pcs');

        $this->assertArrayHasKey('pcs', $options);
        $this->assertArrayNotHasKey('bungkus', $options);
        $this->assertCount(1, $options);
    }

    public function test_mass_recipe_options_include_kg_and_gram(): void
    {
        $options = MaterialUnits::recipeOptions('kg');

        $this->assertArrayHasKey('kg', $options);
        $this->assertArrayHasKey('gr', $options);
    }

    public function test_present_shows_gram_for_small_kg(): void
    {
        $presented = MaterialUnits::present(0.05, 'kg');

        $this->assertSame('gr', $presented['unit']);
        $this->assertEqualsWithDelta(50.0, $presented['quantity'], 0.0000001);
    }
}
