<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Color;
use App\Models\CompanyInfo;
use App\Models\Currency;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Size;
use App\Models\SizeGroup;
use App\Models\User;
use App\Models\UserType;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DevelopmentSeeder;
use Database\Seeders\ProductionSeeder;
use Database\Seeders\UserTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class DatabaseSeedingTest extends TestCase
{
    use RefreshDatabase;

    public function test_migrations_do_not_seed_operational_data(): void
    {
        $this->assertSame(0, UserType::count());
        $this->assertSame(0, Currency::count());
        $this->assertSame(0, CompanyInfo::count());
        $this->assertSame(0, User::count());
        $this->assertSame(0, SizeGroup::count());
        $this->assertSame(0, Size::count());
    }

    public function test_production_seeder_creates_the_complete_operational_baseline(): void
    {
        $this->seed(ProductionSeeder::class);

        $this->assertSame(3, UserType::count());
        $this->assertEqualsCanonicalizing(UserRole::values(), UserType::pluck('code')->all());
        $this->assertSame(2, Currency::count());
        $this->assertEqualsCanonicalizing(['MXN', 'USD'], Currency::pluck('code')->all());
        $this->assertSame(2, SizeGroup::count());
        $this->assertSame(23, Size::count());

        $company = CompanyInfo::with('currency')->sole();
        $this->assertSame('Guayaberas Lopez Silva', $company->name);
        $this->assertSame('MXN', $company->currency->code);

        $administrator = User::with('userType')->sole();
        $this->assertSame('admin', $administrator->user_name);
        $this->assertSame('admin@tienda.local', $administrator->email);
        $this->assertSame(UserRole::Administrator->value, $administrator->userType->code);
        $this->assertTrue(Hash::check('admin', $administrator->password));

        $this->assertSame(0, Category::count());
        $this->assertSame(0, Color::count());
        $this->assertSame(0, Product::count());
    }

    public function test_production_seeder_is_idempotent_and_preserves_configuration(): void
    {
        $this->seed(ProductionSeeder::class);

        Currency::where('code', 'USD')->firstOrFail()->update(['exchange_rate' => 20.50]);
        CompanyInfo::firstOrFail()->update([
            'name' => 'Empresa personalizada',
            'id_currency' => null,
        ]);
        UserType::where('code', UserRole::Warehouse->value)->firstOrFail()
            ->update(['name' => 'Bodega', 'status' => 'inactive']);
        $administrator = User::firstOrFail();
        $administrator->update(['password' => 'nueva-clave-segura']);

        $this->seed(ProductionSeeder::class);

        $this->assertSame(3, UserType::count());
        $this->assertSame(2, Currency::count());
        $this->assertSame(1, CompanyInfo::count());
        $this->assertSame(1, User::count());
        $this->assertSame(2, SizeGroup::count());
        $this->assertSame(23, Size::count());
        $this->assertSame(20.50, Currency::where('code', 'USD')->value('exchange_rate'));
        $this->assertSame('Empresa personalizada', CompanyInfo::firstOrFail()->name);
        $this->assertSame(
            'MXN',
            CompanyInfo::with('currency')->firstOrFail()->currency->code,
        );
        $this->assertSame('Bodega', UserType::where('code', UserRole::Warehouse->value)->value('name'));
        $this->assertSame('inactive', UserType::where('code', UserRole::Warehouse->value)->value('status'));
        $this->assertTrue(Hash::check('nueva-clave-segura', $administrator->fresh()->password));
    }

    public function test_production_seeder_does_not_add_default_admin_when_a_user_exists(): void
    {
        $this->seed(UserTypeSeeder::class);
        $seller = UserType::where('code', UserRole::Seller->value)->firstOrFail();
        User::create([
            'id_user_type' => $seller->id,
            'first_name' => 'Existing',
            'last_name' => 'User',
            'user_name' => 'existing',
            'email' => 'existing@example.com',
            'password' => 'password123',
            'status' => 'active',
        ]);

        $this->seed(ProductionSeeder::class);

        $this->assertSame(1, User::count());
        $this->assertFalse(User::where('user_name', 'admin')->exists());
    }

    public function test_development_seeder_is_idempotent_and_adds_demo_data(): void
    {
        $this->seed(DevelopmentSeeder::class);
        $this->seed(DevelopmentSeeder::class);

        $this->assertSame(2, Category::count());
        $this->assertSame(3, Color::count());
        $this->assertSame(2, Product::count());
        $this->assertSame(6, ProductVariant::count());
        $this->assertSame(5, InventoryMovement::count());
    }

    public function test_initial_admin_can_log_in_and_change_the_temporary_password(): void
    {
        $this->seed(ProductionSeeder::class);
        $administrator = User::sole();

        $login = $this->postJson('/api/login', [
            'userName' => 'admin',
            'password' => 'admin',
        ])->assertOk();

        $this->withToken($login->json('data.token'))
            ->putJson("/api/users/{$administrator->id}", ['password' => 'secure-password-123'])
            ->assertOk();

        $this->postJson('/api/login', [
            'userName' => 'admin',
            'password' => 'admin',
        ])->assertUnauthorized();
        $this->postJson('/api/login', [
            'userName' => 'admin',
            'password' => 'secure-password-123',
        ])->assertOk();
    }

    public function test_database_seeder_selects_production_data_in_production(): void
    {
        $originalEnvironment = $this->app->environment();
        $this->app->detectEnvironment(fn () => 'production');

        try {
            $this->artisan('db:seed', [
                '--class' => DatabaseSeeder::class,
                '--force' => true,
            ])->assertSuccessful();
        } finally {
            $this->app->detectEnvironment(fn () => $originalEnvironment);
        }

        $this->assertSame(1, User::count());
        $this->assertSame(0, Product::count());
    }

    public function test_development_seeder_refuses_to_run_in_production(): void
    {
        $originalEnvironment = $this->app->environment();
        $this->app->detectEnvironment(fn () => 'production');

        try {
            try {
                $this->artisan('db:seed', [
                    '--class' => DevelopmentSeeder::class,
                    '--force' => true,
                ]);
                $this->fail('DevelopmentSeeder debió rechazar el entorno production.');
            } catch (RuntimeException $exception) {
                $this->assertSame('DevelopmentSeeder no puede ejecutarse en producción.', $exception->getMessage());
            }
        } finally {
            $this->app->detectEnvironment(fn () => $originalEnvironment);
        }
    }
}
