<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\DriverLicense;
use App\Models\DriverLocationDailyStat;
use App\Models\ServiceDeliveryControlSheet;
use App\Models\SystemConfiguration;
use App\Models\ThirdParty;
use App\Services\Billing\CalculaTotales;
use App\Services\LicenseService;
use App\Services\Pdf\RouteMapService;
use App\Services\ServiceDeliveryControlSheet\PcpEvidence;
use App\Services\Tracking\LocationTrackingService;
use App\Support\Billing\NumeroALetras;
use App\Utils\OwnCompany;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Suite de Pruebas Adversariales y Fuzzing Determinista de Unidad (Red Team QA).
 *
 * Semilla determinista global: 20261112
 * Audita y estresa las funciones puras, modelos, normalizadores, calculadoras y utilidades
 * del backend sin depender de estado externo.
 */
class AdversarialUnitFuzzTest extends TestCase
{
    private const SEMILLA_FUZZ = 20261112;

    // =========================================================================
    // 1. AUDITORÍA ADVERSARIAL: NumeroALetras (app/Support/Billing/NumeroALetras.php)
    // =========================================================================

    /**
     * FALLO REAL DETECTADO #1:
     * Archivo: app/Support/Billing/NumeroALetras.php:46
     * Entrada: PHP_INT_MIN (-9223372036854775808)
     * Causa raíz: En PHP de 64 bits, el operador unario `-$numero` aplicado a `PHP_INT_MIN`
     * desborda la representación de entero con signo y devuelve `float(9223372036854775808)`.
     * Al tener `declare(strict_types=1)`, la llamada recursiva `self::convertir(-$numero)`
     * aborta con un `TypeError` fatal no controlado.
     */
    public function test_fallo_real_numero_a_letras_desbordamiento_php_int_min_lanza_type_error(): void
    {
        $resultado = NumeroALetras::convertir(PHP_INT_MIN);

        $this->assertIsString($resultado);
        $this->assertStringStartsWith('MENOS ', $resultado);
    }

    /**
     * FALLO REAL DETECTADO #2:
     * Archivo: app/Support/Billing/NumeroALetras.php:36
     * Entrada: NAN, INF, -INF en NumeroALetras::formatear()
     * Causa raíz: `(int) round(NAN)` y `(int) round(INF)` evalúan silenciosamente a `0` en PHP,
     * emitiendo en documentos fiscales 'CERO PESOS M/CTE' ante un resultado financiero corrupto
     * en lugar de rechazar el valor no finito.
     */
    public function test_fallo_real_numero_a_letras_formatear_convierte_nan_e_infinito_en_cero_silenciosamente(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        NumeroALetras::formatear(NAN);
    }

    /**
     * FALLO REAL DETECTADO #3:
     * Archivo: app/Support/Billing/NumeroALetras.php:71-73 y 96
     * Entrada: 101000 (apócope omitido en miles compuestos) y 1_000_000_000_000 (billón devuelve dígitos)
     * Causa raíz:
     * 1) `convertir(1)` devuelve `'UNO'`, por lo que `$miles = 101` concatena `'CIENTO UNO MIL'`
     *    en lugar de apocopar a `'CIENTO UN MIL'`. En cambio, `21` está codificado fijo como
     *    `'VEINTIÚN'`, produciendo `'VEINTIÚN PESOS M/CTE'` cuando está en posición final.
     * 2) Para `$numero >= 1_000_000_000_000`, la línea 96 ejecuta `return (string) $numero;`,
     *    filtrando dígitos numéricos crudos dentro del texto en letras.
     */
    public function test_fallo_real_numero_a_letras_billones_devuelven_digitos_y_apocope_inconsistente(): void
    {
        $unBillon = NumeroALetras::convertir(1_000_000_000_000);
        $this->assertDoesNotMatchRegularExpression(
            '/\d/',
            $unBillon,
            "NumeroALetras::convertir(1_000_000_000_000) devolvió dígitos crudos: {$unBillon}"
        );

        $cientoUnMil = NumeroALetras::convertir(101_000);
        $this->assertSame('CIENTO UN MIL', $cientoUnMil);
    }

