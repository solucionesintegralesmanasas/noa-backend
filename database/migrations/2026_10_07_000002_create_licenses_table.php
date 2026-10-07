<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('licenses', function (Blueprint $table) {
            $table->uuid('company_uuid');
            $table->string('license_key', 512)->unique();
            $table->string('signature', 64);
            $table->date('expiry_date');
            $table->enum('status', ['trial', 'active', 'expired', 'suspended', 'revoked'])->default('active');
            $table->boolean('online_verification_enabled')->default(true);
            $table->string('activated_ip')->nullable();
            $table->string('activated_domain')->nullable();
            $table->integer('offline_verifications')->default(0);
            $table->integer('online_verifications')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('license_key');
            $table->index(['company_uuid', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('licenses');
    }
};