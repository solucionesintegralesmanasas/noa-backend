<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        $methods = [
            ['dian_code' => '10', 'name' => 'Efectivo'],
            ['dian_code' => '42', 'name' => 'Consignación'],
            ['dian_code' => '20', 'name' => 'Cheque'],
            ['dian_code' => '47', 'name' => 'Transferencia'],
            ['dian_code' => '71', 'name' => 'Bonos'],
            ['dian_code' => '72', 'name' => 'Vales'],
            ['dian_code' => '1', 'name' => 'Medio de pago no definido'],
            ['dian_code' => '49', 'name' => 'Tarjeta Débito'],
            ['dian_code' => '48', 'name' => 'Tarjeta Crédito'],
            ['dian_code' => 'ZZZ', 'name' => 'Otro*'],
        ];

        foreach ($methods as $method) {
            PaymentMethod::firstOrCreate(
                ['dian_code' => $method['dian_code']],
                [
                    'uuid' => Str::uuid()->toString(),
                    'name' => $method['name'],
                    'is_active' => true,
                ]
            );
        }
    }
}
