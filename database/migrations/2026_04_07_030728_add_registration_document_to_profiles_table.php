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
        Schema::table('sme_profiles', function (Blueprint $table) {
            $table->string('registration_document')->nullable()->after('address');
        });
        Schema::table('investor_profiles', function (Blueprint $table) {
            $table->string('registration_document')->nullable()->after('address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sme_profiles', function (Blueprint $table) {
            $table->dropColumn('registration_document');
        });
        Schema::table('investor_profiles', function (Blueprint $table) {
            $table->dropColumn('registration_document');
        });
    }
};