    /**
     * Prueba valores límite enteros, negativos, cero negativo (-0.0) y redondeos de frontera
     * en NumeroALetras::convertir y NumeroALetras::formatear.
     */
    public function test_numero_a_letras_valores_limite_y_redondeo_extremo(): void
    {
        $this->assertSame('CERO', NumeroALetras::convertir(0));
        $this->assertSame('CERO PESOS M/CTE', NumeroALetras::formatear(-0.0));
        $this->assertSame('CERO PESOS M/CTE', NumeroALetras::formatear(0.499999999));
        $this->assertSame('UN PESO M/CTE', NumeroALetras::formatear(0.5));
        $this->assertSame('MENOS UN PESO M/CTE', NumeroALetras::formatear(-1.0));
        $this->assertSame('CIEN', NumeroALetras::convertir(100));
        $this->assertSame('CIENTO UNO', NumeroALetras::convertir(101));
        $this->assertSame('MIL', NumeroALetras::convertir(1_000));
        $this->assertSame('UN MILLÓN', NumeroALetras::convertir(1_000_000));
        $this->assertSame('DOS MILLONES', NumeroALetras::convertir(2_000_000));
        $this->assertSame('MIL MILLONES', NumeroALetras::convertir(1_000_000_000));
        $this->assertSame(
            'NOVECIENTOS NOVENTA Y NUEVE MIL NOVECIENTOS NOVENTA Y NUEVE MILLONES NOVECIENTOS NOVENTA Y NUEVE MIL NOVECIENTOS NOVENTA Y NUEVE',
            NumeroALetras::convertir(999_999_999_999)
        );
    }

    /**
     * Fuzzer determinista de 2,500 iteraciones contra NumeroALetras::convertir y formatear
     * en el rango soportado (-999_999_999_999 a 999_999_999_999).
     */
    public function test_fuzzer_determinista_numero_a_letras_2500_entradas(): void
    {
        mt_srand(self::SEMILLA_FUZZ);

        for ($i = 0; $i < 2500; $i++) {
            $entero = mt_rand(-999_999_999_999, 999_999_999_999);
            $texto = NumeroALetras::convertir($entero);

            $this->assertNotEmpty($texto, "Iteración {$i}: salida vacía para {$entero}");
            $this->assertDoesNotMatchRegularExpression(
                '/\s{2,}/',
                $texto,
                "Iteración {$i}: espacios dobles detectados en '{$texto}' para {$entero}"
            );
            $this->assertSame(
                 trim($texto),
                $texto,
                "Iteración {$i}: espacios laterales detectados en '{$texto}' para {$entero}"
            );
            $this->assertDoesNotMatchRegularExpression(
                '/\d/',
                $texto,
                "Iteración {$i}: quedaron dígitos sin convertir en '{$texto}' para {$entero}"
            );

            $fraccion = mt_rand(-500_000_000, 500_000_000) / 100.0;
            $formateado = NumeroALetras::formatear($fraccion, 'COP');
            $this->assertStringEndsWith(' M/CTE', $formateado);
        }
    }

    // =========================================================================
    // 2. AUDITORÍA ADVERSARIAL: OwnCompany y SystemConfiguration
    // =========================================================================

    /**
     * FALLO REAL DETECTADO #4:
     * Archivo: app/Utils/OwnCompany.php:34 y 39
     * Entrada: 'TRANSPORTES.SIN.BARRERAS S.A.S.' y 'EMPRESA A Y B S.A.S.'
     * Causa raíz:
     * 1) La línea 34 ejecuta `str_replace('.', '', $normalizado)` ANTES de separar tokens,
     *    pegando palabras separadas por punto ('TRANSPORTES.SIN.BARRERAS' -> 'TRANSPORTESSINBARRERAS').
     * 2) La línea 39 aplica `/(?<![A-Z0-9])[A-Z](?: [A-Z](?![A-Z0-9]))+/` que colapsa cualquier
     *    secuencia de letras sueltas, fusionando conjunciones e iniciales ('EMPRESA A Y B' -> 'EMPRESA AYB')
     *    y fusionando siglas contiguas separadas por espacio ('EMPRESA S A S E S P' -> 'EMPRESA SASESP')
     *    de modo distinto a cuando llevan puntos ('EMPRESA S.A.S. E.S.P.' -> 'EMPRESA SAS ESP').
     */
    public function test_fallo_real_own_company_colisiona_siglas_y_palabras_con_puntos(): void
    {
        $conPuntos = OwnCompany::normalizarNombre('EMPRESA S.A.S. E.S.P.');
        $conEspacios = OwnCompany::normalizarNombre('EMPRESA S A S E S P');

        $this->assertSame(
            $conPuntos,
            $conEspacios,
            "Inconsistencia en normalización de siglas compuestas: '{$conPuntos}' vs '{$conEspacios}'"
        );
    }

