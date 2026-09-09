<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id()->comment('Identificador único autoincremental');
            $table->string('name')->comment('Nombre del usuario');
            $table->char('uuid', 36)->unique()->comment('Identificador único universal');
            $table->string('email')->unique()->comment('Correo electrónico único');
            $table->string('user_name')->nullable()->comment('Nombre de usuario, puede ser único si aplica');
            $table->timestamp('email_verified_at')->nullable()->comment('Fecha de verificación de correo');
            $table->string('password')->comment('Contraseña');
            $table->string('verification_code')->nullable()->comment('Código de verificación');
            $table->timestamp('verification_code_expires_at')->nullable()->comment('Expiración del código de verificación');
            $table->timestamp('last_login_at')->nullable()->comment('Último inicio de sesión');
            $table->unsignedTinyInteger('failed_login_attempts')->default(0)->comment('Intentos fallidos de inicio de sesión');
            $table->timestamp('locked_until')->nullable()->comment('Fecha hasta la que el usuario está bloqueado');
            $table->string('google_id')->nullable()->comment('Identificador único de Google');
            $table->string('google_email')->nullable()->comment('Correo electrónico de Google');
            $table->string('google_drive_refresh_token')->nullable()->comment('Token de actualización de Google Drive');
            $table->tinyInteger('status')->default(1)->comment('Estado del usuario');
            $table->rememberToken()->comment('Token para recordar sesión');
            $table->timestamps();
        });

        Schema::create('company_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->char('company_uuid', 36)->comment('UUID de la empresa');
            $table->char('third_party_uuid', 36)->nullable()->comment('UUID del tercero asociado en esta empresa');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'company_uuid'], 'uq_user_company');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary()->comment('Correo electrónico único');
            $table->string('token')->comment('Token para restablecer contraseña');
            $table->timestamp('created_at')->nullable()->comment('Fecha de creación');
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary()->comment('Identificador único de sesión');
            $table->foreignId('user_id')->nullable()->index()->comment('Identificador único del usuario');
            $table->string('ip_address', 45)->nullable()->comment('Dirección IP del usuario');
            $table->text('user_agent')->nullable()->comment('Agente de usuario del usuario');
            $table->longText('payload')->comment('Payload de la sesión');
            $table->integer('last_activity')->index()->comment('Última actividad del usuario');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_user');
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
