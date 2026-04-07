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
            $indexes = collect(Schema::getIndexes('users'))->pluck('name');
            if (!$indexes->contains('users_role_status_index')) {
                $table->index(['role', 'status']);
            }
        });

        Schema::table('sme_profiles', function (Blueprint $table) {
            $indexes = collect(Schema::getIndexes('sme_profiles'))->pluck('name');
            if (!$indexes->contains('sme_profiles_user_id_index') && !$indexes->contains('sme_profiles_user_id_foreign')) {
                $table->index('user_id');
            }
        });

        Schema::table('investor_profiles', function (Blueprint $table) {
            $indexes = collect(Schema::getIndexes('investor_profiles'))->pluck('name');
            if (!$indexes->contains('investor_profiles_user_id_index') && !$indexes->contains('investor_profiles_user_id_foreign')) {
                $table->index('user_id');
            }
        });

        Schema::table('program_enrollments', function (Blueprint $table) {
            $indexes = collect(Schema::getIndexes('program_enrollments'))->pluck('name');
            if (!$indexes->contains('program_enrollments_program_id_status_index')) {
                $table->index(['program_id', 'status']);
            }
            if (!$indexes->contains('program_enrollments_sme_id_status_index')) {
                $table->index(['sme_id', 'status']);
            }
            if (!$indexes->contains('program_enrollments_investor_id_status_index')) {
                $table->index(['investor_id', 'status']);
            }
        });

        Schema::table('assessments', function (Blueprint $table) {
            $indexes = collect(Schema::getIndexes('assessments'))->pluck('name');
            if (!$indexes->contains('assessments_sme_id_status_index')) {
                $table->index(['sme_id', 'status']);
            }
            if (!$indexes->contains('assessments_template_id_status_index')) {
                $table->index(['template_id', 'status']);
            }
        });

        Schema::table('questions', function (Blueprint $table) {
            $indexes = collect(Schema::getIndexes('questions'))->pluck('name');
            if (!$indexes->contains('questions_template_id_pillar_id_index')) {
                $table->index(['template_id', 'pillar_id']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) { $table->dropIndex(['role', 'status']); });
        Schema::table('sme_profiles', function (Blueprint $table) { $table->dropIndex(['user_id']); });
        Schema::table('investor_profiles', function (Blueprint $table) { $table->dropIndex(['user_id']); });
        Schema::table('program_enrollments', function (Blueprint $table) {
            $table->dropIndex(['program_id', 'status']);
            $table->dropIndex(['sme_id', 'status']);
            $table->dropIndex(['investor_id', 'status']);
        });
        Schema::table('assessments', function (Blueprint $table) {
            $table->dropIndex(['sme_id', 'status']);
            $table->dropIndex(['template_id', 'status']);
        });
        Schema::table('questions', function (Blueprint $table) { $table->dropIndex(['template_id', 'pillar_id']); });
    }
};
