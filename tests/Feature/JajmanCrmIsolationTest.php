<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Jajman;
use App\Models\JajmanNote;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class JajmanCrmIsolationTest extends TestCase
{
    use DatabaseMigrations;
    public function test_practitioners_cannot_access_each_others_jajmans_or_notes()
    {
        // Ensure Astrologer role exists for admin guard
        $role = Role::firstOrCreate(['name' => 'Astrologer', 'guard_name' => 'admin']);

        // Create Practitioner A
        $adminA = Admin::create([
            'name' => 'Pandit Sharma',
            'email' => 'sharma_' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
        ]);
        $adminA->assignRole('Astrologer');

        // Create Practitioner B
        $adminB = Admin::create([
            'name' => 'Acharya Verma',
            'email' => 'verma_' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
        ]);
        $adminB->assignRole('Astrologer');

        // Practitioner A creates a Jajman
        $responseA = $this->actingAs($adminA, 'sanctum')->postJson('/api/astrologer/jajmans', [
            'name' => 'Client of Sharma',
            'phone' => '9876543210',
            'city' => 'Varanasi',
        ]);
        $responseA->assertStatus(201);
        $jajmanId = $responseA->json('data.id');

        // Practitioner A adds a private note
        $noteResponse = $this->actingAs($adminA, 'sanctum')->postJson("/api/astrologer/jajmans/{$jajmanId}/notes", [
            'content' => 'Sharma confidential observation: Saturn Mahadasha running.',
        ]);
        $noteResponse->assertStatus(201);

        // Practitioner B attempts to view Practitioner A's Jajman -> MUST 404
        $responseB = $this->actingAs($adminB, 'sanctum')->getJson("/api/astrologer/jajmans/{$jajmanId}");
        $responseB->assertStatus(404);

        // Practitioner B attempts to list Jajmans -> Should not see Practitioner A's Jajman
        $listResponseB = $this->actingAs($adminB, 'sanctum')->getJson('/api/astrologer/jajmans');
        $listResponseB->assertStatus(200);
        $ids = collect($listResponseB->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($jajmanId));

        // Practitioner B attempts to read Practitioner A's notes -> MUST 404
        $notesResponseB = $this->actingAs($adminB, 'sanctum')->getJson("/api/astrologer/jajmans/{$jajmanId}/notes");
        $notesResponseB->assertStatus(404);
    }
}
