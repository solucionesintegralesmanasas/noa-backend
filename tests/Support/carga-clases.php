<?php

declare(strict_types=1);

/**
 * Script auxiliar de FirmasDeClasesTest.
 *
 * Carga, una a una, todas las clases de la aplicación (servicios, controladores,
 * requests, modelos, correos y traits) escribiendo el nombre ANTES de cargarla.
 *
 * Existe por una razón concreta: una declaración incompatible con la clase padre
 * (por ejemplo `ServiceProvisionContractService::update(): Model` contra
 * `BaseService::update(): bool`) es un error de compilación que PHP NO permite
 * capturar con try/catch: finaliza el proceso entero. En local eso tumbaba el
 * worker de Apache (el proxy del dev server respondía 502 Bad Gateway) y en un
 * hosting compartido devolvería un 500 en la primera petición que tocara el
 * servicio. Al vivir en un subproceso, el test puede leer el mensaje del fatal y
 * fallar indicando la clase culpable, sin que muera la suite.
 *
 * Salida por stdout: una línea ":: Clase" por clase y "::FIN" al terminar.
 * Los errores de compilación van a stderr y matan el proceso antes de "::FIN".
 */

$raiz = dirname(__DIR__, 2);

require $raiz . '/vendor/autoload.php';
$app = require $raiz . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$carpetas = ['Services', 'Http/Controllers', 'Http/Requests', 'Models', 'Mail', 'Traits'];

$base = rtrim(str_replace('\\', '/', app_path()), '/');
$contador = 0;

foreach ($carpetas as $carpeta) {
    $directorio = app_path($carpeta);

    if (! is_dir($directorio)) {
        continue;
    }

    $archivos = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directorio));

    foreach ($archivos as $archivo) {
        if (! $archivo->isFile() || $archivo->getExtension() !== 'php') {
            continue;
        }

        $ruta = str_replace('\\', '/', $archivo->getPathname());
        $clase = 'App\\' . str_replace('/', '\\', substr($ruta, strlen($base) + 1, -4));

        // Se anuncia la clase antes de cargarla: si el proceso muere, esta es la culpable.
        fwrite(STDOUT, ":: {$clase}\n");
        fflush(STDOUT);

        class_exists($clase) || interface_exists($clase) || trait_exists($clase);
        $contador++;
    }
}

fwrite(STDOUT, '::FIN ' . $contador . "\n");
fflush(STDOUT);