    /**
     * FALLO REAL DETECTADO #5:
     * Archivo: app/Models/SystemConfiguration.php:196-226
     * Entrada: Objeto JSON asociativo `'{"malicioso":"Empresa Fantasma"}'` o escalar `'12345'`
     * Causa raíz: `setOwnCompanyNamesAttribute` decodifica con `json_decode($value, true)` y verifica
     * `is_array($decodificado)`. Un objeto JSON `{...}` en PHP se convierte en array asociativo y se
     * acepta silenciosamente extrayendo sus valores. Si un elemento dentro del array es un subarray
     * anidado `[['EMPRESA']]`, `(string) $n` lanza `ErrorException: Array to string conversion`.
     */
    public function test_fallo_real_system_configuration_own_company_names_con_arreglo_anidado_lanza_error(): void
    {
        $config = new SystemConfiguration();
        $config->own_company_names = [['TRANSPORTES SIN BARRERAS'], ['OTRA EMPRESA']];

        $this->assertIsArray($config->own_company_names);
    }

    /**
     * Prueba entradas adversariales Unicode, acentos combinados NFD, caracteres invisibles,
     * inyecciones SQL/XSS y bytes nulos contra OwnCompany::normalizarNombre.
     */
    #[DataProvider('proveedorEntradasAdversarialesOwnCompany')]
    public function test_own_company_normalizar_nombre_entradas_adversariales(?string $entrada): void
    {
        $resultado = OwnCompany::normalizarNombre($entrada);

        $this->assertIsString($resultado);
        $this->assertDoesNotMatchRegularExpression('/[^A-Z0-9 ]/', $resultado);
        $this->assertSame(trim($resultado), $resultado);
        $this->assertDoesNotMatchRegularExpression('/\s{2,}/', $resultado);
    }

    public static function proveedorEntradasAdversarialesOwnCompany(): array
    {
        return [
            'nulo' => [null],
            'vacio' => [''],
            'solo_espacios_y_tabs' => ["   \t\n\r  "],
            'bytes_nulos' => ["TRANSPORTES\0SIN\0BARRERAS\0S.A.S."],
            'acentos_combinados_nfd' => ["CA\u{0303}O\u{0301}N DEL COMBEIMA S.A.S."],
            'emojis_y_simbolos' => ['🚛 TRANSPORTES 🚀 SIN BARRERAS © ® ™ S.A.S.'],
            'inyeccion_sql' => ["'; DROP TABLE vehicles; -- S.A.S."],
            'payload_xss' => ['<script>alert("XSS")</script> S. A. S.'],
            'zero_width_spaces' => ["TRANS\u{200B}PORTES\u{FEFF} SIN BARRERAS"],
            'cadena_megalarga' => [str_repeat('TRANSPORTES S. A. S. ', 500)],
        ];
    }

    /**
     * Fuzzer determinista de 1,500 iteraciones sobre OwnCompany::normalizarNombre y SystemConfiguration.
     */
    public function test_fuzzer_determinista_own_company_y_system_configuration_1500_iteraciones(): void
    {
        mt_srand(self::SEMILLA_FUZZ);
        $alfabeto = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789 .,-_/\0\t\náéíóúÁÉÍÓÚñÑüÜ🚛";
        $lenAlfabeto = mb_strlen($alfabeto, 'UTF-8');

        $nombresFuzz = [];
        for ($i = 0; $i < 1500; $i++) {
            $longitud = mt_rand(0, 80);
            $cadena = '';
            for ($j = 0; $j < $longitud; $j++) {
                $idx = mt_rand(0, $lenAlfabeto - 1);
                $cadena .= mb_substr($alfabeto, $idx, 1, 'UTF-8');
            }

            $normalizado = OwnCompany::normalizarNombre($cadena);
            // Propiedad de idempotencia: normalizar dos veces debe dar exactamente el mismo resultado
            $this->assertSame(
                $normalizado,
                OwnCompany::normalizarNombre($normalizado),
                "Violación de idempotencia en iteración {$i} para entrada: " . bin2hex($cadena)
            );
            $nombresFuzz[] = $cadena;
        }

        $config = new SystemConfiguration();
        $config->own_company_names = $nombresFuzz;
        $guardados = $config->own_company_names;

        $this->assertIsArray($guardados);
        $this->assertSame(array_values(array_unique($guardados)), $guardados);
    }

