<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\FormatsDates;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Categorías de ítems de inspección preoperacional.
 */
class InspectionCategory extends Model
{
    use FormatsDates, HasFactory, HasUuid, LogsActivity;

    /**
     * @var string
     */
    protected $table = 'inspection_categories';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'code',
        'name',
        'description',
    ];

    /**
     * Normaliza un nombre o código de categoría al formato estándar MAYUSCULAS_CON_GUION_BAJO.
     */
    public static function normalizeCode(string $value): string
    {
        $ascii = Str::ascii(trim($value));
        $upper = strtoupper($ascii);
        $slug = (string) preg_replace('/[^A-Z0-9]+/', '_', $upper);
        $trimmed = trim($slug, '_');

        return substr($trimmed, 0, 100);
    }

    /**
     * Construye una etiqueta legible a partir de un nombre o código.
     */
    public static function formatLabel(string $value): string
    {
        $cleaned = trim((string) preg_replace('/\s+/', ' ', $value));
        if ($cleaned === '') {
            return '';
        }

        // Si parece un código tipo VIDRIOS_ESPEJOS o MAYUSCULAS, convertir a título legible.
        if (str_contains($cleaned, '_') || $cleaned === strtoupper($cleaned)) {
            $words = str_replace('_', ' ', mb_strtolower($cleaned, 'UTF-8'));

            return mb_convert_case($words, MB_CASE_TITLE, 'UTF-8');
        }

        return substr($cleaned, 0, 100);
    }
}
