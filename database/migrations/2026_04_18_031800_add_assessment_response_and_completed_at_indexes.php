<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fix #1: Add missing indexes for assessment_responses.assessment_id
     * and assessments.completed_at (used heavily in ORDER BY and filtering).
     */
    public function up(): void
    {
        Schema::table('assessment_responses', function (Blueprint $table) {
            $indexes = collect(Schema::getIndexes('assessment_responses'))->pluck('name');
            if (!$indexes->contains('assessment_responses_assessment_id_index')
                && !$indexes->contains('assessment_responses_assessment_id_foreign')) {
                $table->index('assessment_id');
            }
        });

        Schema::table('assessments', function (Blueprint $table) {
            $indexes = collect(Schema::getIndexes('assessments'))->pluck('name');
            // Index on completed_at is used in ORDER BY completed_at DESC queries
            if (!$indexes->contains('assessments_completed_at_index')) {
                $table->index('completed_at');
            }
            // Composite index for sme+template+status (used in dealflow unique() query)
            if (!$indexes->contains('assessments_sme_id_template_id_status_index')) {
                $table->index(['sme_id', 'template_id', 'status'], 'assessments_sme_id_template_id_status_index');
            }
        });

        Schema::table('program_enrollments', function (Blueprint $table) {
            $indexes = collect(Schema::getIndexes('program_enrollments'))->pluck('name');
            // Standalone sme_id index for pluck('sme_id') queries
            if (!$indexes->contains('program_enrollments_sme_id_index')
                && !$indexes->contains('program_enrollments_sme_id_foreign')) {
                $table->index('sme_id');
            }
            // Standalone investor_id index
            if (!$indexes->contains('program_enrollments_investor_id_index')
                && !$indexes->contains('program_enrollments_investor_id_foreign')) {
                $table->index('investor_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('assessment_responses', function (Blueprint $table) {
            $table->dropIndex(['assessment_id']);
        });
        Schema::table('assessments', function (Blueprint $table) {
            $table->dropIndex(['completed_at']);
            $table->dropIndex('assessments_sme_id_template_id_status_index');
        });
        Schema::table('program_enrollments', function (Blueprint $table) {
            $table->dropIndex(['sme_id']);
            $table->dropIndex(['investor_id']);
        });
    }
};
