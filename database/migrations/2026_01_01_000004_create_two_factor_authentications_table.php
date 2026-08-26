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
        $table = AuthenticationConfig::tableName('two_factor', 'authentication_two_factors');

        if (!Schema::hasTable($table)) {
            Schema::create($table, function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->unique()->index();
                $table->text('secret');
                $table->text('recovery_codes')->nullable();
                $table->timestamp('confirmed_at')->nullable()->index();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $table = AuthenticationConfig::tableName('two_factor', 'authentication_two_factors');
        Schema::dropIfExists($table);
    }
};
