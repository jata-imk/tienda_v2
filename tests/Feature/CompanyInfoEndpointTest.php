<?php

namespace Tests\Feature;

use App\DTOs\CompanyInfo\UpdateCompanyInfoDTO;
use App\Models\CompanyInfo;
use App\Services\CompanyInfoService;
use Mockery;
use Tests\TestCase;

class CompanyInfoEndpointTest extends TestCase
{
    public function test_put_updates_company_info(): void
    {
        $this->mockCompanyInfoService(
            function (UpdateCompanyInfoDTO $dto): bool {
                $this->assertSame('Updated Company', $dto->fields['name']);
                $this->assertSame(false, $dto->fields['stock_control']);
                $this->assertSame(['columns' => ['name', 'price']], $dto->fields['grid_settings']);

                return true;
            },
            new CompanyInfo([
                'id'                => 1,
                'name'              => 'Updated Company',
                'rfc'               => 'XAXX010101000',
                'legal_name'        => 'Updated Company SA',
                'stock_control'     => false,
                'quantity_integers' => 8,
                'quantity_decimals' => 2,
                'grid_settings'     => ['columns' => ['name', 'price']],
                'status'            => 'inactive',
            ])
        );

        $response = $this->withoutMiddleware()->putJson('/api/company-info', [
            'name'              => 'Updated Company',
            'rfc'               => 'XAXX010101000',
            'legal_name'        => 'Updated Company SA',
            'stock_control'     => false,
            'quantity_integers' => 8,
            'quantity_decimals' => 2,
            'grid_settings'     => ['columns' => ['name', 'price']],
            'status'            => 'inactive',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Company info updated.')
            ->assertJsonPath('data.name', 'Updated Company')
            ->assertJsonPath('data.stockControl', false)
            ->assertJsonPath('data.gridSettings.columns.0', 'name');
    }

    public function test_patch_updates_only_sent_company_info_fields(): void
    {
        $this->mockCompanyInfoService(
            function (UpdateCompanyInfoDTO $dto): bool {
                $this->assertSame([
                    'legal_name'    => null,
                    'stock_control' => false,
                ], $dto->fields);

                return true;
            },
            new CompanyInfo([
                'id'            => 1,
                'name'          => 'Original',
                'legal_name'    => null,
                'city'          => 'Merida',
                'stock_control' => false,
            ])
        );

        $response = $this->withoutMiddleware()->patchJson('/api/company-info', [
            'legal_name'    => null,
            'stock_control' => false,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.name', 'Original')
            ->assertJsonPath('data.legalName', null)
            ->assertJsonPath('data.city', 'Merida')
            ->assertJsonPath('data.stockControl', false);
    }

    public function test_company_info_update_returns_not_found_when_missing(): void
    {
        $this->mockCompanyInfoService(fn(UpdateCompanyInfoDTO $dto): bool => true, null);

        $response = $this->withoutMiddleware()->patchJson('/api/company-info', [
            'name' => 'Updated Company',
        ]);

        $response
            ->assertNotFound()
            ->assertJsonPath('message', 'Company info not found.');
    }

    public function test_company_info_update_validates_payload(): void
    {
        $this->app->instance(CompanyInfoService::class, Mockery::mock(CompanyInfoService::class));

        $response = $this->withoutMiddleware()->patchJson('/api/company-info', [
            'status'            => 'archived',
            'quantity_integers' => 256,
            'grid_settings'     => 'invalid',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'status',
                'quantity_integers',
                'grid_settings',
            ]);
    }

    private function mockCompanyInfoService(callable $matcher, ?CompanyInfo $result): void
    {
        $service = Mockery::mock(CompanyInfoService::class);
        $service
            ->shouldReceive('update')
            ->once()
            ->with(Mockery::on($matcher))
            ->andReturn($result);

        $this->app->instance(CompanyInfoService::class, $service);
    }
}
