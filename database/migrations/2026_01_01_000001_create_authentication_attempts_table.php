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
        $table = AuthenticationConfig::tableName('attempts', 'authentication_attempts');

        if (!Schema::hasTable($table)) {
            Schema::create($table, function (Blueprint $table) {
                $table->id();
                $table->string('identifier', 255)->index();
                $table->string('ip_address', 45)->index();
                $table->text('user_agent')->nullable();
                $table->string('status', 32)->index(); // SUCCESS, FAILED, THROTTLED, LOCKED
                $table->string('failure_reason', 64)->nullable();
                $table->string('strategy', 64)->nullable();
                $table->string('channel', 32)->default('web'); // web, api, cli
                $table->timestamp('attempted_at')->useCurrent()->index();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $table = AuthenticationConfig::tableName('attempts', 'authentication_attempts');
        Schema::dropIfExists($table);
    }
};