    // =========================================================================
    // 3. AUDITORÍA ADVERSARIAL: DriverLocationDailyStat::indicesParaTope
    // =========================================================================

    /**
     * FALLO REAL DETECTADO #6:
     * Archivo: app/Models/DriverLocationDailyStat.php:83
     * Entrada: DriverLocationDailyStat::indicesParaTope(total: 0, tope: 300)
     * Causa raíz: Cuando `$total === 0`, la condición `$total <= $tope` es verdadera y ejecuta
     * `range(0, $total - 1)` -> `range(0, -1)`. En PHP, `range(0, -1)` genera `[0, -1]` en lugar
     * de un arreglo vacío `[]`, devolviendo dos índices inválidos (incluyendo `-1`) para una lista vacía.
     */
    public function test_fallo_real_indices_para_tope_con_total_cero_devuelve_indice_negativo(): void
    {
        $indices = DriverLocationDailyStat::indicesParaTope(0, 300);

        $this->assertSame(
            [],
            $indices,
            'Para total=0, indicesParaTope debe devolver [] y no ' . json_encode($indices)
        );
    }

    /**
     * FALLO REAL DETECTADO #7:
     * Archivo: app/Models/DriverLocationDailyStat.php:87
     * Entrada: DriverLocationDailyStat::indicesParaTope(total: 10, tope: 1)
     * Causa raíz: Cuando `$tope === 1` y `$total > 1`, la línea 87 calcula
     * `$paso = ($total - 1) / ($tope - 1)` -> `9 / 0`, lanzando `DivisionByZeroError` fatal.
     */
    public function test_fallo_real_indices_para_tope_con_tope_uno_lanza_division_by_zero(): void
    {
        $indices = DriverLocationDailyStat::indicesParaTope(10, 1);

        $this->assertNotEmpty($indices);
        $this->assertLessThanOrEqual(1, count($indices));
    }

    /**
     * Fuzzer determinista de muestreo de índices para verificar invariantes en rangos válidos.
     */
    public function test_fuzzer_determinista_indices_para_tope_1000_combinaciones(): void
    {
        mt_srand(self::SEMILLA_FUZZ);

        for ($i = 0; $i < 1000; $i++) {
            $total = mt_rand(2, 10_000);
            $tope = mt_rand(2, 500);

            $indices = DriverLocationDailyStat::indicesParaTope($total, $tope);

            $this->assertNotEmpty($indices);
            $this->assertSame(0, $indices[0], "Iteración {$i}: siempre debe incluir el primer punto (0)");
            $this->assertSame(
                $total - 1,
                $indices[count($indices) - 1],
                "Iteración {$i}: siempre debe incluir el último punto ({$total}-1)"
            );
            $this->assertLessThanOrEqual(
                min($total, $tope),
                count($indices),
                "Iteración {$i}: nunca debe superar el tope {$tope}"
            );
        }
    }

    // =========================================================================
    // 4. AUDITORÍA ADVERSARIAL: LicenseService (app/Services/LicenseService.php)
    // =========================================================================

    /**
     * FALLO REAL DETECTADO #8:
     * Archivo: app/Services/LicenseService.php:113-117 y 144
     * Entrada: Base64 de un escalar JSON truthy, p. ej. `base64_encode('"licencia-falsa"')` ('ImxpY2VuY2lhLWZhbHNhIg==')
     *          o `base64_encode('12345')` ('MTIzNDU=') o `base64_encode('true')` ('dHJ1ZQ==').
     * Causa raíz:
     * 1) `json_decode($jsonPayload, true)` devuelve `'licencia-falsa'` (string) con `JSON_ERROR_NONE`.
     * 2) La guardia `if (!$decoded || json_last_error() !== JSON_ERROR_NONE)` pasa porque el string no es vacío.
     * 3) En la línea 117 se invoca `Arr::except($decoded, ['sig'])`, que exige `array` y lanza `TypeError`.
     * 4) El bloque `catch (Exception $e)` de la línea 144 NO captura `TypeError` (que implementa `\Error`,
     *    no `\Exception`), tumbando el proceso PHP.
     */
    #[DataProvider('proveedorPayloadsEscalaresLicenseService')]
    public function test_fallo_real_license_service_verify_key_con_json_escalar_lanza_type_error_no_capturado(string $claveMaliciosa): void
    {
        Config::set('app.license_hmac_secret', 'clave-secreta-auditoria-2026');
        $servicio = new LicenseService();

        $resultado = $servicio->verifyKey($claveMaliciosa);

        $this->assertNull($resultado);
    }

