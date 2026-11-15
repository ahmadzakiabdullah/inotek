<?php

namespace Tests\Feature\Auth;

use App\Actions\Fortify\CreateNewUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::registration());
    }

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get(route('register'));

        $response->assertOk();
    }

    public function test_new_users_can_register()
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_registrations_are_rate_limited_per_ip_address(): void
    {
        $max = CreateNewUser::MAX_REGISTRATIONS_PER_HOUR;

        for ($i = 1; $i <= $max + 1; $i++) {
            $response = $this->post(route('register.store'), [
                'name' => "User {$i}",
                'username' => "user{$i}",
                'email' => "user{$i}@example.com",
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);
            auth()->logout();
        }

        $response->assertSessionHasErrors('email');
        $this->assertDatabaseCount('users', $max);
    }
}
