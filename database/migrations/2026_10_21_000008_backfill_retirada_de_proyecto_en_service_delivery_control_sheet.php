<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Marca las hojas cuyo coche YA estaba retirado del proyecto antes de que
     * existiera la columna (el marcado del servicio solo actúa en el momento
     * del retiro). Idempotente: retira el estado final de las asignaciones —
     * un coche con alguna fila en el pivote pero SIN asignación activa hoy es
     * un coche retirado, y sus hojas de ese proyecto (padres e hijos, enlazados
     * por su control interno) quedan marcadas. Un coche aún activo, o que
     * NUNCA entró al pivote (hojas de tipos externos), sigue visible.
     */
    public function up(): void
    {
        DB::statement("UPDATE service_delivery_control_sheet sdc
            INNER JOIN service_internal_controls sic
                ON sic.service_delivery_control_sheet_uuid = sdc.uuid
            INNER JOIN (
                SELECT project_uuid, vehicle_uuid
                FROM project_driver_vehicles
                GROUP BY project_uuid, vehicle_uuid
            ) tuvo
                ON tuvo.project_uuid = sdc.project_uuid
                AND tuvo.vehicle_uuid = sic.vehicle_uuid
            LEFT JOIN (
                SELECT project_uuid, vehicle_uuid
                FROM project_driver_vehicles
                WHERE is_active = 1
            ) activa
                ON activa.project_uuid = sdc.project_uuid
                AND activa.vehicle_uuid = sic.vehicle_uuid
            SET sdc.retirada_de_proyecto = 1
            WHERE sdc.project_uuid IS NOT NULL
                AND activa.project_uuid IS NULL");
    }

    /**
     * No se revierte: antes de este relleno no había forma de distinguir qué
     * hojas correspondían a un coche ya retirado.
     */
    public function down(): void
    {
        //
    }
};
