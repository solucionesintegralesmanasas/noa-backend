<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @trait HasUuid
 *
 * @brief Trait para asignar automáticamente un UUID a los modelos de Eloquent.
 *
 * Este trait proporciona una funcionalidad para generar y asignar un UUID (Universally Unique Identifier)
 * a los modelos de Eloquent antes de que sean creados en la base de datos. Asegura que cada
 * instancia del modelo tenga un identificador único global, lo cual es útil para referencias
 * externas, sistemas distribuidos y para evitar problemas con IDs autoincrementales.
 *
 * @author Darwin Montes Lopez
 *
 * @version 1.0.0
 *
 * @creationDate 2025-08-07
 */
trait HasUuid
{
    /**
     * Método de arranque del trait.
     *
     * Este método se ejecuta automáticamente cuando el trait es utilizado por un modelo.
     * Registra un evento 'creating' en el modelo, que se dispara antes de que un nuevo
     * modelo sea guardado en la base de datos por primera vez.
     */
    protected static function bootHasUuid(): void
    {
        // Sección de Evento 'creating'
        // Registra un callback que se ejecutará justo antes de que un modelo sea creado.
        static::creating(function (Model $model) {
            // Verifica si el atributo 'uuid' del modelo está vacío.
            if (empty($model->uuid)) {
                // Si está vacío, genera un nuevo UUID y lo asigna al atributo 'uuid' del modelo.
                // Str::uuid() genera una cadena UUID de la versión 4 (RFC 4122).
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Verifica si el modelo tiene el atributo 'uuid'.
     *
     * Este método es útil para determinar programáticamente si un modelo que utiliza
     * este trait tiene la columna 'uuid' definida en su tabla de base de datos
     * o si el atributo está presente en la instancia del modelo.
     *
     * @return bool Retorna `true` si el modelo tiene el atributo 'uuid', `false` en caso contrario.
     */
    public function hasUuidAttribute(): bool
    {
        // Sección de Verificación de Atributo
        // Comprueba si la clave 'uuid' existe en el array de atributos del modelo.
        return array_key_exists('uuid', $this->attributes);
    }

    /**
     * Especifica que la columna 'uuid' debe usarse para el enlace de modelos en las rutas.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
