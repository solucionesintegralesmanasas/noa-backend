<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ContextualQuestionPaymentsSeeder extends Seeder
{
    public function run(): void
    {
        $questions = [
            [
                'module' => 'fleet',
                'title' => 'Pagos pendientes',
                'question_text' => '¿Hay pagos pendientes?',
                'patterns' => ['pagos pendientes', 'pagos atrasados', 'cuotas pendientes', 'al dia con los pagos',
                    'al día con los pagos', 'deudas', 'estado de pagos', 'cargos pendientes', 'mora', 'adeudado',
                    'payment pending', 'pagos vencidos', 'cuotas vencidas', 'pendiente de pago',
                ],
                'response_type' => 'list',
                'response_template' => "💰 **{count}** cargos/pagos pendientes:\n{results}",
                'entity_type' => 'affiliate_charges',
                'filters' => ['status' => 'PENDIENTE'],
                'expiring_days' => null,
                'sort_order' => 200,
            ],
            [
                'module' => 'fleet',
                'title' => 'Pagos por vencer',
                'question_text' => '¿Qué pagos están por vencer?',
                'patterns' => ['pagos por vencer', 'cargos por vencer', 'cuotas por vencer', 'proximos pagos',
                    'próximos pagos', 'pagos proximos', 'facturas por vencer',
                ],
                'response_type' => 'expiring',
                'response_template' => "💰 **{count}** pagos próximos a vencer en {days} días:\n{results}",
                'entity_type' => 'affiliate_charges',
                'filters' => ['status' => 'PENDIENTE'],
                'expiring_days' => 15,
                'sort_order' => 210,
            ],
            [
                'module' => 'fleet',
                'title' => 'Todos los pagos',
                'question_text' => 'Mostrar todos los pagos',
                'patterns' => ['todos los pagos', 'lista de pagos', 'listar pagos', 'ver pagos', 'historial de pagos'],
                'response_type' => 'list',
                'response_template' => "💰 **{count}** pagos registrados:\n{results}",
                'entity_type' => 'affiliate_charges',
                'filters' => null,
                'expiring_days' => null,
                'sort_order' => 220,
            ],
            [
                'module' => 'fleet',
                'title' => 'Pagos al día',
                'question_text' => '¿Estoy al día con los pagos?',
                'patterns' => ['al dia con los pagos', 'al día con los pagos', 'estoy al dia', 'pagos al dia',
                    'pagos al día', 'esta al dia', 'está al día', 'todo al dia',
                ],
                'response_type' => 'count',
                'response_template' => '💰 Tienes **{count}** cargos pendientes. Los pagos al día están al corriente.',
                'entity_type' => 'affiliate_charges',
                'filters' => ['status' => 'PENDIENTE'],
                'expiring_days' => null,
                'sort_order' => 230,
            ],
        ];

        $now = now();

        foreach ($questions as $data) {
            DB::table('contextual_questions')->insert([
                'uuid' => (string) Str::uuid(),
                'module' => $data['module'],
                'title' => $data['title'],
                'question_text' => $data['question_text'],
                'patterns' => json_encode($data['patterns'], JSON_UNESCAPED_UNICODE),
                'response_type' => $data['response_type'],
                'response_template' => $data['response_template'],
                'entity_type' => $data['entity_type'],
                'filters' => $data['filters'] !== null ? json_encode($data['filters'], JSON_UNESCAPED_UNICODE) : null,
                'expiring_days' => $data['expiring_days'],
                'sort_order' => $data['sort_order'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
