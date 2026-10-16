<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SPEC-002 §6 — Rol y vigencia de las firmas.
 *
 * Antes, el PDF y el servicio interpretaban las firmas por ORDEN DE INSERCIÓN
 * (primera = funcionario, segunda = conductor), lo que se rompe con reenvíos
 * parciales. Aquí se persiste el rol explícito y el estado de vigencia.
 */
return new class extends Migration
{
    /** Tipos de entidad que participan del flujo PCP. */
    private const ENTITY_PLANILLA = 'App\\Models\\ServiceDeliveryControlSheet';

    private const ENTITY_RUTA = 'App\\Models\\ServiceDeliveryControlSheetRoute';

    private const ENTITY_COORDINADOR = 'App\\Models\\ServiceDeliveryControlSheetCoordinator';

    public function up(): void
    {
        Schema::table('signatures', static function (Blueprint $table): void {
            $table->string('signer_role', 30)->nullable()->after('entity_id')
                ->comment('conductor|funcionario|coordinador; NULL = rol no determinable (legado)');
            $table->string('scope', 30)->nullable()->after('signer_role')
                ->comment('planilla|recorrido');
            $table->string('status', 20)->default('vigente')->after('scope')
                ->comment('vigente|reemplazada|revocada');
            $table->timestamp('signed_at')->nullable()->after('status')
                ->comment('Momento real de la firma');
            $table->char('signer_uuid', 36)->nullable()->after('signed_at')
                ->comment('UUID del firmante; NULL en subcontratados con datos en texto libre');
        });

        Schema::table('signatures', static function (Blueprint $table): void {
            $table->index(
                ['entity_type', 'entity_id', 'signer_role', 'status'],
                'idx_signatures_entity_role'
            );
        });

        $this->backfillRoles();
    }

    /**
     * Rellena signer_role/scope/status por orden de inserción (única señal disponible
     * en el legado) y deja la última de cada rol como vigente.
     */
    private function backfillRoles(): void
    {
        $tipos = [
            self::ENTITY_PLANILLA => ['scope' => 'planilla', 'roles' => ['funcionario', 'conductor']],
            self::ENTITY_RUTA => ['scope' => 'recorrido', 'roles' => ['funcionario', 'conductor']],
            self::ENTITY_COORDINADOR => ['scope' => 'planilla', 'roles' => ['coordinador']],
        ];

        // En el histórico, una planilla SIN recorridos era una jornada en
        // disponibilidad: allí solo firmaba el conductor. Sin esto, su única firma
        // quedaría rotulada como "funcionario" y el rol se leería al revés.
        $planillasSinRecorridos = DB::table('service_delivery_control_sheet as s')
            ->leftJoin(
                'service_delivery_control_sheet_routes as r',
                'r.service_delivery_control_sheet_uuid',
                '=',
                's.uuid'
            )
            ->whereNull('r.id')
            ->pluck('s.id')
            ->flip()
            ->all();

        foreach ($tipos as $entityType => $conf) {
            $entidades = DB::table('signatures')
                ->where('entity_type', $entityType)
                ->whereNull('deleted_at')
                ->orderBy('entity_id')
                ->orderBy('id')
                ->get(['id', 'entity_id', 'created_at']);

            // Agrupa por entidad para asignar roles según posición.
            $porEntidad = [];
            foreach ($entidades as $fila) {
                $porEntidad[$fila->entity_id][] = $fila;
            }

            foreach ($porEntidad as $entityId => $filas) {
                $roles = $conf['roles'];
                if ($entityType === self::ENTITY_PLANILLA && isset($planillasSinRecorridos[$entityId])) {
                    $roles = ['conductor'];
                }

                // Posición -> rol. Solo las dos primeras son inequívocas.
                $asignado = [];
                foreach ($filas as $posicion => $fila) {
                    $asignado[$fila->id] = [
                        'rol' => $roles[$posicion] ?? null,
                        'signed_at' => $fila->created_at,
                    ];
                }

                // Última instancia de cada rol = vigente; las anteriores, reemplazadas.
                $ultimaPorRol = [];
                foreach ($asignado as $id => $datos) {
                    if ($datos['rol'] !== null) {
                        $ultimaPorRol[$datos['rol']] = $id;
                    }
                }

                foreach ($asignado as $id => $datos) {
                    DB::table('signatures')->where('id', $id)->update([
                        'signer_role' => $datos['rol'],
                        'scope' => $conf['scope'],
                        'status' => ($datos['rol'] !== null && ($ultimaPorRol[$datos['rol']] ?? null) === $id)
                            ? 'vigente'
                            : 'reemplazada',
                        'signed_at' => $datos['signed_at'],
                    ]);
                }
            }
        }

        // Firmas soft-deleted quedan revocadas.
        DB::table('signatures')
            ->whereNotNull('deleted_at')
            ->update(['status' => 'revocada']);
    }

    public function down(): void
    {
        Schema::table('signatures', static function (Blueprint $table): void {
            $table->dropIndex('idx_signatures_entity_role');
            $table->dropColumn(['signer_role', 'scope', 'status', 'signed_at', 'signer_uuid']);
        });
    }
};
