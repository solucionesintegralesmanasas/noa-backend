<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dev;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\Pdf\PdfService;
use App\Support\Billing\QrDian;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Controlador temporal para iterar prototipos visuales de PDFs en esta rama.
 */
class PdfPrototypeController extends Controller
{
    private const VARIANTES = [
        'a' => [
            'vista' => 'pdf.prototypes.proto-a-institucional',
            'nombre' => 'Prototipo A - Institucional SGC Refinado',
            'archivo' => 'prototipo-a-institucional.pdf',
        ],
        'b' => [
            'vista' => 'pdf.prototypes.proto-b-minimal-impresion',
            'nombre' => 'Prototipo B - Tecnico Minimalista (Ahorro de Tinta)',
            'archivo' => 'prototipo-b-minimal-impresion.pdf',
        ],
        'c' => [
            'vista' => 'pdf.prototypes.proto-c-moderno-modular',
            'nombre' => 'Prototipo C - Modular Moderno (Pantalla y Papel)',
            'archivo' => 'prototipo-c-moderno-modular.pdf',
        ],
    ];

    public function index(): View
    {
        return view('pdf.prototypes.index', [
            'variantes' => self::VARIANTES,
        ]);
    }

    public function show(Request $request, string $variant): Response
    {
        $clave = strtolower($variant);
        if (! isset(self::VARIANTES[$clave])) {
            abort(404, 'Prototipo no encontrado. Use a, b o c.');
        }

        $orientacion = $request->query('orientacion') === 'landscape' ? 'landscape' : 'portrait';
        $filas = max(1, min(40, (int) $request->query('filas', 22)));
        $descargar = $request->boolean('download', false);

        $pdf = $this->renderizarPdf($clave, $orientacion, $filas);
        $nombre = self::VARIANTES[$clave]['archivo'];

        return $descargar ? $pdf->download($nombre) : $pdf->stream($nombre);
    }

    /**
     * Genera el objeto DomPDF listo para stream, descarga o guardado en disco.
     */
    public function renderizarPdf(string $clave, string $orientacion = 'portrait', int $cantidadFilas = 22): \Barryvdh\DomPDF\PDF
    {
        $vista = self::VARIANTES[$clave]['vista'];
        $datos = $this->datosMuestra($orientacion, $cantidadFilas);

        global $_dompdf_warnings;
        $_dompdf_warnings = [];

        $pdf = Pdf::loadView($vista, $datos);
        $pdf->setPaper('letter', $orientacion);
        $pdf->setOption([
            'dpi' => 120,
            'isFontSubsettingEnabled' => true,
            'isRemoteEnabled' => false,
        ]);
        $pdf->setWarnings((bool) config('app.debug', false));

        app(PdfService::class)->sellarPaginado($pdf);

        return $pdf;
    }

    /**
     * Datos realistas compartidos entre los 3 prototipos para comparar
     * legibilidad, márgenes, saltos de página y bloques de cierre en igualdad de condiciones.
     *
     * @return array<string, mixed>
     */
    public function datosMuestra(string $orientacion = 'portrait', int $cantidadFilas = 22): array
    {
        $rutas = [
            ['Base Montería — Terminal Sur — Planta Cereté', '06:00', '12:00 - 13:00', '17:30', '10:30', '45.210', '45.328', '118', 'CERRADA', 'Carlos Andrés Pérez'],
            ['Planta Cereté — Zona Franca — Montería Centro', '05:45', '11:30 - 12:30', '18:00', '11:15', '45.328', '45.462', '134', 'CERRADA', 'Carlos Andrés Pérez'],
            ['Montería Norte — Aeropuerto Los Garzones — Sede Operativa', '07:00', '12:30 - 13:15', '16:45', '09:00', '45.462', '45.540', '78', 'CERRADA', 'Luis Fernando Martínez'],
            ['Disponibilidad programada en patio — Mantenimiento preventivo menor', '08:00', '—', '12:00', '04:00', '45.540', '45.540', '0', 'OBSERVACIÓN', 'Luis Fernando Martínez'],
            ['Montería — Lorica — Coveñas (Relevo personal técnico)', '05:30', '12:00 - 13:00', '19:15', '12:45', '45.540', '45.756', '216', 'PENDIENTE', 'Carlos Andrés Pérez'],
        ];

        $filas = [];
        for ($i = 0; $i < $cantidadFilas; $i++) {
            $base = $rutas[$i % count($rutas)];
            $dia = str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT);
            $filas[] = [
                'fecha' => "{$dia}/11/2026",
                'dia_semana' => ['LUN', 'MAR', 'MIÉ', 'JUE', 'VIE', 'SÁB'][$i % 6],
                'ruta' => $base[0],
                'hora_inicio' => $base[1],
                'descanso' => $base[2],
                'hora_fin' => $base[3],
                'total_horas' => $base[4],
                'km_inicial' => $base[5],
                'km_final' => $base[6],
                'km_total' => $base[7],
                'estado' => $base[8],
                'conductor' => $base[9],
                'funcionario' => 'Ana María Gómez (C.C. 1.067.842.190)',
            ];
        }

