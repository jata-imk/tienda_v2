<?php

namespace Database\Seeders;

use App\Models\CompanyInfo;
use App\Models\Currency;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        $mxn = Currency::where('code', 'MXN')->firstOrFail();
        $company = CompanyInfo::first();

        if ($company) {
            if ($company->id_currency === null) {
                $company->update(['id_currency' => $mxn->id]);
            }

            // Igual que la moneda: solo se llena si falta, nunca se reemplaza
            // un logo que el usuario ya haya subido.
            if ($company->logo === null) {
                $company->update(['logo' => self::defaultLogo()]);
            }

            return;
        }

        CompanyInfo::create([
            'name' => 'Guayaberas Lopez Silva',
            'rfc' => 'XAXX010101000',
            'legal_name' => 'Guayaberas Lopez Silva S.A. de C.V.',
            'tax_regime' => '601',
            'id_currency' => $mxn->id,
            'logo' => self::defaultLogo(),
            'street' => 'Calle 15',
            'external_number' => '94 A',
            'cross_street_one' => 'Calle 16',
            'cross_street_two' => 'Calle 18',
            'postal_code' => '97000',
            'neighborhood' => 'Centro',
            'city' => 'Mérida',
            'stock_control' => true,
            'quantity_integers' => 9,
            'quantity_decimals' => 3,
            'grid_settings' => null,
            'status' => 'active',
        ]);
    }

    /**
     * Logo de la empresa en el formato de `company_info.logo` (base64 con
     * prefijo data-URI). Lo usan el login y el PDF de pedidos.
     */
    private static function defaultLogo(): string
    {
        return 'data:image/jpeg;base64,'.base64_encode(file_get_contents(__DIR__.'/assets/company-logo.jpg'));
    }
}
