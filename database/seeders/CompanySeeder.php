<?php

namespace Database\Seeders;

use App\Models\CompanyInfo;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        CompanyInfo::create([
            'name'               => 'Guayaberas Lopez Silva',
            'rfc'                => 'XAXX010101000',
            'legal_name'         => 'Guayaberas Lopez Silva S.A. de C.V.',
            'tax_regime'         => '601',
            'logo'               => null,
            'street'             => 'Calle 15',
            'external_number'    => '94 A',
            'cross_street_one'   => 'Calle 16',
            'cross_street_two'   => 'Calle 18',
            'postal_code'        => '97000',
            'neighborhood'       => 'Centro',
            'city'               => 'Mérida',
            'stock_control'      => true,
            'quantity_integers'  => 9,
            'quantity_decimals'  => 3,
            'grid_settings'      => null,
            'status'             => 'active',
        ]);
    }
}