        // Generar QR en PNG Base64 con GD (100% compatible con DomPDF sin warnings)
        $qrBase64 = QrDian::pngBase64('https://apptransportessinbarreras.com/#/validacion/PROTOTIPO-OPE-2026', 160);

        // Resolver logo real de la empresa (BD -> respaldo local para prototipo)
        $logoDataUrl = $this->resolverLogoPrototipo();
        $transporDataUrl = $this->archivoADataUrl(public_path('img/transporte.png'), 'image/png');
        $superDataUrl = $this->archivoADataUrl(public_path('img/super2.png'), 'image/png');

        $empresaNombre = 'TRANSPORTES ESPECIALES SIN BARRERAS S.A.S.';
        $siglas = collect(preg_split('/\s+/', trim($empresaNombre)))
            ->filter(fn ($w) => mb_strlen($w) > 2)
            ->take(3)
            ->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))
            ->implode('');

        return [
            'orientation' => $orientacion,
            'logo_data_url' => $logoDataUrl,
            'transpor_data_url' => $transporDataUrl,
            'super_data_url' => $superDataUrl,
            'empresa_siglas' => $siglas ?: 'NOA',
            'codigo_sgc' => 'OPE-F-006',
            'version_sgc' => '003',
            'fecha_sgc' => '17/11/2026',
            'periodo' => 'NOVIEMBRE 2026',
            'empresa_transportadora' => $empresaNombre,
            'empresa_nit' => '900.458.123-7',
            'empresa_ciudad' => 'Montería, Córdoba',
            'titulo_documento' => 'PLANILLA DE CONTROL Y CONSOLIDADO DE SERVICIOS',
            'subtitulo_documento' => 'PLAN ESTRATÉGICO DE SEGURIDAD VIAL (PESV) · SISTEMA DE GESTIÓN DE CALIDAD',
            'placa' => 'WLG-482',
            'numero_interno' => 'INT-042',
            'clase_vehiculo' => 'MICROBÚS ESPECIAL (19 PAX)',
            'sede' => 'SEDE PRINCIPAL MONTERÍA',
            'cliente' => 'CONSORCIO ENERGÉTICO DEL SINÚ S.A.S.',
            'cliente_nit' => '901.234.567-2',
            'proyecto' => 'CONTRATO TRANSPORTE PERSONAL OPERATIVO · FASE II',
            'vigencia' => '01/01/2026 — 31/12/2026',
            'responsable' => 'ANA MARÍA GÓMEZ',
            'filas' => $filas,
            'totales' => [
                'dias' => count($filas),
                'cerrados' => count(array_filter($filas, fn ($f) => $f['estado'] === 'CERRADA')),
                'horas' => '209:30',
                'km' => 2408,
                'peajes' => 18,
                'valor_peajes' => '$ 284.400',
            ],
            'observaciones' => 'Vehículo operó dentro de los límites de jornada PESV. Novedad menor registrada el día 04/11 por mantenimiento preventivo programado de frenos y revisión de luces en taller autorizado.',
            'alerta' => 'EVIDENCIA EN REVISIÓN: Algunos recorridos recientes permanecen pendientes de certificación administrativa por parte del coordinador logístico.',
            'qr_png_base64' => $qrBase64,
            'conductor_pie' => 'CARLOS ANDRÉS PÉREZ',
            'coordinador_pie' => 'ANA MARÍA GÓMEZ',
        ];
    }

    /**
     * Resuelve el logo de la empresa desde BD o archivos locales disponibles
     * para que los prototipos muestren el logo real (y solo caigan al monograma si no existe ninguno).
     */
    private function resolverLogoPrototipo(): ?string
    {
        try {
            $company = Company::withoutGlobalScopes()->first();
            $logoModel = $company ? PdfService::logoParaMarca($company) : null;
            if (is_object($logoModel) && method_exists($logoModel, 'getPath')) {
                $path = $logoModel->getPath();
                if ($path && is_file($path)) {
                    $mime = $logoModel->mime_type ?: (@mime_content_type($path) ?: 'image/png');

                    return $this->archivoADataUrl($path, $mime);
                }
            }
        } catch (\Throwable) {
            // En prototipos sin BD activa se usa el respaldo local del proyecto.
        }

        $respaldos = [
            public_path('img/logo.png') => 'image/png',
            public_path('img/logo.jpg') => 'image/jpeg',
            base_path('../noa-frontend-tsb/public/logo-sin-barreras.svg') => 'image/svg+xml',
        ];

        foreach ($respaldos as $ruta => $mime) {
            $dataUrl = $this->archivoADataUrl($ruta, $mime);
            if ($dataUrl !== null) {
                return $dataUrl;
            }
        }

        return null;
    }

    private function archivoADataUrl(string $ruta, string $mime): ?string
    {
        if (! is_file($ruta)) {
            return null;
        }

        $contenido = @file_get_contents($ruta);
        if ($contenido === false || $contenido === '') {
            return null;
        }

        return 'data:' . $mime . ';base64,' . base64_encode($contenido);
    }
}
