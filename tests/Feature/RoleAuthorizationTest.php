<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\UserSession;
use App\Models\UserType;
use Database\Seeders\UserTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(UserTypeSeeder::class);
    }

    public function test_role_seeder_is_idempotent_and_creates_the_expected_roles(): void
    {
        $warehouse = UserType::where('code', UserRole::Warehouse->value)->firstOrFail();
        $warehouse->update(['name' => 'Bodega', 'status' => 'inactive']);
        $this->seed(UserTypeSeeder::class);

        $this->assertSame(3, UserType::count());
        $this->assertEqualsCanonicalizing(UserRole::values(), UserType::pluck('code')->all());
        $this->assertSame('inactive', $warehouse->fresh()->status);
        $this->assertSame('Bodega', $warehouse->fresh()->name);
    }

    public function test_login_exposes_a_stable_role_code_for_each_active_role(): void
    {
        foreach (UserRole::cases() as $index => $role) {
            $user = $this->createUser($role, 'login-'.$index);

            $this->login($user)
                ->assertOk()
                ->assertJsonPath('data.user.userType', $user->id_user_type)
                ->assertJsonPath('data.user.roleCode', $role->value)
                ->assertJsonPath('data.user.roleName', $user->userType->name)
                ->assertJsonFragment(['code' => $role->value]);
        }
    }

    public function test_unlisted_active_role_is_denied_shared_and_admin_endpoints(): void
    {
        $auditor = UserType::create([
            'name' => 'Auditor',
            'code' => 'auditor',
            'status' => 'active',
        ]);
        $user = User::create([
            'id_user_type' => $auditor->id,
            'first_name' => 'Test',
            'last_name' => 'User',
            'user_name' => 'auditor',
            'email' => 'auditor@example.com',
            'password' => 'password123',
            'status' => 'active',
        ])->load('userType');
        $token = $this->login($user)->json('data.token');

        $this->withToken($token)->getJson('/api/catalogs')->assertForbidden();
        $this->withToken($token)->getJson('/api/users')->assertForbidden();
        $this->withToken($token)->deleteJson('/api/logout')->assertOk();
    }

    public function test_seller_and_warehouse_can_use_shared_endpoints_but_not_admin_modules(): void
    {
        foreach ([UserRole::Seller, UserRole::Warehouse] as $index => $role) {
            $user = $this->createUser($role, 'shared-'.$index);
            $token = $this->login($user)->json('data.token');

            $this->withToken($token)->getJson('/api/catalogs')->assertOk();
            $this->withToken($token)->getJson('/api/products')->assertOk();

            $this->withToken($token)->getJson('/api/dashboard')->assertForbidden();
            $this->withToken($token)->getJson('/api/users')->assertForbidden();
            $this->withToken($token)->getJson('/api/company-info')->assertForbidden();
            $this->withToken($token)->getJson('/api/currencies')->assertForbidden();
        }
    }

    public function test_administrator_access_depends_on_code_not_visible_name(): void
    {
        $user = $this->createUser(UserRole::Administrator, 'renamed-admin');
        $user->userType->update(['name' => 'Superusuario']);
        $token = $this->login($user->fresh('userType'))->json('data.token');

        $this->withToken($token)->getJson('/api/catalogs')->assertOk();
        $this->withToken($token)->getJson('/api/dashboard')->assertOk();
        $this->withToken($token)->getJson('/api/users')->assertOk();
        $this->withToken($token)->getJson('/api/currencies')->assertOk();
        $this->withToken($token)->getJson('/api/company-info')->assertNotFound();
    }

    public function test_inactive_users_and_roles_cannot_log_in(): void
    {
        $inactiveUser = $this->createUser(UserRole::Seller, 'inactive-user', 'inactive');
        $this->login($inactiveUser)->assertUnauthorized();

        $inactiveRoleUser = $this->createUser(UserRole::Warehouse, 'inactive-role');
        $inactiveRoleUser->userType->update(['status' => 'inactive']);
        $this->login($inactiveRoleUser)->assertUnauthorized();
    }

    public function test_existing_session_is_revoked_when_user_or_role_becomes_inactive(): void
    {
        $inactiveUser = $this->createUser(UserRole::Seller, 'token-user');
        $userToken = $this->login($inactiveUser)->json('data.token');
        $inactiveUser->update(['status' => 'inactive']);

        $this->withToken($userToken)->getJson('/api/catalogs')->assertUnauthorized();
        $this->assertNotNull(UserSession::where('token_hash', $userToken)->value('revoked_at'));

        $inactiveRoleUser = $this->createUser(UserRole::Warehouse, 'token-role');
        $roleToken = $this->login($inactiveRoleUser)->json('data.token');
        $inactiveRoleUser->userType->update(['status' => 'inactive']);

        $this->withToken($roleToken)->getJson('/api/catalogs')->assertUnauthorized();
        $this->assertNotNull(UserSession::where('token_hash', $roleToken)->value('revoked_at'));
    }

    public function test_role_change_and_user_deactivation_revoke_active_sessions(): void
    {
        $administrator = $this->createUser(UserRole::Administrator, 'session-admin');
        $adminToken = $this->login($administrator)->json('data.token');
        $seller = $this->createUser(UserRole::Seller, 'changed-role');
        $sellerToken = $this->login($seller)->json('data.token');
        $warehouse = UserType::where('code', UserRole::Warehouse->value)->firstOrFail();

        $this->withToken($adminToken)
            ->putJson("/api/users/{$seller->id}", ['idUserType' => $warehouse->id])
            ->assertOk()
            ->assertJsonPath('data.roleCode', UserRole::Warehouse->value);

        $this->assertNotNull(UserSession::where('token_hash', $sellerToken)->value('revoked_at'));

        $seller = $seller->fresh();
        $newToken = $this->login($seller)->json('data.token');
        $this->withToken($adminToken)->deleteJson("/api/users/{$seller->id}")->assertOk();
        $this->assertNotNull(UserSession::where('token_hash', $newToken)->value('revoked_at'));
    }

    public function test_user_requests_reject_inactive_roles(): void
    {
        $administrator = $this->createUser(UserRole::Administrator, 'validation-admin');
        $adminToken = $this->login($administrator)->json('data.token');
        $inactiveRole = UserType::where('code', UserRole::Warehouse->value)->firstOrFail();
        $inactiveRole->update(['status' => 'inactive']);

        $this->withToken($adminToken)->postJson('/api/users', [
            'idUserType' => $inactiveRole->id,
            'firstName' => 'Usuario',
            'lastName' => 'Inválido',
            'userName' => 'invalid.role',
            'email' => 'invalid.role@example.com',
            'password' => 'password123',
        ])->assertUnprocessable()->assertJsonValidationErrors('idUserType');

        $target = $this->createUser(UserRole::Seller, 'invalid-update');
        $this->withToken($adminToken)
            ->putJson("/api/users/{$target->id}", ['idUserType' => $inactiveRole->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('idUserType');
    }

    public function test_any_active_user_can_log_out(): void
    {
        $seller = $this->createUser(UserRole::Seller, 'logout');
        $token = $this->login($seller)->json('data.token');

        $this->withToken($token)->deleteJson('/api/logout')->assertOk();
        $this->assertNotNull(UserSession::where('token_hash', $token)->value('revoked_at'));
    }

    public function test_changing_password_revokes_existing_sessions(): void
    {
        $administrator = $this->createUser(UserRole::Administrator, 'password-admin');
        $token = $this->login($administrator)->json('data.token');

        $this->withToken($token)
            ->putJson("/api/users/{$administrator->id}", ['password' => 'new-password-123'])
            ->assertOk();

        $this->assertNotNull(UserSession::where('token_hash', $token)->value('revoked_at'));
        $this->login($administrator)->assertUnauthorized();
        $this->postJson('/api/login', [
            'userName' => $administrator->user_name,
            'password' => 'new-password-123',
        ])->assertOk();
    }

    private function createUser(UserRole $role, string $suffix, string $status = 'active'): User
    {
        $userType = UserType::where('code', $role->value)->firstOrFail();

        return User::create([
            'id_user_type' => $userType->id,
            'first_name' => 'Test',
            'last_name' => 'User',
            'user_name' => $suffix,
            'email' => "$suffix@example.com",
            'password' => 'password123',
            'status' => $status,
        ])->load('userType');
    }

    private function login(User $user)
    {
        return $this->postJson('/api/login', [
            'userName' => $user->user_name,
            'password' => 'password123',
        ]);
    }
}
