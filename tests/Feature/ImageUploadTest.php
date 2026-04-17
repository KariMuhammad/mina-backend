<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_upload_avatar()
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('avatar.jpg', 100, 'image/jpeg');

        $response = $this->actingAs($user)->postJson('/api/profile/update', [
            'name' => 'Updated Name',
            'avatar' => $file,
        ]);

        $response->assertStatus(200);

        // Verify the file was stored securely
        $user->refresh();
        $this->assertNotNull($user->avatar_path);
        Storage::disk('public')->assertExists($user->avatar_path);
        $this->assertStringContainsString('avatars/', $user->avatar_path);
    }

    public function test_uploading_invalid_mime_type_fails()
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $response = $this->actingAs($user)->postJson('/api/profile/update', [
            'avatar' => $file,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['avatar']);
    }

    public function test_uploading_oversized_image_fails()
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('giant.jpg', 3000, 'image/jpeg');

        $response = $this->actingAs($user)->postJson('/api/profile/update', [
            'avatar' => $file,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['avatar']);
    }

    public function test_old_avatar_is_deleted_on_replace()
    {
        Storage::fake('public');
        $user = User::factory()->create();
        
        // Initial upload
        $firstFile = UploadedFile::fake()->create('first.jpg', 100, 'image/jpeg');
        $this->actingAs($user)->postJson('/api/profile/update', ['avatar' => $firstFile]);
        $user->refresh();
        $oldPath = $user->avatar_path;
        Storage::disk('public')->assertExists($oldPath);

        // Replace upload
        $secondFile = UploadedFile::fake()->create('second.png', 100, 'image/png');
        $this->actingAs($user)->postJson('/api/profile/update', ['avatar' => $secondFile]);
        $user->refresh();
        $newPath = $user->avatar_path;

        // Assertion
        $this->assertNotEquals($oldPath, $newPath);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($newPath);
    }
}
