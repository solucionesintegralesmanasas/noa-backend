<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Inserta una fila rellenando las columnas obligatorias sin valor por defecto,
 * para no acoplar las pruebas a campos irrelevantes.
 */
trait InsertaFilas
{
    protected function insertar(string $tabla, array $datos): string
    {
        $uuid = $datos['uuid'] ?? (string) Str::uuid();
        $datos['uuid'] = $uuid;
        $columnas = DB::select(
            'select column_name n, data_type t, column_type ct from information_schema.columns
             where table_schema = database() and table_name = ? and is_nullable = "NO"
             and column_default is null and extra not like "%auto_increment%"',
            [$tabla]
        );
        foreach ($columnas as $c) {
            if (array_key_exists($c->n, $datos)) {
                continue;
            }
            $datos[$c->n] = match (true) {
                in_array($c->t, ['char', 'varchar'], true) => $c->n === 'email' ? Str::random(6).'@x.test' : (str_ends_with($c->n, 'uuid') ? (string) Str::uuid() : 'x'),
                in_array($c->t, ['text', 'longtext', 'mediumtext'], true) => 'x',
                $c->t === 'enum' => explode("','", trim(substr($c->ct, 5, -1), "'"))[0],
                $c->t === 'date' => '2026-09-30',
                in_array($c->t, ['datetime', 'timestamp'], true) => '2026-09-30 10:00:00',
                $c->t === 'json' => '[]',
                default => 0,
            };
        }
        Schema::disableForeignKeyConstraints();
        DB::table($tabla)->insert($datos + ['created_at' => now(), 'updated_at' => now()]);
        Schema::enableForeignKeyConstraints();

        return $uuid;
    }
}
