<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\ShopSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->put(route('admin.settings.update'), [
            'shop_name' => 'Kedai Joan',
            'shop_title' => 'Kopi & Cemilan',
            'logo' => UploadedFile::fake()->image('logo.png', 256, 256),
            'attendance_clock_in' => '08:00',
            'attendance_clock_out' => '17:00',
            'attendance_early_minutes' => 60,
            'attendance_radius_meters' => 100,
        ]);

        $response->assertRedirect(route('admin.settings.edit'));

        ShopSettings::forgetCache();

        $this->assertSame('Kedai Joan', ShopSettings::get('shop_name'));
        $this->assertSame('Kopi & Cemilan', ShopSettings::get('shop_title'));
        $logoPath = ShopSettings::get('logo_path');
        $this->assertNotEmpty($logoPath);
        $this->assertFileExists(public_path($logoPath));
        $this->assertNotEmpty(ShopSettings::faviconUrl());

        ShopSettings::deleteLogoFile($logoPath);
        ShopSettings::put(['logo_path' => null]);
    }

    public function test_admin_can_replace_qris_payment_image(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->put(route('admin.settings.update'), [
            'shop_name' => 'Kedai Joan',
            'shop_title' => 'Kopi & Cemilan',
            'qris' => UploadedFile::fake()->image('qris-baru.png', 512, 512),
            'attendance_clock_in' => '08:00',
            'attendance_clock_out' => '17:00',
            'attendance_early_minutes' => 60,
            'attendance_radius_meters' => 100,
        ]);

        $response->assertRedirect(route('admin.settings.edit'));

        ShopSettings::forgetCache();

        $qrisPath = ShopSettings::get('qris_path');
        $this->assertNotEmpty($qrisPath);
        $this->assertTrue(ShopSettings::hasCustomQris());
        $this->assertFileExists(public_path($qrisPath));
        $this->assertStringContainsString('/uploads/qris/', ShopSettings::qrisUrl());

        $this->actingAs($admin)
            ->get(route('admin.settings.edit'))
            ->assertOk()
            ->assertSee('QR pembayaran (QRIS)');

        // Bersihkan file uji agar tidak menumpuk di public/uploads.
        ShopSettings::deleteQrisFile($qrisPath);
        ShopSettings::put(['qris_path' => null]);
    }
}
