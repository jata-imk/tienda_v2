<?php

namespace Tests\Feature;

use App\Models\CompanyInfo;
use App\Models\Currency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyInfoCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_returns_the_single_record(): void
    {
        CompanyInfo::create(['name' => 'Tienda demo', 'status' => 'active']);

        $this->withoutMiddleware()
            ->getJson('/api/company-info')
            ->assertOk()
            ->assertJsonPath('data.name', 'Tienda demo');
    }

    public function test_get_returns_404_when_empty(): void
    {
        $this->withoutMiddleware()->getJson('/api/company-info')->assertNotFound();
    }

    public function test_store_creates_the_record_with_a_base64_logo(): void
    {
        $logo = 'data:image/png;base64,' . base64_encode(str_repeat('a', 100000));

        $this->withoutMiddleware()
            ->postJson('/api/company-info', [
                'name'         => 'Tienda demo',
                'logo'         => $logo,
                'stockControl' => false,
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Tienda demo')
            ->assertJsonPath('data.stockControl', false)
            ->assertJsonPath('data.updatedAt', null);

        $this->assertSame($logo, CompanyInfo::first()->logo);
    }

    public function test_store_conflicts_when_a_record_already_exists(): void
    {
        CompanyInfo::create(['name' => 'Tienda demo', 'status' => 'active']);

        $this->withoutMiddleware()
            ->postJson('/api/company-info', ['name' => 'Otra tienda'])
            ->assertStatus(409);
    }

    public function test_patch_updates_camel_case_keys(): void
    {
        CompanyInfo::create(['name' => 'Tienda demo', 'legal_name' => 'SA de CV', 'status' => 'active']);

        $this->withoutMiddleware()
            ->patchJson('/api/company-info', ['legalName' => 'Nueva razon social'])
            ->assertOk()
            ->assertJsonPath('data.legalName', 'Nueva razon social');
    }

    public function test_patch_ignores_snake_case_keys(): void
    {
        CompanyInfo::create(['name' => 'Tienda demo', 'legal_name' => 'Original', 'status' => 'active']);

        // El contrato es camelCase: una llave snake_case no valida ni modifica nada.
        $this->withoutMiddleware()
            ->patchJson('/api/company-info', ['legal_name' => 'No debe aplicarse'])
            ->assertOk()
            ->assertJsonPath('data.legalName', 'Original');

        $this->assertSame('Original', CompanyInfo::first()->legal_name);
    }

    public function test_logo_that_is_not_base64_is_rejected(): void
    {
        CompanyInfo::create(['name' => 'Tienda demo', 'status' => 'active']);

        $this->withoutMiddleware()
            ->patchJson('/api/company-info', ['logo' => 'https://ejemplo.com/logo.png'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['logo']);
    }

    public function test_patch_sets_the_base_currency_and_returns_it_nested(): void
    {
        CompanyInfo::create(['name' => 'Tienda demo', 'status' => 'active']);
        $currency = Currency::create([
            'name' => 'Dolar', 'code' => 'USD', 'symbol' => '$', 'exchange_rate' => 17.25, 'status' => 'active',
        ]);

        $this->withoutMiddleware()
            ->patchJson('/api/company-info', ['idCurrency' => $currency->id])
            ->assertOk()
            ->assertJsonPath('data.idCurrency', $currency->id)
            ->assertJsonPath('data.currency.code', 'USD')
            ->assertJsonPath('data.currency.exchangeRate', 17.25);

        $this->assertSame($currency->id, CompanyInfo::first()->id_currency);
    }

    public function test_get_returns_a_null_currency_when_none_is_set(): void
    {
        CompanyInfo::create(['name' => 'Tienda demo', 'status' => 'active']);

        $this->withoutMiddleware()
            ->getJson('/api/company-info')
            ->assertOk()
            ->assertJsonPath('data.idCurrency', null)
            ->assertJsonPath('data.currency', null);
    }

    public function test_patch_rejects_a_currency_that_does_not_exist(): void
    {
        CompanyInfo::create(['name' => 'Tienda demo', 'status' => 'active']);

        $this->withoutMiddleware()
            ->patchJson('/api/company-info', ['idCurrency' => 9999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['idCurrency']);
    }

    public function test_patch_rejects_an_inactive_currency_as_the_base(): void
    {
        CompanyInfo::create(['name' => 'Tienda demo', 'status' => 'active']);
        $currency = Currency::create([
            'name' => 'Dolar', 'code' => 'USD', 'symbol' => '$', 'exchange_rate' => 17.25, 'status' => 'inactive',
        ]);

        $this->withoutMiddleware()
            ->patchJson('/api/company-info', ['idCurrency' => $currency->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['idCurrency']);
    }
}
