<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserUpdateTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_updates_a_user_by_cedula()
    {
        // Create a test user
        $user = User::factory()->create([
            'cedula' => '12345678',
            'nombre' => 'Original Name',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
            'is_root' => false
        ]);

        // Authenticate as the user
        $token = $user->createToken('test-token')->plainTextToken;

        // Update the user's name
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->putJson("/api/users/cedula/{$user->cedula}", [
            'nombre' => 'Updated Name'
        ]);

        // Assert the response
        $response->assertStatus(200);
        $response->assertJson([
            'status' => true,
            'mensaje' => 'Usuario actualizado por cédula.'
        ]);
        $response->assertJsonPath('data.nombre', 'Updated Name');

        // Verify the user was updated in the database
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'nombre' => 'Updated Name'
        ]);
    }

    /** @test */
    public function it_prevents_non_root_users_from_updating_root_users()
    {
        // Create a root user
        $rootUser = User::factory()->create([
            'cedula' => '87654321',
            'is_root' => true
        ]);

        // Create a regular user
        $regularUser = User::factory()->create([
            'cedula' => '12345678',
            'is_root' => false
        ]);

        // Authenticate as the regular user
        $token = $regularUser->createToken('test-token')->plainTextToken;

        // Try to update the root user
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->putJson("/api/users/cedula/{$rootUser->cedula}", [
            'nombre' => 'Should Not Update'
        ]);

        // Assert the response indicates user not found (for security)
        $response->assertStatus(200);
        $response->assertJson([
            'status' => true,
            'mensaje' => 'usuario no encontrado',
            'data' => null
        ]);

        // Verify the root user was not updated
        $this->assertDatabaseHas('users', [
            'id' => $rootUser->id,
            'nombre' => $rootUser->nombre // Name should not have changed
        ]);
    }
}
