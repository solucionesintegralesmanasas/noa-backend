<?php

declare(strict_types=1);

namespace App\Support\Media;

use App\Models\Company;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

/**
 * Generador de rutas personalizado para Spatie Media Library.
 *
 * Estructura de carpetas por NIT de la empresa:
 *
 *   companies/
 *     {NIT}/
 *       LOGO/
 *         logo-empresa.png
 *       FIRMA/
 *         firma-representante.png
 *       SOAT/
 *         soat-2026.pdf
 *       DOCUMENTOS/
 *         contrato.pdf
 *       conversions/    ← thumbnails generados automáticamente
 *       responsive-images/
 *
 * Esta misma estructura se replica en cualquier disco configurado
 * (local, S3, Google Drive) sin cambiar el código. Solo hay que
 * cambiar el valor de MEDIA_DISK en el .env.
 *
 * @author   Darwin Montes
 *
 * @version  1.1.0
 *
 * @since    1.0.0
 *
 * @updated  Preparado para Google Drive / S3, fallback a UUID
 */
class CompanyPathGenerator implements PathGenerator
{
    /**
     * Ruta base donde se almacena el archivo original.
     *
     * Resultado: companies/{NIT}/{COLECCIÓN}/
     * Ejemplo:   companies/900123456/LOGO/
     */
    public function getPath(Media $media): string
    {
        return $this->buildPath($media);
    }

    /**
     * Ruta para las conversiones (thumbnails generados por Spatie).
     *
     * Resultado: companies/{NIT}/{COLECCIÓN}/conversions/
     */
    public function getPathForConversions(Media $media): string
    {
        return $this->buildPath($media).'conversions/';
    }

    /**
     * Ruta para las imágenes responsivas.
     *
     * Resultado: companies/{NIT}/{COLECCIÓN}/responsive-images/
     */
    public function getPathForResponsiveImages(Media $media): string
    {
        return $this->buildPath($media).'responsive-images/';
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Construye la ruta base del archivo.
     *
     * Si el modelo es una Company con NIT definido → companies/{NIT}/{colección}/
     * Si no (otro modelo sin NIT)                  → fallback/{media-uuid}/
     */
    private function buildPath(Media $media): string
    {
        $model = $media->model;
        $company = null;

        if ($model instanceof Company) {
            $company = $model;
        } elseif (method_exists($model, 'company') && $model->company) {
            $company = $model->company;
        }

        if ($company && ! empty($company->document_number)) {
            // Sanitizamos el NIT: solo alfanuméricos y guiones
            $nit = $this->sanitizeNit($company->document_number);
            $collection = $media->collection_name;
            $subFolder = $media->uuid ?? $media->id;

            // Carpeta: companies/{NIT}/{COLECCION}/{UUID_DEL_ARCHIVO}/
            return "companies/{$nit}/{$collection}/{$subFolder}/";
        }

        // Fallback seguro: usamos el UUID del media (no el ID numérico)
        // para evitar colisiones en discos remotos como Google Drive / S3
        return 'fallback/'.($media->uuid ?? $media->id).'/';
    }

    /**
     * Limpia el NIT para usarlo como nombre de carpeta.
     *
     * - Elimina espacios y caracteres especiales
     * - Permite: letras, números y guiones
     * - Ejemplo: "900.123.456-7" → "900_123_456-7"
     */
    private function sanitizeNit(string $nit): string
    {
        return preg_replace('/[^a-zA-Z0-9\-]/', '_', trim($nit));
    }
}
