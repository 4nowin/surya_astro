<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_user_can_register_successfully(): void
    {
        $payload = [
            'name' => 'Aditi Sharma',
            'email' => 'aditi@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ];

        $response = $this->postJson('/api/en/register', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Registration successful',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'token',
                'user' => [
                    'id',
                    'name',
                    'email',
                    'referral_code',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'aditi@example.com',
            'name' => 'Aditi Sharma',
        ]);
    }

    public function test_api_user_registration_fails_on_duplicate_email(): void
    {
        User::factory()->create([
            'email' => 'duplicate@example.com',
        ]);

        $payload = [
            'name' => 'Another User',
            'email' => 'duplicate@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ];

        $response = $this->postJson('/api/en/register', $payload);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_api_user_registration_fails_on_password_mismatch(): void
    {
        $payload = [
            'name' => 'Test User',
            'email' => 'mismatch@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'different123',
        ];

        $response = $this->postJson('/api/en/register', $payload);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_api_user_registration_fails_on_missing_fields(): void
    {
        $response = $this->postJson('/api/en/register', []);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_registered_user_can_login_via_api(): void
    {
        $user = User::factory()->create([
            'email' => 'loginuser@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/en/login', [
            'email' => 'loginuser@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Login successful',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'token',
                'user',
            ]);
    }

    public function test_api_login_fails_with_invalid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'loginuser2@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/en/login', [
            'email' => 'loginuser2@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid credentials',
            ]);
    }
}
