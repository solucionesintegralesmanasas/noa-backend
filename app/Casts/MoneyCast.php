<?php

declare(strict_types=1);

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Money\Currency;
use Money\Money;

/**
 * Cast de Eloquent para manejar dinero automáticamente usando moneyphp/money.
 *
 * USO EN EL MODELO:
 *
 * protected $casts = [
 *     'price' => MoneyCast::class,             // Usa COP por defecto
 *     'total' => MoneyCast::class . ':USD',    // Fuerza USD
 * ];
 */
class MoneyCast implements CastsAttributes
{
    /**
     * El código de moneda por defecto.
     */
    protected string $defaultCurrency;

    /**
     * Permite recibir parámetros desde el array $casts del modelo.
     */
    public function __construct(string $defaultCurrency = 'COP')
    {
        $this->defaultCurrency = $defaultCurrency;
    }

    /**
     * Transforma el entero de la Base de Datos a un Objeto Money.
     *
     * @param  string  $key  Nombre de la columna (ej: 'total')
     * @param  mixed  $value  Valor en centavos desde la DB (ej: 10500)
     * @param  array  $attributes  Todas las columnas de la fila actual
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        if ($value === null) {
            return null;
        }

        // Magia: Si en tu tabla creaste una columna "total_currency", la lee automáticamente.
        // Si no existe, usa la moneda por defecto configurada (COP).
        $currencyCode = $attributes["{$key}_currency"] ?? $this->defaultCurrency;

        // Truncar decimales para evitar la excepción "Amount must be an integer(ish) value"
        $valueStr = (string) $value;
        if (strpos($valueStr, '.') !== false) {
            $valueStr = explode('.', $valueStr)[0];
        }

        return new Money($valueStr, new Currency($currencyCode));
    }

    /**
     * Prepara el Objeto Money para guardarse como entero en la Base de Datos.
     *
     * @param  string  $key  Nombre de la columna
     * @param  Money|int|string|null  $value
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null) {
            return null;
        }

        if (! $value instanceof Money) {
            // Protección de seguridad por si le asignan un entero directamente
            if (! is_numeric($value)) {
                throw new InvalidArgumentException("El valor para {$key} debe ser un número o una instancia de Money.");
            }

            // Truncar decimales antes de instanciar Money (ya que app asume unidades mayores como enteros)
            $valueStr = (string) $value;
            if (strpos($valueStr, '.') !== false) {
                $valueStr = explode('.', $valueStr)[0];
            }

            $value = new Money($valueStr, new Currency($this->defaultCurrency));
        }

        // Si el modelo tiene explícitamente una columna de moneda en sus atributos
        // o mapeos, podríamos devolver un array para guardar ambos.
        // Pero para máxima compatibilidad con esquemas normales, devolvemos solo el monto.

        return $value->getAmount(); // Devuelve el string del número entero para la DB
    }
}
