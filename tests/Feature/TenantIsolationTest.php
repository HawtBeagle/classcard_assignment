<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class TenantIsolationTest extends TestCase
{
    /** @test */
    public function user_can_only_see_their_own_tenant_data()
    {
        // Pick an existing user from tenant 1
        $tenant1User = User::where('tenant_id', 1)->first();
        Auth::login($tenant1User);

        // Fetch all users
        $users = User::all();

        // Assert all users belong to tenant 1
        foreach ($users as $user) {
            $this->assertEquals(1, $user->tenant_id);
        }

        // Attempt to fetch a user from another tenant directly
        $tenant2User = User::where('tenant_id', '!=', 1)->first();
        $this->assertNull(User::find($tenant2User?->id)); // global scope prevents access
    }

    /** @test */
    public function creating_user_sets_tenant_id_automatically()
    {
        $tenant1User = User::where('tenant_id', 1)->first();
        Auth::login($tenant1User);

        $newUser = User::create([
            'name' => 'New Tenant User',
            'email' => 'newuser@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->assertEquals(1, $newUser->tenant_id);
    }
}