    public static function proveedorPayloadsEscalaresLicenseService(): array
    {
        return [
            'json_string_truthy' => [base64_encode('"licencia-falsa"')],
            'json_entero_positivo' => [base64_encode('999999')],
            'json_booleano_true' => [base64_encode('true')],
            'json_float_positivo' => [base64_encode('3.14159')],
        ];
    }

    /**
     * FALLO REAL DETECTADO #9:
     * Archivo: app/Services/LicenseService.php:129 y 144
     * Entrada: Token de licencia con firma HMAC válida pero `expires_at` de tipo array o entero.
     * Causa raíz: `Carbon::createFromFormat('Y-m-d H:i:s', $decoded['expires_at'])` recibe un `array` o `int`
     * y lanza `TypeError`, que escapa del `catch (Exception $e)`.
     */
    public function test_fallo_real_license_service_expires_at_no_string_lanza_type_error(): void
    {
        $secreto = 'clave-secreta-auditoria-2026';
        Config::set('app.license_hmac_secret', $secreto);

        $datos = [
            'company_uuid' => '00000000-0000-4000-8000-000000000001',
            'expires_at' => ['inyeccion' => '2099-12-31 23:59:59'],
            'permissions' => [],
            'iat' => time(),
            'type' => 'hybrid_license_v1',
        ];
        $datos['sig'] = hash_hmac('sha256', json_encode($datos), $secreto);
        $token = rtrim(strtr(base64_encode(json_encode($datos)), '+/', '-_'), '=');

        $servicio = new LicenseService();
        $resultado = $servicio->verifyKey($token);

        $this->assertNull($resultado);
    }

    /**
     * Fuzzer determinista de 1,000 tokens corruptos/mutados contra LicenseService::verifyKey.
     */
    public function test_fuzzer_determinista_license_service_tokens_corruptos_1000_iteraciones(): void
    {
        mt_srand(self::SEMILLA_FUZZ);
        $secreto = 'clave-secreta-fuzzing-2026';
        Config::set('app.license_hmac_secret', $secreto);

        $servicio = new LicenseService();
        $generada = $servicio->generateKey([
            'company_uuid' => '',
            'expiry_date' => '2030-12-31',
            'permissions' => [],
        ]);
        $tokenValido = $generada['license_key'];

        for ($i = 0; $i < 1000; $i++) {
            // Mutar bits/caracteres del token válido
            $chars = str_split($tokenValido);
            $pos = mt_rand(0, count($chars) - 1);
            $chars[$pos] = $chars[$pos] === 'A' ? 'B' : 'A';
            $tokenMutado = implode('', $chars);

            $res = $servicio->verifyKey($tokenMutado);
            $this->assertNull(
                $res,
                "Iteración {$i}: Un token con firma mutada en posición {$pos} fue aceptado como válido"
            );
        }
    }

    // =========================================================================
    // 5. AUDITORÍA ADVERSARIAL: ThirdParty y DriverLicense (normalización y licenciaActual)
    // =========================================================================

    /**
     * FALLO REAL DETECTADO #10:
     * Archivo: app/Models/ThirdParty.php:212-222
     * Entrada: Tercero con una licencia en estado 'ACTIVA' pero con fecha de vencimiento en el pasado (p. ej. 2019-01-01)
     *          y otra licencia con estado 'VENCIDA' pero con fecha más reciente (2025-01-01).
     * Causa raíz: `ThirdParty::licenciaActual()` filtra por `$esActiva = in_array($estado, ['ACTIVA', 'ACTIVE', '1', 'TRUE'])`
     * sin verificar si `expiration_date` ya expiró (a diferencia de `DriverLicenseService::esVigente()`).
     */
    public function test_third_party_licencia_actual_colisiones_y_estados_extremos(): void
    {
        $tercero = new ThirdParty();

        // Caso 1: Empate exacto de fechas de expiración y de IDs, con estados en formatos heterogéneos
        $lic1 = new DriverLicense();
        $lic1->id = 10;
        $lic1->license_number = 'LIC-BOOL-TRUE';
        $lic1->status = ' true ';
        $lic1->expiration_date = '2030-06-15';

        $lic2 = new DriverLicense();
        $lic2->id = 20;
        $lic2->license_number = 'LIC-NUM-1';
        $lic2->status = 1;
        $lic2->expiration_date = '2030-06-15';

        $lic3 = new DriverLicense();
        $lic3->id = 999;
        $lic3->license_number = 'LIC-INACTIVA-FUTURA';
        $lic3->status = 'SUSPENDIDA';
        $lic3->expiration_date = '2099-12-31';

        $tercero->setRelation('driverLicenses', new EloquentCollection([$lic1, $lic2, $lic3]));

        $elegida = $tercero->licenciaActual();
        $this->assertNotNull($elegida);
        $this->assertSame('LIC-NUM-1', $elegida->license_number);
    }

