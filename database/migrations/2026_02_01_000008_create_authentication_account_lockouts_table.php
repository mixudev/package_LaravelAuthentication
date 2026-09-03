<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Vendor\LaravelAuthentication\Support\AuthenticationConfig;

/**
 * SEC-07 FIX: Persistent account lockout store in the database.
 *
 * Previously lockout state lived only in cache (memory/Redis), which could be
 * flushed or is not shared reliably across app servers. Moving to DB makes
 * lockout enforcement durable and consistent in multi-server deployments.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = AuthenticationConfig::tableName('account_lockouts', 'authentication_account_lockouts');

        if (!Schema::hasTable($table)) {
            Schema::create($table, function (Blueprint $table) {
                $table->id();
                // Identifier is stored masked (SecurityHelper::maskIdentifier) as with attempts.
                $table->string('user_identifier', 255)->index();
                $table->unsignedInteger('failed_attempts')->default(0);
                $table->timestamp('locked_until')->nullable()->index();
                $table->timestamp('last_failure_at')->nullable();
                $table->timestamps();

                $table->unique('user_identifier', 'auth_lockouts_user_unique');
            });
        }
    }

    public function down(): void
    {
        $table = AuthenticationConfig::tableName('account_lockouts', 'authentication_account_lockouts');
        Schema::dropIfExists($table);
    }
};
