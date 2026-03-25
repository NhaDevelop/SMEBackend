<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goals', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by')->nullable()->after('sme_id');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            
            $table->text('proof_note')->nullable()->after('pillar_targets');
            $table->string('proof_document')->nullable()->after('proof_note');
        });
    }

    public function down(): void
    {
        Schema::table('goals', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn(['created_by', 'proof_note', 'proof_document']);
        });
    }
};
