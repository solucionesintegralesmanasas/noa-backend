<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Notification;
use App\Services\Notifications\NotificationsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\InsertaFilas;
use Tests\Support\PresupuestoConsultas;
use Tests\TestCase;

/**
 * SPEC-004: la sincronización de notificaciones y el conteo de la campana
 * declaran su presupuesto. El sync escribe una vez (no admite re-medición
 * sobre los mismos datos); la campana es una agregación constante.
 */
#[\PHPUnit\Framework\Attributes\Group('perf')]
class NotificacionesConsultasTest extends TestCase
{
    use RefreshDatabase, PresupuestoConsultas, InsertaFilas;

    private string $empresa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->empresa = $this->insertar('companies', ['business_name' => 'Empresa notificaciones', 'is_active' => true]);
    }

    private function vehiculoConVencimientos(string $placa): void
    {
        $v = $this->insertar('vehicles', ['company_uuid' => $this->empresa, 'vehicle_license_plate' => $placa, 'is_active' => true]);
        $this->insertar('vehicle_documents', [
            'company_uuid' => $this->empresa, 'vehicle_uuid' => $v, 'document_type' => 'SOAT',
            'expiry_date' => Carbon::today()->addDays(5)->toDateString(), 'status' => 'VIGENTE',
        ]);
        $this->insertar('operation_cards', [
            'company_uuid' => $this->empresa, 'vehicle_uuid' => $v, 'operating_card_number' => 'TC-'.$placa,
            'expiration_date' => Carbon::today()->addDays(5)->toDateString(), 'status' => true,
        ]);
    }

    private function conductorConLicencia(string $apellido): void
    {
        $t = $this->insertar('third_parties', ['company_uuid' => $this->empresa, 'first_name' => 'Conductor', 'last_name' => $apellido]);
        $this->insertar('driver_licenses', [
            'company_uuid' => $this->empresa, 'third_party_uuid' => $t, 'number' => 'LIC-'.$apellido,
            'category' => 'C2', 'expiration_date' => Carbon::today()->addDays(5)->toDateString(), 'status' => 'VIGENTE',
        ]);
    }

    public function test_la_sincronizacion_tiene_un_tope_de_consultas(): void
    {
        $this->vehiculoConVencimientos('NOT001');
        $this->vehiculoConVencimientos('NOT002');
        $this->vehiculoConVencimientos('NOT003');
        $this->conductorConLicencia('Uno');
        $this->conductorConLicencia('Dos');

        // El sync escribe: se mide una sola vez sobre datos frescos.
        // Medido: 22 consultas constantes con 3 vehículos y 2 licencias (lectores +
        // precarga del estado + upsert en bloque; ya no crece con la flota).
        $this->assertPresupuesto(22, fn () => app(NotificationsService::class)->syncNotifications($this->empresa), 'Sincronización de notificaciones');
    }

    /** Consultas de un sync sobre datos frescos de una empresa nueva con $vehiculos vehículos. */
    private function consultasDeSyncConVehiculos(int $vehiculos): int
    {
        $this->empresa = $this->insertar('companies', ['business_name' => 'Empresa sync '.$vehiculos, 'is_active' => true]);
        for ($i = 1; $i <= $vehiculos; $i++) {
            $this->vehiculoConVencimientos('V'.$vehiculos.'-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT));
        }

        return $this->contarConsultas(fn () => app(NotificationsService::class)->syncNotifications($this->empresa));
    }

    /**
     * Pendiente conocida: `syncNotifications` hacía lecturas y escrituras por
     * alerta. Ya no: la empresa la trae el lector, el estado existente se
     * precarga y las escrituras van en bloque, así que el coste es constante.
     */
    public function test_el_sync_no_empeora_su_costo_por_vehiculo(): void
    {
        $this->consultasDeSyncConVehiculos(1); // calentamiento: consultas de arranque
        $pocos = $this->consultasDeSyncConVehiculos(2);
        $muchos = $this->consultasDeSyncConVehiculos(6);

        $porVehiculo = ($muchos - $pocos) / 4;
        $this->assertSame(0, $porVehiculo, "El sync cuesta $porVehiculo consultas por vehículo ($pocos con 2; $muchos con 6)");
    }

    public function test_el_sync_actualiza_sin_duplicar_y_conserva_el_estado_leido(): void
    {
        $this->vehiculoConVencimientos('SYN001');
        app(NotificationsService::class)->syncNotifications($this->empresa);
        $antes = Notification::where('company_uuid', $this->empresa)->count();
        $this->assertGreaterThan(0, $antes);

        $primera = Notification::where('company_uuid', $this->empresa)->first();
        $primera->update(['status' => 'LEIDA']);

        app(NotificationsService::class)->syncNotifications($this->empresa);

        $this->assertSame($antes, Notification::where('company_uuid', $this->empresa)->count(), 'El sync duplicó notificaciones');
        $this->assertSame('LEIDA', Notification::where('uuid', $primera->uuid)->value('status'), 'El sync revivió una notificación leída');
    }

    public function test_el_sync_no_escribe_en_el_registro_de_actividad(): void
    {
        $this->vehiculoConVencimientos('ACT001');
        $antes = DB::table('activity_log')->count();

        app(NotificationsService::class)->syncNotifications($this->empresa);

        $this->assertSame($antes, DB::table('activity_log')->count(), 'El sync escribió en activity_log');
    }

    public function test_la_campana_no_crece_con_las_notificaciones(): void
    {
        $this->vehiculoConVencimientos('CAM001');
        $this->vehiculoConVencimientos('CAM002');
        app(NotificationsService::class)->syncNotifications($this->empresa);

        $medir = fn () => app(NotificationsService::class)->getUnreadCountsByPriority($this->empresa);
        $ampliar = function (): void {
            for ($i = 3; $i <= 10; $i++) {
                $this->vehiculoConVencimientos('CAM'.str_pad((string) $i, 3, '0', STR_PAD_LEFT));
            }
            app(NotificationsService::class)->syncNotifications($this->empresa);
        };

        $this->assertConteoConstante($medir, $ampliar, 'Conteo de la campana');
        // Medido: 1 consulta (agregado por prioridad).
        $this->assertPresupuesto(1, $medir, 'Conteo de la campana');
    }

    public function test_el_mantenimiento_preventivo_detecta_los_hitos_vencidos(): void
    {
        $vehiculo = $this->insertar('vehicles', [
            'company_uuid' => $this->empresa, 'vehicle_license_plate' => 'MTO001', 'is_active' => true,
        ]);
        $this->insertar('vehicle_inspections', ['vehicle_uuid' => $vehiculo, 'mileage' => 25000]);

        $alertas = app(NotificationsService::class)->notificationsForPreventativeMaintenance($this->empresa);
        $hitos = array_column(array_filter($alertas, fn ($a) => $a['status'] === 'VENCIDO'), 'milestone');
        sort($hitos);

        $this->assertSame([10000, 20000], $hitos);
    }
}
