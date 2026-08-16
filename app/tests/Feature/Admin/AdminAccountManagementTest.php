<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccountManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_create_another_admin_account(): void
    {
        $admin = Admin::factory()->create();

        $response = $this->actingAs($admin, 'admin')->post(route('admin.admins.store'), [
            'name' => 'Deuxième Admin',
            'email' => 'deuxieme@gestion-stock.test',
            'password' => 'mot-de-passe-sur',
            'password_confirmation' => 'mot-de-passe-sur',
        ]);

        $response->assertRedirect(route('admin.admins.index'));
        $this->assertDatabaseHas('admins', ['email' => 'deuxieme@gestion-stock.test']);
    }

    public function test_an_admin_can_delete_another_admin_account(): void
    {
        $admin = Admin::factory()->create();
        $autre = Admin::factory()->create();

        $this->actingAs($admin, 'admin')
            ->delete(route('admin.admins.destroy', $autre))
            ->assertRedirect(route('admin.admins.index'));

        $this->assertDatabaseMissing('admins', ['id' => $autre->id]);
    }

    public function test_an_admin_cannot_delete_their_own_account(): void
    {
        $admin = Admin::factory()->create();

        $response = $this->actingAs($admin, 'admin')->delete(route('admin.admins.destroy', $admin));

        $response->assertStatus(422);
        $this->assertDatabaseHas('admins', ['id' => $admin->id]);
    }

    public function test_a_guest_cannot_manage_admin_accounts(): void
    {
        $admin = Admin::factory()->create();

        $response = $this->get(route('admin.admins.index'));

        $response->assertRedirect(route('admin.login'));
        $this->assertDatabaseCount('admins', 1);
    }
}