    /**
     * Prueba normalización de mayúsculas en ThirdParty ante Unicode extremo, caracteres de control,
     * payloads XSS/SQLi y tipos no string.
     */
    public function test_third_party_normalizacion_mayusculas_unicode_y_tipos_adversariales(): void
    {
        $tercero = new ThirdParty([
            'first_name' => "   josé   maría   ángel\t\n  ",
            'last_name' => '  ñáñez   pérez   güemes ',
            'company_name' => '  transportes   el   cóndor   s.a.s.  ',
            'trade_name' => '   ',
            'email' => '  ConDucTor@Ejemplo.COM  ',
        ]);

        $this->assertSame('JOSÉ MARÍA ÁNGEL', $tercero->first_name);
        $this->assertSame('ÑÁÑEZ PÉREZ GÜEMES', $tercero->last_name);
        $this->assertSame('TRANSPORTES EL CÓNDOR S.A.S.', $tercero->company_name);
        $this->assertSame('', $tercero->trade_name);
        // El correo NO está en CAMPOS_EN_MAYUSCULAS
        $this->assertSame('  ConDucTor@Ejemplo.COM  ', $tercero->email);
    }

    /**
     * Fuzzer determinista de 500 lotes de licencias de conducción sobre ThirdParty::licenciaActual.
     */
    public function test_fuzzer_determinista_licencia_actual_conductor_500_lotes(): void
    {
        mt_srand(self::SEMILLA_FUZZ);
        $estados = ['ACTIVA', 'ACTIVE', '1', 'TRUE', 'VENCIDA', 'INACTIVA', '0', 'FALSE', '', null, '  activa  '];

        for ($lote = 0; $lote < 500; $lote++) {
            $cantidad = mt_rand(0, 12);
            $licencias = [];

            for ($j = 0; $j < $cantidad; $j++) {
                $lic = new DriverLicense();
                $lic->id = mt_rand(1, 50);
                $lic->license_number = "LIC-{$lote}-{$j}";
                $lic->status = $estados[mt_rand(0, count($estados) - 1)];
                $tieneFecha = mt_rand(0, 4) > 0;
                $lic->expiration_date = $tieneFecha
                    ? sprintf('%04d-%02d-%02d', mt_rand(2000, 2040), mt_rand(1, 12), mt_rand(1, 28))
                    : null;
                $licencias[] = $lic;
            }

            $tercero = new ThirdParty();
            $tercero->setRelation('driverLicenses', new EloquentCollection($licencias));

            $elegida = $tercero->licenciaActual();
            if ($cantidad === 0) {
                $this->assertNull($elegida);
            } else {
                $this->assertInstanceOf(DriverLicense::class, $elegida);
            }
        }
    }

    // =========================================================================
    // 6. AUDITORÍA ADVERSARIAL: LocationTrackingService y RouteMapService
    // =========================================================================

    /**
     * FALLO REAL DETECTADO #11:
     * Archivo: app/Services/Pdf/RouteMapService.php:56
     * Entrada: Puntos GPS reales sobre la línea del Ecuador (`latitude = 0.0`, `longitude = -76.5`, sur de Colombia)
     *          o sobre el Meridiano de Greenwich (`latitude = 51.4778`, `longitude = 0.0`).
     * Causa raíz: El filtro `->filter(fn ($c) => $c[0] !== 0.0 && $c[1] !== 0.0)` utiliza `&&` en lugar de `||`.
     * Cualquier coordenada donde UNO solo de los ejes sea `0.0` es descartada silenciosamente como si fuera `(0, 0)`,
     * haciendo que `RouteMapService::generar` devuelva `null` (sin mapa) cuando un vehículo cruza el Ecuador.
     */
    public function test_fallo_real_route_map_service_descarta_puntos_en_la_linea_del_ecuador(): void
    {
        Config::set('services.static_map.provider', 'none');
        $servicio = new RouteMapService();

        $puntosEcuador = collect([
            (object) ['latitude' => 0.0, 'longitude' => -76.5200, 'speed' => 45, 'recorded_at' => '2026-11-10 08:00:00'],
            (object) ['latitude' => 0.0, 'longitude' => -76.5500, 'speed' => 50, 'recorded_at' => '2026-11-10 08:10:00'],
        ]);

        $resultado = $servicio->generar($puntosEcuador);

        $this->assertNotNull(
            $resultado,
            'RouteMapService::generar descartó puntos válidos sobre la línea del Ecuador (latitude = 0.0)'
        );
    }

