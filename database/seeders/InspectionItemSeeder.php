<?php

namespace Database\Seeders;

use App\Models\InspectionItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class InspectionItemSeeder extends Seeder
{
    public function run(): void
    {
        $items = [

            ['category' => 'DOCUMENTOS', 'description' => 'Licencia de tránsito'],
            ['category' => 'DOCUMENTOS', 'description' => 'Revisión técnico mecánica'],
            ['category' => 'DOCUMENTOS', 'description' => 'Seguro obligatorio'],
            ['category' => 'DOCUMENTOS', 'description' => 'Seguro terceros'],
            ['category' => 'DOCUMENTOS', 'description' => 'Licencia de conducción'],
            ['category' => 'DOCUMENTOS', 'description' => 'Planos, mapas rutas, GPS'],
            ['category' => 'DOCUMENTOS', 'description' => 'Contactos de Emergencia'],

            ['category' => 'DOTACION', 'description' => 'Botas'],
            ['category' => 'DOTACION', 'description' => 'Chaleco fotoluminiscente'],
            ['category' => 'DOTACION', 'description' => 'Guantes'],
            ['category' => 'DOTACION', 'description' => 'Protección auditiva'],
            ['category' => 'DOTACION', 'description' => 'Monogafas'],
            ['category' => 'DOTACION', 'description' => 'Radio teléfono / celular'],
            ['category' => 'DOTACION', 'description' => 'Hidratación'],

            ['category' => 'VIDRIOS_ESPEJOS', 'description' => 'Parabrisas'],
            ['category' => 'VIDRIOS_ESPEJOS', 'description' => 'Limpiaparabrisas'],
            ['category' => 'VIDRIOS_ESPEJOS', 'description' => 'Laterales'],
            ['category' => 'VIDRIOS_ESPEJOS', 'description' => 'Vidrio Trasero'],
            ['category' => 'VIDRIOS_ESPEJOS', 'description' => 'Lavaparabrisas trasero'],
            ['category' => 'VIDRIOS_ESPEJOS', 'description' => 'Espejo retrovisor'],
            ['category' => 'VIDRIOS_ESPEJOS', 'description' => 'Espejos laterales'],

            ['category' => 'EMERGENCIAS', 'description' => 'Kit de Accidentes'],
            ['category' => 'EMERGENCIAS', 'description' => 'Cámara fotográfica'],
            ['category' => 'EMERGENCIAS', 'description' => 'Linterna con pilas'],
            ['category' => 'EMERGENCIAS', 'description' => 'Bolígrafo'],

            ['category' => 'EXTINTOR', 'description' => 'Pin de Seguridad'],
            ['category' => 'EXTINTOR', 'description' => 'Cargado'],
            ['category' => 'EXTINTOR', 'description' => 'Vigente'],

            ['category' => 'HERRAMIENTAS', 'description' => 'Triángulos Reflectores (2)'],
            ['category' => 'HERRAMIENTAS', 'description' => 'Gato'],
            ['category' => 'HERRAMIENTAS', 'description' => 'Caja de herramientas'],
            ['category' => 'HERRAMIENTAS', 'description' => 'Cruceta'],
            ['category' => 'HERRAMIENTAS', 'description' => 'Tacos'],
            ['category' => 'HERRAMIENTAS', 'description' => 'Llanta de repuesto'],
            ['category' => 'HERRAMIENTAS', 'description' => 'Cables de Arranque'],

            ['category' => 'LUCES', 'description' => 'Bajas'],
            ['category' => 'LUCES', 'description' => 'Plenas'],
            ['category' => 'LUCES', 'description' => 'Direccionales'],
            ['category' => 'LUCES', 'description' => 'Cocuyos'],
            ['category' => 'LUCES', 'description' => 'Reversa'],
            ['category' => 'LUCES', 'description' => 'Anti-niebla'],
            ['category' => 'LUCES', 'description' => 'Luces de cabina'],
            ['category' => 'LUCES', 'description' => 'Emergencia'],
            ['category' => 'LUCES', 'description' => 'Tercer Stop'],

            ['category' => 'FLUIDOS', 'description' => 'Aceite motor'],
            ['category' => 'FLUIDOS', 'description' => 'Último cambio'],
            ['category' => 'FLUIDOS', 'description' => 'Dirección'],
            ['category' => 'FLUIDOS', 'description' => 'Líquido de frenos'],
            ['category' => 'FLUIDOS', 'description' => 'Refrigerante'],
            ['category' => 'FLUIDOS', 'description' => 'Agua parabrisas'],
            ['category' => 'FLUIDOS', 'description' => 'Nivel combustible'],
            ['category' => 'FLUIDOS', 'description' => 'Fugas de lubricantes'],
            ['category' => 'FLUIDOS', 'description' => 'Fugas de Agua'],

            ['category' => 'NEUMATICOS', 'description' => 'Delantero Derecho'],
            ['category' => 'NEUMATICOS', 'description' => 'Delantero Izquierdo'],
            ['category' => 'NEUMATICOS', 'description' => 'Trasero Derecho'],
            ['category' => 'NEUMATICOS', 'description' => 'Trasero Izquierdo'],
            ['category' => 'NEUMATICOS', 'description' => 'Llanta repuesto'],

            ['category' => 'PRESION', 'description' => 'Delantero Derecho'],
            ['category' => 'PRESION', 'description' => 'Delantero Izquierdo'],
            ['category' => 'PRESION', 'description' => 'Trasero Derecho'],
            ['category' => 'PRESION', 'description' => 'Trasero Izquierdo'],
            ['category' => 'PRESION', 'description' => 'Llanta repuesto'],

        ];

        foreach ($items as $item) {
            InspectionItem::firstOrCreate(
                ['item_name' => $item['description']],
                [
                    'uuid' => Str::uuid()->toString(),
                    'category' => $item['category'],
                    'description' => null,
                ]
            );
        }
    }
}
