<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AppSettingTest extends TestCase
{
    use RefreshDatabase;

    private function seedSettings(): void
    {
        AppSetting::query()->insert([
            ['key' => 'support_phone', 'value' => '+1111111111', 'label' => 'Support Phone Number', 'type' => 'phone', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'whatsapp', 'value' => '+2222222222', 'label' => 'WhatsApp Number', 'type' => 'phone', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'contact_email', 'value' => 'test@example.com', 'label' => 'Contact Email', 'type' => 'email', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'terms_conditions', 'value' => '<p>Terms</p>', 'label' => 'Terms & Conditions', 'type' => 'richtext', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'privacy_policy', 'value' => '<p>Privacy</p>', 'label' => 'Privacy Policy', 'type' => 'richtext', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'about_us', 'value' => '<p>About</p>', 'label' => 'About Us', 'type' => 'richtext', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    // -----------------------------------------------------------------------
    // Public endpoints
    // -----------------------------------------------------------------------

    public function test_public_index_returns_key_value_object(): void
    {
        $this->seedSettings();

        $response = $this->getJson('/api/app-settings');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.support_phone', '+1111111111')
            ->assertJsonPath('data.contact_email', 'test@example.com')
            ->assertJsonPath('data.about_us', '<p>About</p>');
    }

    public function test_public_show_returns_single_setting(): void
    {
        $this->seedSettings();

        $this->getJson('/api/app-settings/terms_conditions')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.key', 'terms_conditions')
            ->assertJsonPath('data.value', '<p>Terms</p>')
            ->assertJsonPath('data.label', 'Terms & Conditions');
    }

    public function test_public_show_returns_404_for_missing_key(): void
    {
        $this->getJson('/api/app-settings/nonexistent')
            ->assertStatus(404);
    }

    // -----------------------------------------------------------------------
    // Admin endpoints
    // -----------------------------------------------------------------------

    public function test_admin_can_list_all_settings(): void
    {
        $this->seedSettings();
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/app-settings')
            ->assertOk()
            ->assertJsonCount(6);
    }

    public function test_admin_can_bulk_update_settings(): void
    {
        $this->seedSettings();
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/app-settings/bulk', [
            'settings' => [
                ['key' => 'support_phone', 'value' => '+9999999999'],
                ['key' => 'contact_email', 'value' => 'new@example.com'],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Settings updated successfully.');

        $this->assertDatabaseHas('app_settings', [
            'key' => 'support_phone',
            'value' => '+9999999999',
        ]);
        $this->assertDatabaseHas('app_settings', [
            'key' => 'contact_email',
            'value' => 'new@example.com',
        ]);
    }

    public function test_admin_can_update_single_setting(): void
    {
        $this->seedSettings();
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $this->putJson('/api/admin/app-settings/support_phone', [
            'value' => '+8888888888',
        ])
            ->assertOk()
            ->assertJsonPath('setting.value', '+8888888888');

        $this->assertDatabaseHas('app_settings', [
            'key' => 'support_phone',
            'value' => '+8888888888',
        ]);
    }

    public function test_admin_update_single_returns_404_for_missing_key(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $this->putJson('/api/admin/app-settings/nonexistent', [
            'value' => 'x',
        ])->assertStatus(404);
    }

    public function test_non_admin_cannot_access_admin_settings(): void
    {
        $this->seedSettings();
        $customer = User::factory()->create();
        Sanctum::actingAs($customer);

        $this->getJson('/api/admin/app-settings')->assertForbidden();
        $this->postJson('/api/admin/app-settings/bulk', [
            'settings' => [['key' => 'support_phone', 'value' => 'x']],
        ])->assertForbidden();
    }

    public function test_unauthenticated_cannot_access_admin_settings(): void
    {
        $this->getJson('/api/admin/app-settings')->assertUnauthorized();
    }

    // -----------------------------------------------------------------------
    // Model helpers
    // -----------------------------------------------------------------------

    public function test_app_setting_get_and_set_helpers(): void
    {
        $this->seedSettings();

        $this->assertSame('+1111111111', AppSetting::get('support_phone'));
        $this->assertNull(AppSetting::get('nonexistent'));

        AppSetting::set('support_phone', '+7777777777');
        $this->assertSame('+7777777777', AppSetting::get('support_phone'));
    }
}
