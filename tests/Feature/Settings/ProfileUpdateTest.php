<?php

namespace Tests\Feature\Settings;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('profile.edit'));

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'username' => 'testuser',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_lecturers_and_judges_cannot_change_their_own_email(): void
    {
        $this->seed(RoleSeeder::class);

        foreach (['lecturer', 'judge'] as $roleName) {
            $user = User::factory()->create(['role_id' => Role::where('name', $roleName)->value('id')]);
            $originalEmail = $user->email;

            $this->actingAs($user)
                ->patch(route('profile.update'), [
                    'name' => 'Renamed',
                    'username' => $user->username,
                    'email' => 'someone-else@example.com',
                ])
                ->assertSessionHasErrors('email');

            $this->assertSame($originalEmail, $user->refresh()->email);

            // Other profile fields can still be changed.
            $this->actingAs($user)
                ->patch(route('profile.update'), [
                    'name' => 'Renamed',
                    'username' => $user->username,
                    'email' => $originalEmail,
                ])
                ->assertSessionHasNoErrors();

            $this->assertSame('Renamed', $user->refresh()->name);
        }
    }

    public function test_the_only_admin_cannot_delete_their_own_account(): void
    {
        $this->seed(RoleSeeder::class);
        $adminRoleId = Role::where('name', 'admin')->value('id');
        $admin = User::factory()->create(['role_id' => $adminRoleId]);

        $this->actingAs($admin)
            ->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertSessionHasErrors('password');

        $this->assertNotSoftDeleted($admin);

        User::factory()->create(['role_id' => $adminRoleId]);

        $this->actingAs($admin)
            ->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertSessionHasNoErrors();

        $this->assertSoftDeleted($admin);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'username' => $user->username,
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete(route('profile.destroy'), [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('home'));

        $this->assertGuest();
        $this->assertTrue($user->fresh()->trashed());
    }

    public function test_correct_password_must_be_provided_to_delete_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'), [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->fresh());
    }
}
