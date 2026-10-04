<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Vendor\LaravelAuthentication\Support\AuthenticationConfig;

return new class extends Migration
{
    public function up(): void
    {
        $table = AuthenticationConfig::tableName('two_factor', 'authentication_two_factors');

        if (Schema::hasTable($table) && !Schema::hasColumn($table, 'last_used_timestep')) {
            Schema::table($table, function (Blueprint $table): void {
                $table->bigInteger('last_used_timestep')->nullable()->after('confirmed_at');
            });
        }
    }

    public function down(): void
    {
        $table = AuthenticationConfig::tableName('two_factor', 'authentication_two_factors');

        if (Schema::hasTable($table) && Schema::hasColumn($table, 'last_used_timestep')) {
            Schema::table($table, function (Blueprint $table): void {
                $table->dropColumn('last_used_timestep');
            });
        }
    }
};