    /**
     * FALLO REAL DETECTADO #12:
     * Archivo: app/Services/Pdf/RouteMapService.php:43 y 136-137
     * Entrada: Puntos con `recorded_at` corrupto ('fecha-invalida').
     * Causa raíz: En `RouteMapService::generar()`, la llamada `$stats = $this->calcularStats($points)`
     * en la línea 43 ocurre FUERA del bloque `try { ... } catch (\Throwable $e)` de la línea 73.
     * `Carbon::parse('fecha-invalida')` lanza `InvalidFormatException` sin caer al PNG de respaldo.
     */
    public function test_fallo_real_route_map_service_recorded_at_invalido_lanza_excepcion_fuera_del_try_catch(): void
    {
        Config::set('services.static_map.provider', 'none');
        $servicio = new RouteMapService();

        $puntosCorruptos = collect([
            (object) ['latitude' => 4.6097, 'longitude' => -74.0817, 'speed' => 30, 'recorded_at' => 'no-es-una-fecha'],
            (object) ['latitude' => 4.6200, 'longitude' => -74.0900, 'speed' => 40, 'recorded_at' => 'tampoco-es-fecha'],
        ]);

        $resultado = $servicio->generar($puntosCorruptos);

        $this->assertIsArray($resultado);
        $this->assertArrayHasKey('image_base64', $resultado);
    }

    /**
     * Prueba geométrica y adversarial sobre haversineDistance y pointInPolygon (vía reflexión)
     * en LocationTrackingService.
     */
    public function test_location_tracking_haversine_y_point_in_polygon_casos_extremos(): void
    {
        $refHaversine = new ReflectionMethod(LocationTrackingService::class, 'haversineDistance');
        $refPolygon = new ReflectionMethod(LocationTrackingService::class, 'pointInPolygon');
        $instancia = (new \ReflectionClass(LocationTrackingService::class))->newInstanceWithoutConstructor();

        // Distancia idéntica = 0.0
        $this->assertSame(0.0, $refHaversine->invoke($instancia, 4.6097, -74.0817, 4.6097, -74.0817));

        // Puntos antipodales extremos (Polo Norte a Polo Sur) ~ 20,015 km
        $distPolos = $refHaversine->invoke($instancia, 90.0, 0.0, -90.0, 0.0);
        $this->assertGreaterThan(20_000_000.0, $distPolos);
        $this->assertLessThan(20_040_000.0, $distPolos);

        // Polígono degenerado (< 3 vértices) -> false
        $this->assertFalse($refPolygon->invoke($instancia, 4.6, -74.0, []));
        $this->assertFalse($refPolygon->invoke($instancia, 4.6, -74.0, [['lat' => 4.0, 'lng' => -74.0], ['lat' => 5.0, 'lng' => -75.0]]));

        // Polígono cuadrado alrededor de Bogotá
        $cuadrado = [
            ['lat' => 4.5, 'lng' => -74.2],
            ['lat' => 4.5, 'lng' => -74.0],
            ['lat' => 4.8, 'lng' => -74.0],
            ['lat' => 4.8, 'lng' => -74.2],
        ];
        $this->assertTrue($refPolygon->invoke($instancia, 4.65, -74.1, $cuadrado));
        $this->assertFalse($refPolygon->invoke($instancia, 6.24, -75.58, $cuadrado));
    }

    // =========================================================================
    // 7. AUDITORÍA ADVERSARIAL: CalculaTotales (Facturación Comercial)
    // =========================================================================

