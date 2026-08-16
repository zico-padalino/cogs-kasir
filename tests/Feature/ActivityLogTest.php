<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_login_is_recorded_with_ip(): void
    {
        $user = User::factory()->admin()->create([
            'email' => 'admin-log@test.local',
            'password' => 'secret123',
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->post(route('login.store'), [
                'email' => $user->email,
                'password' => 'secret123',
            ])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertDatabaseHas('activity_logs', [
            'category' => 'auth',
            'action' => 'login',
            'user_id' => $user->id,
            'actor_email' => $user->email,
            'ip_address' => '203.0.113.10',
        ]);
    }

    public function test_failed_login_is_recorded(): void
    {
        $this->post(route('login.store'), [
            'email' => 'nobody@test.local',
            'password' => 'wrong-password',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'category' => 'auth',
            'action' => 'login_failed',
            'actor_email' => 'nobody@test.local',
        ]);
    }

    public function test_admin_can_open_activity_log_page(): void
    {
        $admin = User::factory()->admin()->create();
        ActivityLog::query()->create([
            'category' => 'transaksi',
            'action' => 'order_paid',
            'description' => 'Transaksi TRX-1 lunas.',
            'actor_name' => 'Kasir Satu',
            'ip_address' => '198.51.100.2',
            'channel' => 'kasir',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.activity-logs.index'))
            ->assertOk()
            ->assertSee('Log Aktivitas')
            ->assertSee('Kasir Satu')
            ->assertSee('198.51.100.2')
            ->assertSee('Transaksi TRX-1 lunas.');
    }

    public function test_kasir_cannot_open_activity_log_page(): void
    {
        $kasir = User::factory()->kasir()->create();

        $this->actingAs($kasir)
            ->get(route('admin.activity-logs.index'))
            ->assertRedirect();
    }
}
