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
        $table = AuthenticationConfig::tableName('devices', 'authentication_devices');

        if (!Schema::hasTable($table)) {
            Schema::create($table, function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('device_fingerprint', 64)->index();
                $table->string('ip_address', 45);
                $table->text('user_agent')->nullable();
                $table->string('device_name', 128)->nullable();
                $table->string('platform', 64)->nullable();
                $table->string('browser', 64)->nullable();
                $table->string('location', 128)->nullable();
                $table->boolean('is_trusted')->default(false)->index();
                $table->timestamp('trusted_until')->nullable()->index();
                $table->timestamp('last_seen_at')->useCurrent()->index();
                $table->timestamps();

                $table->unique(['user_id', 'device_fingerprint']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $table = AuthenticationConfig::tableName('devices', 'authentication_devices');
        Schema::dropIfExists($table);
    }
};
