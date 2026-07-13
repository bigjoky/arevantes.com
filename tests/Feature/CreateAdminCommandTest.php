<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_verified_administrator_with_a_hidden_password_prompt(): void
    {
        $this->artisan('arevantes:create-admin', [
            'email' => 'ADMIN@AREVANTES.COM',
            '--name' => 'Joaquín Arévalo',
        ])
            ->expectsQuestion('Password (minimum 12 characters)', 'a-secure-password')
            ->expectsQuestion('Confirm password', 'a-secure-password')
            ->assertSuccessful();

        $admin = User::query()->where('email', 'admin@arevantes.com')->firstOrFail();

        $this->assertSame('Joaquín Arévalo', $admin->name);
        $this->assertSame('admin', $admin->role);
        $this->assertNotNull($admin->email_verified_at);
        $this->assertTrue(password_verify('a-secure-password', $admin->password));
    }

    public function test_it_rejects_an_existing_email(): void
    {
        User::factory()->create(['email' => 'admin@arevantes.com']);

        $this->artisan('arevantes:create-admin', [
            'email' => 'admin@arevantes.com',
        ])->assertFailed();

        $this->assertDatabaseCount('users', 1);
    }
}
