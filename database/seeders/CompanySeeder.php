<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        Company::create([
            'company_name' => 'Guayaberas Lopez Silva',
            'status'       => 'Active',
        ]);
    }
}
