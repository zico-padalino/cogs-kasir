<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\ShopSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_settings_page(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.settings.edit'))
            ->assertOk()
            ->assertSee('Pengaturan')
            ->assertSee('Nama toko');
    }

    public function test_non_admin_cannot_open_settings(): void
    {
        $kasir = User::factory()->kasir()->create();

        $this->actingAs($kasir)
            ->get(route('admin.settings.edit'))
            ->assertRedirect();
    }

    public function test_admin_can_update_shop_identity_and_logo(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->put(route('admin.settings.update'), [
            'shop_name' => 'Kedai Joan',
            'shop_title' => 'Kopi & Cemilan',
            'logo' => UploadedFile::fake()->image('logo.png', 256, 256),
        ]);

        $response->assertRedirect(route('admin.settings.edit'));

        ShopSettings::forgetCache();

        $this->assertSame('Kedai Joan', ShopSettings::get('shop_name'));
        $this->assertSame('Kopi & Cemilan', ShopSettings::get('shop_title'));
        $this->assertNotEmpty(ShopSettings::get('logo_path'));
        Storage::disk('public')->assertExists(ShopSettings::get('logo_path'));

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Kedai Joan')
            ->assertSee(ShopSettings::faviconUrl(), false);
    }
}
