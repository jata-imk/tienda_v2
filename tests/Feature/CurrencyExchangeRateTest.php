<?php

namespace Tests\Feature;

use App\Models\CompanyInfo;
use App\Models\Currency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrencyExchangeRateTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_saves_the_exchange_rate(): void
    {
        $this->withoutMiddleware()
            ->postJson('/api/currencies', [
                'name'         => 'Dolar Estadounidense',
                'code'         => 'usd',
                'symbol'       => '$',
                'exchangeRate' => 17.25,
            ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'USD')
            ->assertJsonPath('data.exchangeRate', 17.25);

        $this->assertEqualsWithDelta(17.25, (float) Currency::first()->exchange_rate, 0.000001);
    }

    public function test_store_defaults_the_exchange_rate_to_one(): void
    {
        $this->withoutMiddleware()
            ->postJson('/api/currencies', ['name' => 'Pesos', 'code' => 'MXN', 'symbol' => '$'])
            ->assertCreated()
            ->assertJsonPath('data.exchangeRate', fn ($rate) => (float) $rate === 1.0);
    }

    public function test_update_changes_only_the_exchange_rate(): void
    {
        $currency = Currency::create([
            'name' => 'Dolar', 'code' => 'USD', 'symbol' => '$', 'exchange_rate' => 17.25, 'status' => 'active',
        ]);

        $this->withoutMiddleware()
            ->putJson("/api/currencies/{$currency->id}", ['exchangeRate' => 18.4])
            ->assertOk()
            ->assertJsonPath('data.exchangeRate', 18.4)
            ->assertJsonPath('data.name', 'Dolar');
    }

    /**
     * La columna es decimal(18,6): un valor con mas de 6 decimales se redondearia
     * a 0 y uno con 12+ enteros desbordaria (500 en MariaDB). Ambos se rechazan
     * en validacion. Ojo: la suite corre en SQLite, que aceptaria los dos.
     */
    public function test_an_exchange_rate_outside_the_column_range_is_rejected(): void
    {
        $currency = Currency::create([
            'name' => 'Dolar', 'code' => 'USD', 'symbol' => '$', 'exchange_rate' => 17.25, 'status' => 'active',
        ]);

        $rates = [0, -1, 0.0000001, 10000000000000];

        foreach ($rates as $rate) {
            $this->withoutMiddleware()
                ->putJson("/api/currencies/{$currency->id}", ['exchangeRate' => $rate])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['exchangeRate']);
        }

        $this->assertEqualsWithDelta(17.25, $currency->fresh()->exchange_rate, 0.000001);
    }

    public function test_the_smallest_representable_rate_is_accepted(): void
    {
        $currency = Currency::create([
            'name' => 'Dolar', 'code' => 'USD', 'symbol' => '$', 'exchange_rate' => 17.25, 'status' => 'active',
        ]);

        $this->withoutMiddleware()
            ->putJson("/api/currencies/{$currency->id}", ['exchangeRate' => 0.000001])
            ->assertOk()
            ->assertJsonPath('data.exchangeRate', 0.000001);
    }

    public function test_the_base_currency_cannot_be_deactivated(): void
    {
        $currency = Currency::create([
            'name' => 'Pesos', 'code' => 'MXN', 'symbol' => '$', 'exchange_rate' => 1, 'status' => 'active',
        ]);
        CompanyInfo::create(['name' => 'Tienda demo', 'id_currency' => $currency->id, 'status' => 'active']);

        $this->withoutMiddleware()
            ->deleteJson("/api/currencies/{$currency->id}")
            ->assertStatus(409);

        $this->assertSame('active', $currency->fresh()->status);
    }

    public function test_a_currency_that_is_not_the_base_can_be_deactivated(): void
    {
        $base  = Currency::create([
            'name' => 'Pesos', 'code' => 'MXN', 'symbol' => '$', 'exchange_rate' => 1, 'status' => 'active',
        ]);
        $other = Currency::create([
            'name' => 'Dolar', 'code' => 'USD', 'symbol' => '$', 'exchange_rate' => 17.25, 'status' => 'active',
        ]);
        CompanyInfo::create(['name' => 'Tienda demo', 'id_currency' => $base->id, 'status' => 'active']);

        $this->withoutMiddleware()
            ->deleteJson("/api/currencies/{$other->id}")
            ->assertOk();

        $this->assertSame('inactive', $other->fresh()->status);
    }

    public function test_the_catalog_endpoint_exposes_the_exchange_rate(): void
    {
        Currency::create([
            'name' => 'Pesos', 'code' => 'MXN', 'symbol' => '$', 'exchange_rate' => 1, 'status' => 'active',
        ]);

        $this->withoutMiddleware()
            ->getJson('/api/catalogs')
            ->assertOk()
            ->assertJsonPath('data.currencies.0.exchangeRate', fn ($rate) => (float) $rate === 1.0);
    }
}
