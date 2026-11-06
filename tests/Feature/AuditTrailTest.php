<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Color;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\UserSession;
use App\Models\UserType;
use App\Services\AuditTrail;
use App\Services\OrderPdfService;
use App\Support\Audit;
use App\Support\AuditActions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function login(): string
    {
        return $this->postJson('/api/login', ['userName' => 'admin', 'password' => 'admin'])->assertOk()->json('data.token');
    }

    private function event(string $action): object
    {
        return DB::table('audit_events')->where('action', $action)->latest('id')->firstOrFail();
    }

    private function failChangeInsert(): void
    {
        DB::listen(function ($query) {
            if (preg_match('/insert into ["`]audit_changes["`]/i', $query->sql)) {
                throw new RuntimeException('Simulated audit storage failure');
            }
        });
    }

    public function test_every_api_route_has_an_explicit_audit_classification(): void
    {
        foreach (app('router')->getRoutes() as $route) {
            if (str_starts_with($route->uri(), 'api/')) {
                $this->assertNotNull(AuditActions::definition($route->getActionName()), $route->uri());
            }
        }
    }

    public function test_every_business_field_has_an_explicit_privacy_policy(): void
    {
        foreach (config('audit.models') as $model => [, $fields]) {
            $this->assertEqualsCanonicalizing(
                array_values(array_diff((new $model)->getFillable(), ['password', 'token_hash'])),
                explode(',', $fields), $model,
            );
        }
    }

    public function test_nested_rollback_does_not_leave_phantom_changes(): void
    {
        $audit = app(AuditTrail::class);
        $original = Color::findOrFail(1)->name;
        $audit->run('catalogs', 'maintenance.nested', function () {
            Category::findOrFail(1)->update(['description' => 'Outer change']);
            try {
                Audit::transaction(function () {
                    Color::findOrFail(1)->update(['name' => 'Must rollback']);
                    throw new RuntimeException('Rollback nested operation');
                });
            } catch (RuntimeException) {
                // La operación exterior puede continuar después del savepoint.
            }
        });
        $this->assertSame($original, Color::findOrFail(1)->name);
        $this->assertSame(['category'], DB::table('audit_changes')->where('audit_event_id', $this->event('maintenance.nested')->id)->pluck('entity_type')->all());

    }

    public function test_rejections_remain_rejected_when_audit_is_unavailable(): void
    {
        DB::listen(function ($query) {
            if (preg_match('/insert into ["`]audit_events["`]/i', $query->sql)) {
                throw new RuntimeException('Audit unavailable');
            }
        });
        $this->getJson('/api/users')->assertUnauthorized();
        $this->postJson('/api/login', ['userName' => ['invalid'], 'password' => 'not-a-password'])->assertUnprocessable();
    }

    public function test_login_success_reuse_and_failure_are_recorded_without_credentials(): void
    {
        $token = $this->login();
        $this->assertSame($token, $this->login());
        $this->assertSame(1, UserSession::count());
        $this->assertSame(2, DB::table('audit_events')->where('action', 'auth.login')->where('outcome', 'success')->count());
        $this->postJson('/api/login', ['userName' => 'admin', 'password' => 'wrong-password'])->assertUnauthorized();
        $event = $this->event('auth.login');
        $this->assertSame('rejected', $event->outcome);
        $this->assertNull($event->actor_id);
        $serialized = json_encode(DB::table('audit_changes')->get());
        $this->assertStringNotContainsString($token, $serialized);
        $this->assertStringNotContainsString('token_hash', $serialized);
        $this->assertStringNotContainsString(User::first()->password, $serialized);
    }

    public function test_changes_capture_the_original_actor_role_and_revoke_sessions(): void
    {
        $token = $this->login();
        $seller = UserType::where('code', 'seller')->firstOrFail();
        $this->withToken($token)->putJson('/api/users/1', ['idUserType' => $seller->id, 'password' => 'new-password-123'])->assertOk();
        $event = $this->event('users.updated');
        $this->assertSame('administrator', $event->role_code);
        $this->assertSame(1, $event->actor_id);
        $this->assertSame('success', $event->outcome);
        $changes = DB::table('audit_changes')->where('audit_event_id', $event->id)->get()->keyBy('entity_type');
        $after = json_decode($changes['user']->after_values, true);
        $this->assertTrue($after['passwordChanged']);
        $this->assertSame($seller->id, $after['idUserType']);
        $this->assertArrayNotHasKey('password', $after);
        $this->assertArrayHasKey('user_session', $changes->all());
        $this->assertNotNull(UserSession::first()->revoked_at);
    }

    public function test_audit_failure_reverts_business_changes_and_returns_503(): void
    {
        $token = $this->login();
        $before = User::findOrFail(1)->first_name;
        $this->failChangeInsert();
        $this->withToken($token)->putJson('/api/users/1', ['firstName' => 'Must rollback'])->assertStatus(503)->assertHeader('X-Request-Id');
        $this->assertSame($before, User::findOrFail(1)->first_name);
        $this->assertSame(0, DB::table('audit_events')->where('action', 'users.updated')->where('outcome', 'success')->count());
        $this->assertSame('failed', $this->event('users.updated')->outcome);
    }

    public function test_failed_audit_does_not_issue_a_new_login_session(): void
    {
        $this->failChangeInsert();
        $this->postJson('/api/login', ['userName' => 'admin', 'password' => 'admin'])->assertStatus(503);
        $this->assertSame(0, UserSession::count());
    }

    public function test_reads_previews_and_no_change_operations_are_distinguished(): void
    {
        $token = $this->login();
        $count = DB::table('audit_events')->count();
        $this->withToken($token)->postJson('/api/products/query', [])->assertOk();
        $this->withToken($token)->putJson('/api/products/1', ['key' => 'CAM-900', 'dryRun' => true])->assertOk();
        $this->assertSame($count, DB::table('audit_events')->count());
        $product = Product::findOrFail(1);
        $this->withToken($token)->putJson('/api/products/1', ['name' => $product->name])->assertOk();
        $this->assertSame('no_change', $this->event('products.updated')->outcome);
        $this->withToken($token)->putJson('/api/products/999999', ['name' => 'Missing'])->assertNotFound();
        $this->assertSame('rejected', $this->event('products.updated')->outcome);
    }

    public function test_product_cascades_and_pivot_changes_are_included(): void
    {
        $token = $this->login();
        $product = Product::findOrFail(1);
        $this->withToken($token)->putJson('/api/products/1', ['categories' => [1]])->assertOk();
        $event = $this->event('products.updated');
        $this->assertDatabaseHas('audit_changes', ['audit_event_id' => $event->id, 'entity_type' => 'category_product', 'operation' => 'deleted']);
        $this->withToken($token)->deleteJson('/api/products/1')->assertOk();
        $event = $this->event('products.deactivated');
        $this->assertSame($product->variants()->count(), DB::table('audit_changes')->where('audit_event_id', $event->id)->where('entity_type', 'product_variant')->count());
    }

    public function test_sql_key_renames_record_only_original_and_final_values(): void
    {
        $token = $this->login();
        $variant = ProductVariant::where('id_product', 1)->firstOrFail();
        $variant->update(['sku' => 'CAM001-S'.$variant->id_size.'-C'.$variant->id_color]);
        $this->withToken($token)->putJson('/api/categories/1', ['key' => 'CMS', 'cascadeKeys' => true])->assertOk();
        $event = $this->event('categories.updated');
        $changes = DB::table('audit_changes')->where('audit_event_id', $event->id)->get();
        $this->assertTrue($changes->contains('entity_type', 'product'));
        $this->assertTrue($changes->contains('entity_type', 'product_variant'));
        $this->assertStringNotContainsString('~rename~', json_encode($changes));
        $product = $changes->firstWhere('entity_type', 'product');
        $this->assertSame(['key' => 'CAM-001'], json_decode($product->before_values, true));
        $this->assertSame(['key' => 'CMS-001'], json_decode($product->after_values, true));
    }

    public function test_order_lines_receipts_and_stock_share_one_event(): void
    {
        $token = $this->login();
        $variants = ProductVariant::where('id_product', 1)->limit(2)->get();
        $order = $this->withToken($token)->postJson('/api/orders', ['details' => $variants->map(fn ($v) => ['idProductVariant' => $v->id, 'quantityOrdered' => 2])->all()])->assertCreated()->json('data');
        $this->withToken($token)->putJson('/api/orders/'.$order['id'], ['details' => [['idProductVariant' => $variants[0]->id, 'quantityOrdered' => 2]]])->assertOk();
        $this->assertDatabaseHas('audit_changes', ['audit_event_id' => $this->event('orders.updated')->id, 'entity_type' => 'order_detail', 'operation' => 'deleted']);
        $confirmed = $this->withToken($token)->postJson('/api/orders/'.$order['id'].'/confirm')->assertOk()->json('data');
        $before = DB::table('audit_events')->count();
        $this->withToken($token)->postJson('/api/orders/'.$order['id'].'/receive', ['lines' => [['detailId' => $confirmed['details'][0]['id'], 'quantity' => 1]]])->assertOk();
        $this->assertSame($before + 1, DB::table('audit_events')->count());
        $types = DB::table('audit_changes')->where('audit_event_id', $this->event('orders.received')->id)->pluck('entity_type')->all();
        foreach (['order', 'order_detail', 'product_variant', 'inventory_movement', 'order_status_history'] as $type) {
            $this->assertContains($type, $types);
        }
    }

    public function test_pdf_inline_attachment_and_rendering_errors_are_audited(): void
    {
        $token = $this->login();
        $variant = ProductVariant::where('id_product', 1)->firstOrFail();
        $id = $this->withToken($token)->postJson('/api/orders', ['details' => [['idProductVariant' => $variant->id, 'quantityOrdered' => 2]]])->assertCreated()->json('data.id');
        foreach (['' => 'inline', '?download=1' => 'attachment'] as $suffix => $delivery) {
            $this->withToken($token)->get('/api/orders/'.$id.'/pdf'.$suffix)->assertOk()->assertHeader('Content-Type', 'application/pdf');
            $event = $this->event('orders.pdf_generated');
            $this->assertSame('success', $event->outcome);
            $this->assertSame($delivery, json_decode($event->metadata, true)['delivery']);
        }
        $this->mock(OrderPdfService::class)->shouldReceive('render')->andThrow(new RuntimeException('Render failed'));
        foreach (app('router')->getRoutes() as $route) {
            if (str_ends_with($route->getActionName(), 'OrderController@pdf')) {
                $route->flushController();
            }
        }
        $this->withToken($token)->get('/api/orders/'.$id.'/pdf')->assertStatus(500);
        $this->assertSame('failed', $this->event('orders.pdf_generated')->outcome);
    }

    public function test_pdf_is_not_delivered_if_event_storage_fails(): void
    {
        $token = $this->login();
        $variant = ProductVariant::where('id_product', 1)->firstOrFail();
        $id = $this->withToken($token)->postJson('/api/orders', ['details' => [['idProductVariant' => $variant->id, 'quantityOrdered' => 1]]])->assertCreated()->json('data.id');
        DB::listen(function ($query) {
            if (preg_match('/insert into ["`]audit_events["`]/i', $query->sql)) {
                throw new RuntimeException('Audit unavailable');
            }
        });
        $response = $this->withToken($token)->getJson('/api/orders/'.$id.'/pdf')->assertStatus(503);
        $this->assertStringNotContainsString('%PDF', $response->getContent());
    }

    public function test_failed_audit_keeps_previous_image_and_cleans_new_files(): void
    {
        Storage::fake('public');
        $token = $this->login();
        Storage::disk('public')->put('products/1/old.png', 'previous');
        Product::findOrFail(1)->update(['image' => 'products/1/old.png']);
        $this->failChangeInsert();
        $this->withToken($token)->post('/api/products/1/image', ['image' => UploadedFile::fake()->image('new.png')], ['Accept' => 'application/json'])->assertStatus(503);
        $this->assertSame('products/1/old.png', Product::findOrFail(1)->image);
        Storage::disk('public')->assertExists('products/1/old.png');
        $this->assertSame(['products/1/old.png'], Storage::disk('public')->allFiles());
    }

    public function test_logo_is_stored_as_fingerprint_and_system_changes_are_supported(): void
    {
        $token = $this->login();
        $logo = 'data:image/png;base64,'.base64_encode('private-image-bytes');
        $this->withToken($token)->patchJson('/api/company-info', ['logo' => $logo])->assertOk();
        $event = $this->event('company_info.updated');
        $change = DB::table('audit_changes')->where('audit_event_id', $event->id)->first();
        $this->assertStringNotContainsString('base64', $change->after_values);
        $this->assertSame(hash('sha256', $logo), json_decode($change->after_values, true)['logo']['sha256']);
        // Un proceso obtiene un scope nuevo, sin identidad HTTP heredada.
        $audit = new AuditTrail;
        app()->instance(AuditTrail::class, $audit);
        $audit->run('catalogs', 'maintenance.category_updated', fn () => Category::findOrFail(1)->update(['description' => 'Maintenance']));
        $event = $this->event('maintenance.category_updated');
        $this->assertNull($event->actor_id);
        $this->assertSame('system', $event->source);
    }
}
