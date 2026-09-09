<?php

namespace Database\Seeders;

use App\Models\Withholding;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class WithholdingSeeder extends Seeder
{
    public function run(): void
    {
        $withholdings = [
            [
                'code' => 'RET01',
                'name' => 'Retefuente Honorarios 10%',
                'type' => 'RETEFUENTE',
                'dian_concept' => '06',
                'base_minimum' => 0.00,
                'rate' => 10.00,
                'debit_account' => '236515',
                'credit_account' => '135515',
                'applies_purchases' => true,
                'applies_sales' => false,
            ],
            [
                'code' => 'RET02',
                'name' => 'Retefuente Honorarios 11%',
                'type' => 'RETEFUENTE',
                'dian_concept' => '06',
                'base_minimum' => 0.00,
                'rate' => 11.00,
                'debit_account' => '236515',
                'credit_account' => '135515',
                'applies_purchases' => true,
                'applies_sales' => false,
            ],
            [
                'code' => 'RET03',
                'name' => 'ReteICA Servicios 9.66x1000',
                'type' => 'RETEICA',
                'dian_concept' => '07',
                'base_minimum' => 160000.00,
                'rate' => 0.966,
                'debit_account' => '236801',
                'credit_account' => '135518',
                'applies_purchases' => true,
                'applies_sales' => true,
            ],
        ];

        foreach ($withholdings as $wh) {
            Withholding::firstOrCreate(
                ['code' => $wh['code']],
                [
                    'uuid' => Str::uuid()->toString(),
                    'name' => $wh['name'],
                    'type' => $wh['type'],
                    'dian_concept' => $wh['dian_concept'],
                    'base_minimum' => $wh['base_minimum'],
                    'rate' => $wh['rate'],
                    'debit_account' => $wh['debit_account'],
                    'credit_account' => $wh['credit_account'],
                    'applies_purchases' => $wh['applies_purchases'],
                    'applies_sales' => $wh['applies_sales'],
                    'is_active' => true,
                ]
            );
        }
    }
}
