<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ContextualQuestionExtraSeeder extends Seeder
{
    public function run(): void
    {
        $questions = [
            // ─── EMPRESAS ───
            [
                'module' => 'administration',
                'title' => 'Datos de la empresa',
                'question_text' => 'Mostrar datos de la empresa',
                'patterns' => ['datos de la empresa', 'informacion empresa', 'información empresa', 'mi empresa', 'perfil empresa', 'razon social'],
                'response_type' => 'list',
                'response_template' => "🏢 **{count}** empresas:\n{results}",
                'entity_type' => 'companies',
                'filters' => null,
                'expiring_days' => null,
                'sort_order' => 240,
            ],
            // ─── DOCUMENTOS VEHICULARES (TODOS) ───
            [
                'module' => 'fleet',
                'title' => 'Documentos vehiculares',
                'question_text' => 'Listar documentos vehiculares',
                'patterns' => ['documentos vehiculares', 'documentos del vehiculo', 'papeles del carro', 'documentos vehículo'],
                'response_type' => 'list',
                'response_template' => "📋 **{count}** documentos vehiculares:\n{results}",
                'entity_type' => 'vehicle_documents',
                'filters' => null,
                'expiring_days' => null,
                'sort_order' => 250,
            ],
            [
                'module' => 'fleet',
                'title' => 'Tecnomecánica por vencer',
                'question_text' => 'Tecnomecánica próxima a vencer',
                'patterns' => ['tecnomecanica por vencer', 'tecnomecánica por vencer', 'tecnomecanica vencida', 'revision tecnomecanica'],
                'response_type' => 'expiring',
                'response_template' => "📋 **{count}** tecnomecánicas próximas a vencer en {days} días:\n{results}",
                'entity_type' => 'vehicle_documents',
                'filters' => ['document_type' => 'TECNOMECANICA'],
                'expiring_days' => 30,
                'sort_order' => 260,
            ],
            // ─── CONDUCTORES (LISTA) ───
            [
                'module' => 'fleet',
                'title' => 'Lista de conductores',
                'question_text' => 'Listar conductores registrados',
                'patterns' => ['lista de conductores', 'listar conductores', 'todos los conductores', 'ver conductores', 'choferes'],
                'response_type' => 'list',
                'response_template' => "👤 **{count}** conductores:\n{results}",
                'entity_type' => 'owner_drivers',
                'filters' => null,
                'expiring_days' => null,
                'sort_order' => 270,
            ],
            // ─── TERCEROS (PROVEEDORES, EMPLEADOS) ───
            [
                'module' => 'third_parties',
                'title' => 'Lista de proveedores',
                'question_text' => 'Listar proveedores',
                'patterns' => ['lista de proveedores', 'listar proveedores', 'proveedores registrados'],
                'response_type' => 'list',
                'response_template' => "👤 **{count}** proveedores:\n{results}",
                'entity_type' => 'third_parties',
                'filters' => ['is_supplier' => true],
                'expiring_days' => null,
                'sort_order' => 275,
            ],
            [
                'module' => 'third_parties',
                'title' => 'Lista de empleados',
                'question_text' => 'Listar empleados',
                'patterns' => ['lista de empleados', 'listar empleados', 'empleados registrados', 'trabajadores'],
                'response_type' => 'list',
                'response_template' => "👤 **{count}** empleados:\n{results}",
                'entity_type' => 'third_parties',
                'filters' => ['is_employee' => true],
                'expiring_days' => null,
                'sort_order' => 280,
            ],
            // ─── VEHÍCULOS POR MARCA/MODELO ───
            [
                'module' => 'fleet',
                'title' => 'Vehículos por servicio',
                'question_text' => 'Vehículos por tipo de servicio',
                'patterns' => ['vehiculos publicos', 'vehiculos particulares', 'servicio publico', 'servicio particular'],
                'response_type' => 'list',
                'response_template' => "🚛 **{count}** vehículos:\n{results}",
                'entity_type' => 'vehicles',
                'filters' => null,
                'expiring_days' => null,
                'sort_order' => 285,
            ],
            // ─── TODOS LOS MANTENIMIENTOS ───
            [
                'module' => 'fleet',
                'title' => 'Todos los mantenimientos',
                'question_text' => 'Mostrar todos los mantenimientos',
                'patterns' => ['todos los mantenimientos', 'historial mantenimientos', 'lista mantenimientos', 'ver mantenimientos'],
                'response_type' => 'list',
                'response_template' => "🔧 **{count}** mantenimientos:\n{results}",
                'entity_type' => 'maintenances',
                'filters' => null,
                'expiring_days' => null,
                'sort_order' => 290,
            ],
            // ─── INSPECCIONES (TODAS) ───
            [
                'module' => 'fleet',
                'title' => 'Todas las inspecciones',
                'question_text' => 'Historial completo de inspecciones',
                'patterns' => ['todas las inspecciones', 'historial inspecciones', 'lista inspecciones'],
                'response_type' => 'list',
                'response_template' => "🔍 **{count}** inspecciones:\n{results}",
                'entity_type' => 'inspections',
                'filters' => null,
                'expiring_days' => null,
                'sort_order' => 295,
            ],
            // ─── LICENCIAS (TODAS) ───
            [
                'module' => 'fleet',
                'title' => 'Todas las licencias',
                'question_text' => 'Listar todas las licencias de conducción',
                'patterns' => ['todas las licencias', 'lista licencias', 'licencias registradas', 'ver licencias'],
                'response_type' => 'list',
                'response_template' => "🪪 **{count}** licencias:\n{results}",
                'entity_type' => 'driver_licenses',
                'filters' => null,
                'expiring_days' => null,
                'sort_order' => 300,
            ],
            // ─── TARJETAS DE OPERACIÓN (TODAS) ───
            [
                'module' => 'fleet',
                'title' => 'Todas las tarjetas de operación',
                'question_text' => 'Listar todas las TDO',
                'patterns' => ['todas las tarjetas de operacion', 'todas las tdo', 'lista tdo', 'ver tarjetas operacion'],
                'response_type' => 'list',
                'response_template' => "🪪 **{count}** tarjetas de operación:\n{results}",
                'entity_type' => 'operation_cards',
                'filters' => null,
                'expiring_days' => null,
                'sort_order' => 310,
            ],
            // ─── CONTRATISTAS (TODOS) ───
            [
                'module' => 'third_parties',
                'title' => 'Todos los contratistas',
                'question_text' => 'Listar todos los contratistas',
                'patterns' => ['todos los contratistas', 'lista contratistas', 'ver contratistas'],
                'response_type' => 'list',
                'response_template' => "📋 **{count}** contratistas:\n{results}",
                'entity_type' => 'contractors',
                'filters' => null,
                'expiring_days' => null,
                'sort_order' => 320,
            ],
            // ─── FUECs POR ESTADO ───
            [
                'module' => 'contract_extract',
                'title' => 'FUECs por estado',
                'question_text' => 'FUECs según su estado',
                'patterns' => ['fuecs por estado', 'contratos por estado', 'fuecs cancelados', 'fuecs vencidos'],
                'response_type' => 'list',
                'response_template' => "📄 **{count}** FUECs:\n{results}",
                'entity_type' => 'fuecs',
                'filters' => null,
                'expiring_days' => null,
                'sort_order' => 330,
            ],
            // ─── VEHÍCULOS INACTIVOS ───
            [
                'module' => 'fleet',
                'title' => 'Vehículos inactivos',
                'question_text' => 'Listar vehículos inactivos',
                'patterns' => ['vehiculos inactivos', 'vehiculos fuera servicio', 'vehiculos dados baja'],
                'response_type' => 'list',
                'response_template' => "🚛 **{count}** vehículos inactivos:\n{results}",
                'entity_type' => 'vehicles',
                'filters' => ['is_active' => false],
                'expiring_days' => null,
                'sort_order' => 340,
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
