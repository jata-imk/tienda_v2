<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Services\AuditTrail;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use PDOException;
use Tests\TestCase;

class AuditTransactionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Sin transacción exterior de test: permite verificar el reintento real.
        // migrate:fresh evita el down legado de category_product incompatible con SQLite.
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
        $this->beforeApplicationDestroyed(function () {
            RefreshDatabaseState::$migrated = false;
        });
    }

    public function test_transaction_retry_discards_changes_from_rolled_back_attempts(): void
    {
        $this->seed();
        $attempt = 0;
        app(AuditTrail::class)->run('catalogs', 'maintenance.retry', function () use (&$attempt) {
            $attempt++;
            Category::findOrFail($attempt)->update(['description' => 'Retry committed']);
            if ($attempt === 1) {
                throw new PDOException('Deadlock found when trying to get lock');
            }
        }, 2);
        $this->assertSame(2, $attempt);
        $this->assertDatabaseMissing('categories', ['id' => 1, 'description' => 'Retry committed']);
        $this->assertDatabaseHas('categories', ['id' => 2, 'description' => 'Retry committed']);
        $this->assertSame(1, DB::table('audit_events')->count());
        $this->assertSame(['2'], DB::table('audit_changes')->pluck('entity_id')->all());
    }
}
