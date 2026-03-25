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
        Schema::table('users', function (Blueprint $table) {
            $table->index(['role', 'status'], 'users_role_status_index');
        });

        Schema::table('sme_profiles', function (Blueprint $table) {
            $table->index('industry', 'sme_profiles_industry_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_role_status_index');
        });

        Schema::table('sme_profiles', function (Blueprint $table) {
            $table->dropIndex('sme_profiles_industry_index');
        });
    }
};
