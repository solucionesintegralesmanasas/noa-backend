<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Si el .env no se ve en una petición (Apache multihilo en Windows), la conexión por defecto debe
 * ser mysql y no una base sqlite inexistente.
 */
class ConexionPorDefectoTest extends TestCase
{
    public function test_el_respaldo_de_la_conexion_es_mysql(): void
    {
        $config = file_get_contents(dirname(__DIR__, 2).'/config/database.php');

        $this->assertMatchesRegularExpression("/'default'\s*=>\s*env\('DB_CONNECTION',\s*'mysql'\)/", $config);
    }
}
