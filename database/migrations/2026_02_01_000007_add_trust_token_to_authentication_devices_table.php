<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Vendor\LaravelAuthentication\Support\AuthenticationConfig;

/**
 * SEC-04 FIX: Add server-side trust token to authentication_devices so a stolen
 * 2FA trust cookie can be revoked server-side instead of being re-usable for the
 * full duration with no way to invalidate it.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = AuthenticationConfig::tableName('devices', 'authentication_devices');

        if (Schema::hasTable($table)) {
            Schema::table($table, function (Blueprint $table) {
                $table->string('trust_token_hash', 64)->nullable()->after('location');
                $table->index('trust_token_hash', 'auth_devices_trust_token_hash_idx');
            });
        }
    }

    public function down(): void
    {
        $devicesTable = AuthenticationConfig::tableName('devices', 'authentication_devices');

        if (Schema::hasTable($devicesTable) && Schema::hasColumn($devicesTable, 'trust_token_hash')) {
            Schema::table($devicesTable, function (Blueprint $table) {
                $table->dropIndex('auth_devices_trust_token_hash_idx');
                $table->dropColumn('trust_token_hash');
            });
        }
    }
};
