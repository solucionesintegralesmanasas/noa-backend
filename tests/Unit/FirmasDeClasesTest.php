<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Ninguna clase de la aplicación puede tener una declaración incompatible con la
 * que hereda (la firma de un método sobrescrito, un tipo de retorno, la
 * visibilidade o un parámetro obligatorio nuevo).
 *
 * Es un error de compilación: PHP no lo captura con try/catch y finaliza el
 * proceso. Con mod_php en XAMPP eso mata el worker de Apache y el proxy del
 * frontend responde 502 Bad Gateway; en hosting compartido devuelve un 500 en
 * la primera petición que cargue la clase. Se comprueba en un subproceso para
 * poder reportar la clase culpable en lugar de abortar la suite.
 */
class FirmasDeClasesTest extends TestCase
{
    /** Clases mínimas para que un escaneo roto no pase por bueno. */
    private const MINIMO_DE_CLASES = 100;

    public function test_ninguna_clase_declera_una_firma_incompatible_con_la_que_hereda(): void
    {
        $script = __DIR__ . '/../Support/carga-clases.php';

        $this->assertFileExists($script, 'No se encontró el script de carga de clases.');

        $proceso = proc_open(
            escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script),
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $canales,
            dirname(__DIR__, 2)
        );

        $this->assertIsResource($proceso, 'No se pudo lanzar el subproceso de PHP.');

        fclose($canales[0]);
        $salida = (string) stream_get_contents($canales[1]);
        $errores = (string) stream_get_contents($canales[2]);
        fclose($canales[1]);
        fclose($canales[2]);
        $codigo = proc_close($proceso);

        $cargadas = [];

        foreach (explode("\n", $salida) as $linea) {
            if (! str_starts_with($linea, ':: ') || str_starts_with($linea, '::FIN')) {
                continue;
            }

            $cargadas[] = substr($linea, 3);
        }

        // El código de salida se mira primero: si PHP murió al compilar una clase,
        // el conteo sale incompleto y el mensaje útil es el de la clase culpable.
        if ($codigo !== 0) {
            $culpable = end($cargadas) ?: '(ninguna registrada)';

            $this->fail(
                "PHP finaliza el proceso al compilar una firma incompatible, así que la carga se detuvo en "
                . "{$culpable} (código de salida {$codigo}).\n"
                . 'Errores del subproceso: ' . trim($errores) . "\n"
                . 'Revisa la firma del método sobrescrito en '
                . str_replace('\\', '/', $culpable) . ' contra la de su clase padre.'
            );
        }

        $this->assertGreaterThanOrEqual(
            self::MINIMO_DE_CLASES,
            count($cargadas),
            'El escaneo no encontró las clases esperadas; la prueba no serviría de nada.'
        );

        $this->assertStringContainsString(
            '::FIN',
            $salida,
            'Las clases se cargaron sin errores pero el escaneo no terminó.'
        );
        $this->assertSame('', trim($errores), 'El subproceso de PHP escribió errores.');
    }
}