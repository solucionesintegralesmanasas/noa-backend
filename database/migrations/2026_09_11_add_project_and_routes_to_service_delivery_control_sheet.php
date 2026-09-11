<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. project_uuid idempotente (la columna ya puede existir por intento previo)
        if (! Schema::hasColumn('service_delivery_control_sheet', 'project_uuid')) {
            Schema::table('service_delivery_control_sheet', function (Blueprint $table) {
                $table->char('project_uuid', 36)->nullable()->after('company_uuid');
            });
        }

        // FK project_uuid -> projects.uuid (solo si no existe)
        try {
            Schema::table('service_delivery_control_sheet', function (Blueprint $table) {
                $table->foreign('project_uuid', 'fk_sheet_project')->references('uuid')->on('projects')->nullOnDelete();
            });
        } catch (\Throwable $e) {
            // FK ya existe o tabla projects aún no creada: se ignora para no bloquear
        }

        // 2. Tabla de N recorridos por planilla
        if (! Schema::hasTable('service_delivery_control_sheet_routes')) {
            Schema::create('service_delivery_control_sheet_routes', function (Blueprint $table) {
                $table->id();
                $table->char('uuid', 36)->unique();
                $table->char('service_delivery_control_sheet_uuid', 36)->nullable()->index('idx_routes_sheet_uuid');
                $table->foreign('service_delivery_control_sheet_uuid', 'fk_routes_sheet')->references('uuid')->on('service_delivery_control_sheet')->cascadeOnDelete();
                $table->integer('order_index')->default(1);
                $table->string('origin', 255)->nullable();
                $table->string('destination', 255)->nullable();
                $table->decimal('distance_km', 8, 2)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // 3. Ampliar ENUM type_of_control_sheet para vehículo externo / contratado
        try {
            DB::statement("ALTER TABLE `service_delivery_control_sheet` MODIFY COLUMN `type_of_control_sheet` ENUM('DIRECTO_CON_LA_EMPRESA','SUBCONTRATADO','CON_VEHICULO_CONTRATADO','EXTERNO_PLATAFORMA') NULL");
        } catch (\Throwable $e) {
            // Si el motor no soporta MODIFY o ya está ampliado, ignorar
        }

        // 3b. Flexibilizar subcontratados: clase nullable + fuec_uuid para externos de plataforma
        try {
            if (Schema::hasTable('service_internal_controls_subcontracted')) {
                if (! Schema::hasColumn('service_internal_controls_subcontracted', 'fuec_uuid')) {
                    Schema::table('service_internal_controls_subcontracted', function (Blueprint $table) {
                        $table->char('fuec_uuid', 36)->nullable()->after('service_delivery_control_sheet_uuid');
                    });
                }
                DB::statement('ALTER TABLE `service_internal_controls_subcontracted` MODIFY COLUMN `vehicle_class_uuid` CHAR(36) NULL');
            }
        } catch (\Throwable $e) {
        }

        // 4. Migrar daily_route legacy a 1 recorrido para planillas sin routes
        try {
            $sheets = DB::table('service_delivery_control_sheet')
                ->whereNotNull('daily_route')
                ->where('daily_route', '!=', '')
                ->select('uuid', 'daily_route')
                ->get();

            foreach ($sheets as $s) {
                $exists = DB::table('service_delivery_control_sheet_routes')
                    ->where('service_delivery_control_sheet_uuid', $s->uuid)
                    ->exists();
                if (! $exists) {
                    DB::table('service_delivery_control_sheet_routes')->insert([
                        'uuid' => (string) \Illuminate\Support\Str::uuid(),
                        'service_delivery_control_sheet_uuid' => $s->uuid,
                        'order_index' => 1,
                        'origin' => $s->daily_route,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            // No bloquear migración por datos legacy
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('service_delivery_control_sheet_routes');

        try {
            Schema::table('service_delivery_control_sheet', function (Blueprint $table) {
                $table->dropForeign('fk_sheet_project');
            });
        } catch (\Throwable $e) {
        }

        if (Schema::hasColumn('service_delivery_control_sheet', 'project_uuid')) {
            Schema::table('service_delivery_control_sheet', function (Blueprint $table) {
                $table->dropColumn('project_uuid');
            });
        }
    }
};