    /**
     * FALLO REAL DETECTADO #13:
     * Archivo: app/Services/Billing/CalculaTotales.php:21-32
     * Entrada: Línea de factura con `discount_amount` mayor que `quantity * unit_price`, o `quantity < 0`.
     * Causa raíz: `$base = ($qty * $price) - $discount;` no aplica piso en `0.0` ni rechaza descuentos
     * superiores al valor bruto, generando bases imponibles negativas, IVA negativo y totales de factura negativos.
     */
    public function test_fallo_real_calcula_totales_permite_descuento_mayor_al_subtotal_generando_total_negativo(): void
    {
        $linea = CalculaTotales::calcularLinea([
            'description' => 'Servicio de transporte',
            'quantity' => 1,
            'unit_price' => 100_000,
            'discount_amount' => 250_000,
            'tax_rate' => 19,
        ]);

        $this->assertGreaterThanOrEqual(
            0.0,
            $linea['total'],
            "CalculaTotales permitió un total de línea negativo ({$linea['total']}) cuando el descuento supera el subtotal"
        );
    }

    /**
     * Fuzzer determinista de 1,500 líneas financieras contra CalculaTotales.
     */
    public function test_fuzzer_determinista_calcula_totales_1500_lineas(): void
    {
        mt_srand(self::SEMILLA_FUZZ);

        $lote = [];
        for ($i = 0; $i < 1500; $i++) {
            $qty = mt_rand(1, 500) / 10.0;
            $price = mt_rand(100, 10_000_000) / 100.0;
            $bruto = $qty * $price;
            $discount = (mt_rand(0, 30) / 100.0) * $bruto;
            $taxRate = [0, 5, 19][mt_rand(0, 2)];

            $calc = CalculaTotales::calcularLinea([
                'description' => "Ítem Fuzz #{$i}",
                'quantity' => $qty,
                'unit_price' => $price,
                'discount_amount' => $discount,
                'tax_rate' => $taxRate,
            ]);
            $calc['descuento'] = round($discount, 2);
            $calc['retencion'] = 0.0;
            $lote[] = $calc;
        }

        $lineas = $lote;
        $totales = CalculaTotales::sumarTotales($lineas);
        $this->assertCount(1500, $lineas);
        $this->assertEqualsWithDelta(
            round($totales['subtotal'] + $totales['impuesto'] - $totales['retencion'], 2),
            $totales['total'],
            0.05
        );
    }

    // =========================================================================
    // 8. AUDITORÍA ADVERSARIAL: ServiceDeliveryControlSheet y PcpEvidence
    // =========================================================================

    /**
     * Verifica que los accesores de ServiceDeliveryControlSheet NUNCA disparen lazy loading (Principio #6 / SPEC-012)
     * y que los estados de disponibilidad / cierre soporten entradas límite y corruptas.
     */
    public function test_service_delivery_control_sheet_y_pcp_evidence_invariantes_adversariales(): void
    {
        $motivos = ['Mantenimiento preventivo', 'Día sin servicio por cliente', 'Pico y placa'];
        foreach ($motivos as $etiqueta) {
            $hoja = new ServiceDeliveryControlSheet([
                'day_kind' => ServiceDeliveryControlSheet::DIA_DISPONIBILIDAD,
                'availability_reason' => "  {$etiqueta}  ",
                'is_active' => true,
                'exception_reason' => 'Justificación de auditoría',
            ]);

            $this->assertTrue($hoja->esDisponibilidad());
            $this->assertSame($etiqueta, $hoja->motivoDisponibilidad());
            $this->assertSame("VEHÍCULO EN DISPONIBILIDAD — {$etiqueta}", $hoja->textoDisponibilidad());
            $this->assertFalse($hoja->esCerradaConExcepcion());
            // Sin relaciones cargadas, los accesores deben degradar a null sin hacer consultas
            $this->assertNull($hoja->vehicle_license_plate);
            $this->assertNull($hoja->driver_name);
        }

        // Motivo vacío o de solo espacios en availability_reason
        $hojaSinMotivo = new ServiceDeliveryControlSheet([
            'day_kind' => ServiceDeliveryControlSheet::DIA_DISPONIBILIDAD,
            'availability_reason' => "   \t\n  ",
        ]);
        $this->assertNull($hojaSinMotivo->motivoDisponibilidad());
        $this->assertSame('VEHÍCULO EN DISPONIBILIDAD', $hojaSinMotivo->textoDisponibilidad());

        // Hoja cerrada con excepción aprobada
        $hojaCerradaConExcepcion = new ServiceDeliveryControlSheet([
            'day_kind' => ServiceDeliveryControlSheet::DIA_OPERACION,
            'is_active' => false,
            'exception_reason' => 'Autorizado por gerencia',
        ]);
        $this->assertTrue($hojaCerradaConExcepcion->esCerradaConExcepcion());
    }
}
