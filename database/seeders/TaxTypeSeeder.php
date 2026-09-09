<?php

namespace Database\Seeders;

use App\Models\TaxType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TaxTypeSeeder extends Seeder
{
    public function run(): void
    {
        $taxes = [
            [
                'code' => '01',
                'name' => 'IVA 19%',
                'type' => 'IVA',
                'rate' => 19.00,
                'debit_account' => '240801',
                'credit_account' => '240805',
                'applies_sales' => true,
                'applies_purchases' => true,
            ],
            [
                'code' => '04',
                'name' => 'INC 8%',
                'type' => 'INC',
                'rate' => 8.00,
                'debit_account' => '240802',
                'credit_account' => '240806',
                'applies_sales' => true,
                'applies_purchases' => false,
            ],

        ];

        foreach ($taxes as $tax) {
            TaxType::firstOrCreate(
                ['code' => $tax['code'], 'rate' => $tax['rate']],
                [
                    'uuid' => Str::uuid()->toString(),
                    'name' => $tax['name'],
                    'type' => $tax['type'],
                    'debit_account' => $tax['debit_account'],
                    'credit_account' => $tax['credit_account'],
                    'applies_sales' => $tax['applies_sales'],
                    'applies_purchases' => $tax['applies_purchases'],
                    'is_active' => true,
                ]
            );
        }
    }
}
