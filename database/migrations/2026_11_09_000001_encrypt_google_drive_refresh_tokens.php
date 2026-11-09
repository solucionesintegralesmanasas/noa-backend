<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('users')
            ->whereNotNull('google_drive_refresh_token')
            ->where('google_drive_refresh_token', '!=', '')
            ->get(['id', 'google_drive_refresh_token']);

        foreach ($rows as $row) {
            $token = $row->google_drive_refresh_token;

            // Si ya está cifrado, lo dejamos como está.
            try {
                Crypt::decryptString($token);
                continue;
            } catch (\Throwable) {
                // Token en claro: se cifra.
            }

            DB::table('users')
                ->where('id', $row->id)
                ->update(['google_drive_refresh_token' => Crypt::encryptString($token)]);
        }
    }

    public function down(): void
    {
        // Sin reversión fiable: no es posible distinguir un token ya cifrado de uno en claro.
    }
};
