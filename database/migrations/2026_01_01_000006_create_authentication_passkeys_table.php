<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Vendor\LaravelAuthentication\Support\AuthenticationConfig;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $table = AuthenticationConfig::tableName('passkeys', 'authentication_passkeys');

        if (!Schema::hasTable($table)) {
            Schema::create($table, function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('name', 128)->default('Passkey');
                $table->string('credential_id', 255)->unique()->index();
                $table->text('public_key');
                $table->string('attestation_type', 32)->default('none');
                $table->string('aaguid', 64)->nullable();
                $table->unsignedBigInteger('sign_count')->default(0);
                $table->json('transports')->nullable();
                $table->timestamp('last_used_at')->nullable()->index();
                $table->timestamps();

                // Composite index for rapid lookup and user list rendering
                $table->index(['user_id', 'created_at'], 'idx_passkeys_user_created');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $table = AuthenticationConfig::tableName('passkeys', 'authentication_passkeys');
        Schema::dropIfExists($table);
    }
};
