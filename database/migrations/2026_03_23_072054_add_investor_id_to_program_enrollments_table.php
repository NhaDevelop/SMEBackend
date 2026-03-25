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
        Schema::table('program_enrollments', function (Blueprint $table) {
            // Drop existing foreign key before modifying the column
            $table->dropForeign(['sme_id']);
            
            // Make sme_id nullable
            $table->unsignedBigInteger('sme_id')->nullable()->change();
            
            // Re-add foreign key for sme_id
            $table->foreign('sme_id')->references('id')->on('sme_profiles')->onDelete('cascade');

            // Add new investor_id column
            $table->unsignedBigInteger('investor_id')->nullable()->after('sme_id');
            $table->foreign('investor_id')->references('id')->on('investor_profiles')->onDelete('cascade');

            // Prevent duplicate investor enrollments
            $table->unique(['program_id', 'investor_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('program_enrollments', function (Blueprint $table) {
            // Drop investor_id unique and foreign keys
            $table->dropUnique(['program_id', 'investor_id']);
            $table->dropForeign(['investor_id']);
            $table->dropColumn('investor_id');

            // Revert sme_id to NOT NULL
            $table->dropForeign(['sme_id']);
            $table->unsignedBigInteger('sme_id')->nullable(false)->change();
            $table->foreign('sme_id')->references('id')->on('sme_profiles')->onDelete('cascade');
        });
    }
};
