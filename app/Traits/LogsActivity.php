<?php

namespace App\Traits;

use Spatie\Activitylog\Contracts\Activity;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity as SpatieLogsActivity;

/**
 * Trait LogsActivity
 *
 * Registro de actividad estandarizado para modelos Eloquent.
 * Compatible con: spatie/laravel-activitylog ^4.12 + Laravel 12 + PHP 8.2+
 *
 * @author  Darwin Montes Lopez
 *
 * @version 5.0.0 — spatie/laravel-activitylog ^4.12 / Laravel 12 / PHP 8.2+
 *
 * @since   2025-08-07
 */
trait LogsActivity
{
    use SpatieLogsActivity;

    // -------------------------------------------------------------------------
    // Configuración principal (requerida por Spatie)
    // -------------------------------------------------------------------------

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName($this->resolveLogName())
            ->logOnly($this->resolveLogAttributes())
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $event) => $this->getDescriptionForEvent($event));
    }

    // -------------------------------------------------------------------------
    // Resolvers internos
    // -------------------------------------------------------------------------

    /**
     * Convierte el nombre de clase a snake_case.
     * Ejemplo: "UserProfile" → "user_profile".
     */
    protected function resolveLogName(): string
    {
        return strtolower(
            preg_replace('/(?<!^)[A-Z]/', '_$0', class_basename(static::class))
        );
    }

    /**
     * Atributos a registrar: usa $fillable si existe, de lo contrario todos.
     *
     * @return array<int, string>
     */
    protected function resolveLogAttributes(): array
    {
        return ! empty($this->fillable) ? $this->fillable : ['*'];
    }

    // -------------------------------------------------------------------------
    // Descripción del evento
    // -------------------------------------------------------------------------

    public function getDescriptionForEvent(string $eventName): string
    {
        $name = class_basename(static::class);
        $id = $this->getKey() ?? 'N/A';

        return match ($eventName) {
            'created' => "{$name} #{$id} fue creado",
            'updated' => "{$name} #{$id} fue actualizado",
            'deleted' => "{$name} #{$id} fue eliminado",
            'restored' => "{$name} #{$id} fue restaurado",
            'login' => "{$name} #{$id} inició sesión",
            'logout' => "{$name} #{$id} cerró sesión",
            default => "{$name} #{$id} fue {$eventName}",
        };
    }

    // -------------------------------------------------------------------------
    // Datos adicionales
    // -------------------------------------------------------------------------

    /**
     * Datos contextuales almacenados bajo la clave "extra".
     * Sobrescribe en el modelo para añadir campos sin perder los base.
     *
     * @return array<string, mixed>
     */
    public function getExtraLogData(string $eventName): array
    {
        $request = request();

        return [
            'model_type' => static::class,
            'model_id' => $this->getKey(),
            'event' => $eventName,
            'timestamp' => now()->toISOString(),
            'ip_address' => $request?->ip() ?? 'N/A',
            'user_agent' => $request?->userAgent() ?? 'N/A',
        ];
    }

    // -------------------------------------------------------------------------
    // Hook de Spatie v4 — se ejecuta antes de persistir la actividad
    // -------------------------------------------------------------------------

    /**
     * Spatie v4 accede a properties como cast de colección en el modelo Activity.
     * La forma correcta es asignar directamente el campo "extra" como atributo,
     * sin usar getProperties() que no existe en el contrato Activity.
     */
    public function tapActivity(Activity $activity, string $eventName): void
    {
        // $activity->properties es un cast a Collection en el modelo Activity de Spatie.
        // Usamos collect() para garantizar compatibilidad aunque sea null o array.
        $current = collect($activity->properties ?? []);

        $activity->properties = $current->merge([
            'extra' => $this->getExtraLogData($eventName),
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers — deshabilitar logging en runtime
    // -------------------------------------------------------------------------

    /**
     * Ejecuta un callback con el logging deshabilitado.
     * Garantiza que se rehabilite aunque ocurra una excepción.
     *
     * Uso: $model->withoutLogging(fn () => $model->update([...]));
     */
    public function withoutLogging(callable $callback): mixed
    {
        activity()->disableLogging();

        try {
            return $callback();
        } finally {
            activity()->enableLogging();
        }
    }
}
